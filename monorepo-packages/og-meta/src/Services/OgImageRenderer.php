<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Services;

use Illuminate\Support\Facades\Storage;
use LBHurtado\OgMeta\Data\OgMetaData;

class OgImageRenderer
{
    /**
     * Generate an OG image for the given metadata and return its public URL.
     */
    public function generate(OgMetaData $data, string $resolverKey): string
    {
        $prefix = config('og-meta.cache_prefix', 'og');
        $cacheKey = $data->cacheKey ?? 'default';
        $filename = "{$prefix}/{$resolverKey}/{$cacheKey}-{$data->status}.png";
        $disk = config('og-meta.cache_disk', 'public');

        // Cache hit — check freshness if httpMaxAge is set
        if (Storage::disk($disk)->exists($filename)) {
            if ($this->isFresh($disk, $filename, $data->httpMaxAge)) {
                return Storage::disk($disk)->url($filename);
            }
        }

        // Clean stale images for this cache key (different status)
        $this->cleanStaleImages($disk, "{$prefix}/{$resolverKey}", $cacheKey, $filename);

        $image = $this->render($data);

        Storage::disk($disk)->makeDirectory("{$prefix}/{$resolverKey}");
        Storage::disk($disk)->put($filename, $image);

        return Storage::disk($disk)->url($filename);
    }

    /**
     * Render a fallback image with just the app name.
     */
    public function renderFallback(): string
    {
        $width = config('og-meta.dimensions.width', 1200);
        $height = config('og-meta.dimensions.height', 630);
        $appName = config('og-meta.app_name') ?? config('app.name', 'App');

        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 245, 245, 245);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        $fontBold = $this->fontPath('bold');
        if (file_exists($fontBold)) {
            $dark = imagecolorallocate($img, 60, 60, 60);
            imagettftext($img, 36, 0, (int) (($width - 300) / 2), (int) ($height / 2), $dark, $fontBold, $appName);
        }

        return $this->capturePng($img);
    }

    /**
     * Render the OG image card from OgMetaData.
     */
    private function render(OgMetaData $data): string
    {
        $width = config('og-meta.dimensions.width', 1200);
        $height = config('og-meta.dimensions.height', 630);

        $img = imagecreatetruecolor($width, $height);

        // Colors
        $bgColor = $this->statusBackgroundColor($img, $data->status);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 30, 30, 30);
        $gray = imagecolorallocate($img, 120, 120, 120);
        $badgeColor = $this->statusBadgeColor($img, $data->status);

        // Fill background
        imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

        // Draw card area (white rect)
        $cardMargin = 60;
        imagefilledrectangle(
            $img,
            $cardMargin, $cardMargin,
            $width - $cardMargin, $height - $cardMargin,
            $white
        );

        // Fonts
        $fontBold = $this->fontPath('bold');
        $fontRegular = $this->fontPath('regular');

        if (! file_exists($fontRegular)) {
            $fontRegular = $fontBold;
        }

        $contentX = 120;

        // App name
        $appName = config('og-meta.app_name') ?? config('app.name', 'App');
        imagettftext($img, 16, 0, $contentX, 130, $gray, $fontRegular, $appName);

        // Headline (large text)
        imagettftext($img, 52, 0, $contentX, 230, $dark, $fontBold, $data->headline);

        // Subtitle
        if ($data->subtitle) {
            imagettftext($img, 40, 0, $contentX, 310, $dark, $fontBold, $data->subtitle);
        }

        // Status badge
        $statusLabel = strtoupper($data->status);
        $badgeY = $data->subtitle ? 370 : 310;
        $badgePadX = 20;
        $badgePadY = 12;
        $fontSize = 18;

        $bbox = imagettfbbox($fontSize, 0, $fontBold, $statusLabel);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        imagefilledrectangle(
            $img,
            $contentX, $badgeY,
            $contentX + $textWidth + $badgePadX * 2,
            $badgeY + $textHeight + $badgePadY * 2,
            $badgeColor
        );

        imagettftext(
            $img, $fontSize, 0,
            $contentX + $badgePadX,
            $badgeY + $badgePadY + $textHeight,
            $white, $fontBold, $statusLabel
        );

        // Bottom tagline
        if ($data->tagline) {
            imagettftext($img, 14, 0, $contentX, $height - 100, $gray, $fontRegular, $data->tagline);
        }

        return $this->capturePng($img);
    }

    private function statusBackgroundColor(\GdImage $img, string $status): int
    {
        $colors = config("og-meta.statuses.{$status}.bg")
            ?? config('og-meta.fallback_status.bg', [243, 244, 246]);

        return imagecolorallocate($img, $colors[0], $colors[1], $colors[2]);
    }

    private function statusBadgeColor(\GdImage $img, string $status): int
    {
        $colors = config("og-meta.statuses.{$status}.badge")
            ?? config('og-meta.fallback_status.badge', [107, 114, 128]);

        return imagecolorallocate($img, $colors[0], $colors[1], $colors[2]);
    }

    private function fontPath(string $weight): string
    {
        $configKey = "og-meta.fonts.{$weight}";
        $customPath = config($configKey);

        if ($customPath && file_exists($customPath)) {
            return $customPath;
        }

        // Package-bundled fonts
        $filename = $weight === 'bold' ? 'Inter-Bold.ttf' : 'Inter-Regular.ttf';

        return __DIR__.'/../../resources/fonts/'.$filename;
    }

    /**
     * Check if a cached file is still fresh based on the given max-age.
     */
    private function isFresh(string $disk, string $filename, ?int $maxAge): bool
    {
        // No TTL hint — treat as fresh (infinite cache)
        if ($maxAge === null) {
            return true;
        }

        $lastModified = Storage::disk($disk)->lastModified($filename);

        return (time() - $lastModified) < $maxAge;
    }

    private function cleanStaleImages(string $disk, string $directory, string $cacheKey, string $currentFilename): void
    {
        $files = Storage::disk($disk)->files($directory);

        foreach ($files as $file) {
            if (str_starts_with(basename($file), "{$cacheKey}-") && $file !== $currentFilename) {
                Storage::disk($disk)->delete($file);
            }
        }
    }

    private function capturePng(\GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 6);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }
}
