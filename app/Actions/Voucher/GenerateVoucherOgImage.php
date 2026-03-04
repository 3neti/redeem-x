<?php

declare(strict_types=1);

namespace App\Actions\Voucher;

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateVoucherOgImage
{
    use AsAction;

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    public function handle(Voucher $voucher): string
    {
        $status = $this->resolveStatus($voucher);
        $filename = "og/{$voucher->code}-{$status}.png";

        // Cache hit — file already exists for this code + status
        if (Storage::disk('public')->exists($filename)) {
            return Storage::disk('public')->url($filename);
        }

        // Clean up stale images for this code (different status)
        $this->cleanStaleImages($voucher->code, $filename);

        $image = $this->render($voucher, $status);

        Storage::disk('public')->makeDirectory('og');
        Storage::disk('public')->put($filename, $image);

        return Storage::disk('public')->url($filename);
    }

    private function render(Voucher $voucher, string $status): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // Colors
        $bgColor = $this->statusBackground($img, $status);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 30, 30, 30);
        $gray = imagecolorallocate($img, 120, 120, 120);
        $badgeColor = $this->statusBadgeColor($img, $status);

        // Fill background
        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $bgColor);

        // Draw card area (white rounded rect approximation)
        $cardMargin = 60;
        imagefilledrectangle(
            $img,
            $cardMargin, $cardMargin,
            self::WIDTH - $cardMargin, self::HEIGHT - $cardMargin,
            $white
        );

        // Fonts
        $fontBold = storage_path('fonts/Inter-Bold.ttf');
        $fontRegular = storage_path('fonts/Inter-Regular.ttf');

        // Fallback if fonts missing — use bold as regular too
        if (! file_exists($fontRegular)) {
            $fontRegular = $fontBold;
        }

        $contentX = 120;

        // App name
        imagettftext($img, 16, 0, $contentX, 130, $gray, $fontRegular, config('app.name', 'RedeemX'));

        // Voucher code (large)
        imagettftext($img, 52, 0, $contentX, 230, $dark, $fontBold, $voucher->code);

        // Amount
        $amount = $voucher->instructions->cash->amount ?? 0;
        $currency = $voucher->instructions->cash->currency ?? 'PHP';
        $formattedAmount = '₱'.number_format((float) $amount, 2);
        imagettftext($img, 40, 0, $contentX, 310, $dark, $fontBold, $formattedAmount);

        // Status badge
        $statusLabel = strtoupper($status);
        $badgeY = 370;
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
        imagettftext($img, 14, 0, $contentX, self::HEIGHT - 100, $gray, $fontRegular, 'Tap to redeem this voucher');

        // Capture PNG
        ob_start();
        imagepng($img, null, 6);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    private function resolveStatus(Voucher $voucher): string
    {
        if ($voucher->isRedeemed()) {
            return 'redeemed';
        }
        if ($voucher->isExpired()) {
            return 'expired';
        }
        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            return 'pending';
        }

        return 'active';
    }

    private function statusBackground(\GdImage $img, string $status): int
    {
        return match ($status) {
            'redeemed' => imagecolorallocate($img, 229, 231, 235),  // gray-200
            'expired' => imagecolorallocate($img, 254, 226, 226),  // red-100
            'pending' => imagecolorallocate($img, 254, 243, 199),  // yellow-100
            default => imagecolorallocate($img, 220, 252, 231),  // green-100
        };
    }

    private function statusBadgeColor(\GdImage $img, string $status): int
    {
        return match ($status) {
            'redeemed' => imagecolorallocate($img, 107, 114, 128),  // gray-500
            'expired' => imagecolorallocate($img, 220, 38, 38),    // red-600
            'pending' => imagecolorallocate($img, 202, 138, 4),    // yellow-600
            default => imagecolorallocate($img, 22, 163, 74),    // green-600
        };
    }

    private function cleanStaleImages(string $code, string $currentFilename): void
    {
        $files = Storage::disk('public')->files('og');
        foreach ($files as $file) {
            if (str_starts_with(basename($file), "{$code}-") && $file !== $currentFilename) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
