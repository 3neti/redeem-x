<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UrlUnfurler
{
    private const CACHE_TTL = 3600; // 1 hour

    private const CONNECT_TIMEOUT = 5;

    private const REQUEST_TIMEOUT = 10;

    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * Unfurl a URL: fetch the page and extract OG meta tags.
     *
     * @return array{og_image: ?string, og_title: ?string, og_description: ?string}|null
     */
    public function unfurl(string $url): ?array
    {
        $cacheKey = 'url_unfurl:'.md5($url);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($url) {
            try {
                $response = Http::withOptions([
                    'connect_timeout' => self::CONNECT_TIMEOUT,
                    'timeout' => self::REQUEST_TIMEOUT,
                ])->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; RedeemX/1.0; +'.config('app.url').')',
                    'Accept' => 'text/html',
                ])->get($url);

                if (! $response->successful()) {
                    return null;
                }

                return $this->parseOgTags($response->body());
            } catch (\Exception $e) {
                Log::debug('[UrlUnfurler] Failed to unfurl URL', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Download an image URL and return its base64-encoded binary.
     */
    public function fetchImage(string $imageUrl): ?string
    {
        $cacheKey = 'url_unfurl_img:'.md5($imageUrl);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($imageUrl) {
            try {
                $response = Http::withOptions([
                    'connect_timeout' => self::CONNECT_TIMEOUT,
                    'timeout' => self::REQUEST_TIMEOUT,
                ])->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; RedeemX/1.0)',
                ])->get($imageUrl);

                if (! $response->successful()) {
                    return null;
                }

                $body = $response->body();

                if (strlen($body) > self::MAX_IMAGE_BYTES) {
                    Log::debug('[UrlUnfurler] Image exceeds size limit', [
                        'url' => $imageUrl,
                        'size' => strlen($body),
                    ]);

                    return null;
                }

                return base64_encode($body);
            } catch (\Exception $e) {
                Log::debug('[UrlUnfurler] Failed to fetch image', [
                    'url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Parse OG meta tags from HTML.
     */
    private function parseOgTags(string $html): array
    {
        $result = [
            'og_image' => null,
            'og_title' => null,
            'og_description' => null,
        ];

        $mapping = [
            'og:image' => 'og_image',
            'og:title' => 'og_title',
            'og:description' => 'og_description',
        ];

        // Match <meta property="og:xxx" content="...">
        preg_match_all(
            '/<meta\s+[^>]*property=["\'](?P<prop>og:[^"\']+)["\'][^>]*content=["\'](?P<val>[^"\']*)["\'][^>]*\/?>/i',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        // Also match reversed order: content before property
        preg_match_all(
            '/<meta\s+[^>]*content=["\'](?P<val>[^"\']*)["\'][^>]*property=["\'](?P<prop>og:[^"\']+)["\'][^>]*\/?>/i',
            $html,
            $reversedMatches,
            PREG_SET_ORDER
        );

        foreach (array_merge($matches, $reversedMatches) as $match) {
            $prop = strtolower($match['prop']);
            if (isset($mapping[$prop]) && ! $result[$mapping[$prop]]) {
                $result[$mapping[$prop]] = html_entity_decode($match['val'], ENT_QUOTES, 'UTF-8');
            }
        }

        return $result;
    }
}
