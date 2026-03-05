<?php

namespace App\Pipelines\RedeemedVoucher;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ClearOgMetaCache is a pipeline stage that deletes cached OG meta
 * images for a redeemed voucher so the next crawler request renders
 * a fresh card reflecting the new status.
 *
 * Cached files live at: {cache_prefix}/{resolverKey}/{code}-{status}.png
 * on the configured cache disk (default: public).
 */
class ClearOgMetaCache
{
    public function handle($voucher, Closure $next)
    {
        $disk = config('og-meta.cache_disk', 'public');
        $prefix = config('og-meta.cache_prefix', 'og');
        $directory = "{$prefix}/disburse";

        $pattern = "{$voucher->code}-";

        $deleted = 0;

        try {
            $files = Storage::disk($disk)->files($directory);

            foreach ($files as $file) {
                if (str_contains(basename($file), $pattern)) {
                    Storage::disk($disk)->delete($file);
                    $deleted++;
                }
            }

            if ($deleted > 0) {
                Log::info('[ClearOgMetaCache] Cleared cached OG images', [
                    'voucher' => $voucher->code,
                    'deleted' => $deleted,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-blocking — cache miss just triggers re-render
            Log::warning('[ClearOgMetaCache] Failed to clear cache', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
            ]);
        }

        return $next($voucher);
    }
}
