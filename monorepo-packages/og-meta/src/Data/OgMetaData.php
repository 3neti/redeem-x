<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Data;

use Spatie\LaravelData\Data;

class OgMetaData extends Data
{
    public function __construct(
        /** og:title */
        public string $title,

        /** og:description */
        public string $description,

        /** Status string — drives badge color + background on the image */
        public string $status,

        /** Large text on the image card (e.g. voucher code) */
        public string $headline,

        /** Secondary text on the image card (e.g. formatted amount) */
        public ?string $subtitle = null,

        /** Bottom text on the image card (e.g. 'Tap to redeem') */
        public ?string $tagline = null,

        /** og:url — canonical URL for the page */
        public ?string $url = null,

        /** og:image — auto-set by OgMetaService if left null */
        public ?string $imageUrl = null,

        /** Custom cache key segment for the generated image (defaults to model key) */
        public ?string $cacheKey = null,

        /** HTTP Cache-Control max-age in seconds. Null = package default (3600). */
        public ?int $httpMaxAge = null,

        /** Extra text rendered on the image card below the status badge */
        public ?string $message = null,

        /** Base64-encoded image rendered on the right side of the image card (GD mode) */
        public ?string $overlayImage = null,

        /** Raw HTML rendered natively on the image card (screenshot mode) */
        public ?string $splashHtml = null,

        /** Secondary badge label on the image card (e.g. 'redeemable', 'payable') */
        public ?string $typeBadge = null,

        /** Payee badge label on the image card (e.g. vendor alias, mobile, 'CASH') */
        public ?string $payeeBadge = null,
    ) {}

    /**
     * Default OG data when no resolver matches.
     */
    public static function defaults(): static
    {
        $appName = config('og-meta.app_name') ?? config('app.name', 'App');

        return new static(
            title: "{$appName}",
            description: $appName,
            status: 'active',
            headline: $appName,
            url: config('app.url'),
        );
    }

    /**
     * Convert to the array format expected by the Blade OG tags.
     *
     * Returns ['title', 'description', 'image', 'url'] matching
     * the existing $og view data contract.
     */
    public function toViewData(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->imageUrl,
            'url' => $this->url ?? config('app.url'),
        ];
    }
}
