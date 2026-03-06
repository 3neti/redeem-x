<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Services;

use Illuminate\Http\Request;
use LBHurtado\OgMeta\Contracts\OgMetaResolver;
use LBHurtado\OgMeta\Data\OgMetaData;

class OgMetaService
{
    /** @var array<string, class-string<OgMetaResolver>> */
    private array $resolvers;

    public function __construct(
        private readonly OgImageRenderer $renderer,
        array $resolvers = [],
    ) {
        $this->resolvers = $resolvers;
    }

    /**
     * Resolve OG metadata using a specific resolver key.
     */
    public function resolveByKey(string $key, Request $request): ?OgMetaData
    {
        $resolverClass = $this->resolvers[$key] ?? null;

        if (! $resolverClass) {
            return null;
        }

        $resolver = $this->makeResolver($resolverClass);
        $data = $resolver->resolve($request);

        if ($data) {
            return $this->ensureImageUrl($data, $key);
        }

        return null;
    }

    /**
     * Resolve OG metadata for an image request using a specific resolver key.
     */
    public function resolveForImage(string $resolverKey, string $identifier): ?OgMetaData
    {
        $resolverClass = $this->resolvers[$resolverKey] ?? null;

        if (! $resolverClass) {
            return null;
        }

        return $this->makeResolver($resolverClass)->resolveForImage($identifier);
    }

    /**
     * Generate an OG image and return the cached URL.
     */
    public function generateImage(OgMetaData $data, string $resolverKey): string
    {
        return $this->renderer->generate($data, $resolverKey);
    }

    /**
     * Ensure imageUrl is set on the OgMetaData, generating if needed.
     */
    private function ensureImageUrl(OgMetaData $data, string $resolverKey): OgMetaData
    {
        if ($data->imageUrl) {
            return $data;
        }

        $identifier = $data->cacheKey ?? 'default';
        $imageUrl = url("/og/{$resolverKey}/{$identifier}");

        return new OgMetaData(
            title: $data->title,
            description: $data->description,
            status: $data->status,
            headline: $data->headline,
            subtitle: $data->subtitle,
            tagline: $data->tagline,
            url: $data->url,
            imageUrl: $imageUrl,
            cacheKey: $data->cacheKey,
            httpMaxAge: $data->httpMaxAge,
            message: $data->message,
            overlayImage: $data->overlayImage,
            splashHtml: $data->splashHtml,
            typeBadge: $data->typeBadge,
            payeeBadge: $data->payeeBadge,
            qrDataUri: $data->qrDataUri,
        );
    }

    private function makeResolver(string $resolverClass): OgMetaResolver
    {
        return app($resolverClass);
    }
}
