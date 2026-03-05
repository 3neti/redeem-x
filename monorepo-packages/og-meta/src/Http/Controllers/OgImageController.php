<?php

declare(strict_types=1);

namespace LBHurtado\OgMeta\Http\Controllers;

use Illuminate\Http\Response;
use LBHurtado\OgMeta\Services\OgImageRenderer;
use LBHurtado\OgMeta\Services\OgMetaService;

class OgImageController
{
    public function __construct(
        private readonly OgMetaService $service,
        private readonly OgImageRenderer $renderer,
    ) {}

    /**
     * Serve a dynamically generated OG image.
     *
     * Route: GET /og/{resolverKey}/{identifier}
     */
    public function __invoke(string $resolverKey, string $identifier): Response
    {
        $data = $this->service->resolveForImage($resolverKey, $identifier);

        if (! $data) {
            return $this->fallbackResponse();
        }

        // Generate (or serve cached) image
        $this->service->generateImage($data, $resolverKey);

        // Read the generated file and serve it
        $prefix = config('og-meta.cache_prefix', 'og');
        $cacheKey = $data->cacheKey ?? 'default';
        $filename = "{$prefix}/{$resolverKey}/{$cacheKey}-{$data->status}.png";
        $disk = config('og-meta.cache_disk', 'public');

        $contents = \Illuminate\Support\Facades\Storage::disk($disk)->get($filename);

        if (! $contents) {
            return $this->fallbackResponse();
        }

        $maxAge = $data->httpMaxAge ?? 3600;

        return response($contents)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', "public, max-age={$maxAge}");
    }

    private function fallbackResponse(): Response
    {
        return response($this->renderer->renderFallback())
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
