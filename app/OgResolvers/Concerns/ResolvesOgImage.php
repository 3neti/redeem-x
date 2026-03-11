<?php

declare(strict_types=1);

namespace App\OgResolvers\Concerns;

use App\Services\SuccessContentService;
use App\Services\UrlUnfurler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;

trait ResolvesOgImage
{
    /**
     * Resolve OG image fields based on the rider's og_source preference.
     *
     * @return array{splashHtml: ?string, overlayImage: ?string}
     */
    protected function resolveImageFields(RiderInstructionData $rider, string $status, Voucher $voucher): array
    {
        $source = $rider->og_source;

        if ($source === null) {
            return ['splashHtml' => null, 'overlayImage' => null];
        }

        return match ($source) {
            'message' => $this->buildMessageImage($rider->message, $voucher),
            'url' => $this->buildUrlImage($rider->url),
            'splash' => $this->buildSplashImage($rider->splash, $voucher),
            default => ['splashHtml' => null, 'overlayImage' => null],
        };
    }

    /**
     * Message mode: render rider message as a styled HTML block.
     */
    private function buildMessageImage(?string $message, Voucher $voucher): array
    {
        if (empty($message)) {
            return ['splashHtml' => null, 'overlayImage' => null];
        }

        $service = app(SuccessContentService::class);
        $context = $this->buildTemplateContext($voucher);
        $processed = $service->processContent($message, $context);
        $content = $processed ? $processed['content'] : e($message);
        $type = $processed['type'] ?? 'text';

        // Wrap in a styled container for the card
        $html = $type === 'html' || $type === 'svg'
            ? $this->wrapContentHtml($content)
            : $this->wrapTextHtml(e($message));

        return ['splashHtml' => $html, 'overlayImage' => null];
    }

    /**
     * URL mode: unfurl the URL, fetch its og:image.
     */
    private function buildUrlImage(?string $url): array
    {
        if (empty($url)) {
            return ['splashHtml' => null, 'overlayImage' => null];
        }

        try {
            $unfurler = app(UrlUnfurler::class);
            $meta = $unfurler->unfurl($url);

            if ($meta && ! empty($meta['og_image'])) {
                $base64 = $unfurler->fetchImage($meta['og_image']);
                if ($base64) {
                    return ['splashHtml' => null, 'overlayImage' => $base64];
                }
            }
        } catch (\Exception $e) {
            Log::debug('[ResolvesOgImage] URL unfurl failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: render the URL as text on the card
        $html = $this->wrapTextHtml(e($url));

        return ['splashHtml' => $html, 'overlayImage' => null];
    }

    /**
     * Splash mode: process splash content and render as HTML.
     */
    private function buildSplashImage(?string $splash, Voucher $voucher): array
    {
        if (empty($splash)) {
            return ['splashHtml' => null, 'overlayImage' => null];
        }

        $service = app(SuccessContentService::class);
        $context = $this->buildTemplateContext($voucher);
        $processed = $service->processContent($splash, $context);

        if (! $processed) {
            return ['splashHtml' => null, 'overlayImage' => null];
        }

        $html = match ($processed['type']) {
            'svg' => $this->wrapContentHtml($processed['content']),
            'html' => $this->wrapContentHtml($processed['content']),
            'markdown' => $this->wrapContentHtml($processed['content']),
            'url' => $this->wrapTextHtml(e($processed['content'])),
            default => $this->wrapTextHtml(e($processed['content'])),
        };

        return ['splashHtml' => $html, 'overlayImage' => null];
    }

    /**
     * Wrap plain text in a centered, auto-sized HTML container.
     */
    private function wrapTextHtml(string $text): string
    {
        return <<<HTML
        <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:40px;box-sizing:border-box;">
            <p style="font-family:'Inter',sans-serif;font-size:clamp(24px,5vw,64px);font-weight:700;color:#1a1a1a;text-align:center;line-height:1.2;word-break:break-word;margin:0;">{$text}</p>
        </div>
        HTML;
    }

    /**
     * Wrap raw HTML/SVG content in a fullbleed container.
     */
    private function wrapContentHtml(string $content): string
    {
        return <<<HTML
        <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:24px;box-sizing:border-box;overflow:hidden;">
            {$content}
        </div>
        HTML;
    }

    /**
     * Build template variable context for SuccessContentService.
     */
    private function buildTemplateContext(Voucher $voucher): array
    {
        $amount = $voucher->instructions->cash->amount ?? 0;
        $currency = $voucher->instructions->cash->currency ?? 'PHP';

        return [
            'voucher_code' => $voucher->code,
            'amount' => Number::currency($amount, $currency),
            'currency' => $currency,
        ];
    }
}
