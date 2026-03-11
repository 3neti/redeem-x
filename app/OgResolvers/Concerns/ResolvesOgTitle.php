<?php

declare(strict_types=1);

namespace App\OgResolvers\Concerns;

use App\Services\SuccessContentService;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;

trait ResolvesOgTitle
{
    /**
     * Resolve the OG title based on the rider's og_source preference.
     *
     * When og_source is set, the corresponding rider field is used.
     * Splash content is processed through SuccessContentService to handle
     * template variables and content-type detection, then stripped to plain text.
     *
     * When og_source is null, falls back to rider->message ?? defaultTitle().
     */
    protected function resolveOgTitle(RiderInstructionData $rider, string $status, Voucher $voucher): string
    {
        $source = $rider->og_source;

        if ($source === null) {
            return $rider->message ?? $this->defaultTitle($status);
        }

        $value = match ($source) {
            'message' => $rider->message,
            'url' => $rider->url,
            'splash' => $this->extractSplashText($rider->splash, $voucher),
            default => null,
        };

        return $value ?: $this->defaultTitle($status);
    }

    /**
     * Process splash content through SuccessContentService and extract plain text.
     */
    private function extractSplashText(?string $splash, Voucher $voucher): ?string
    {
        if (empty($splash)) {
            return null;
        }

        $service = app(SuccessContentService::class);

        $amount = $voucher->instructions->cash->amount ?? 0;
        $currency = $voucher->instructions->cash->currency ?? 'PHP';

        $context = [
            'voucher_code' => $voucher->code,
            'amount' => Number::currency($amount, $currency),
            'currency' => $currency,
        ];

        $processed = $service->processContent($splash, $context);

        if (! $processed) {
            return null;
        }

        // Strip to plain text for og:title
        $text = match ($processed['type']) {
            'html', 'svg' => strip_tags($processed['content']),
            'markdown' => strip_tags($processed['content']),
            default => $processed['content'],
        };

        // Trim to a sensible OG title length
        $text = trim($text);

        return mb_strlen($text) > 120
            ? mb_substr($text, 0, 117).'…'
            : $text;
    }
}
