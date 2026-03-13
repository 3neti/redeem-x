<?php

declare(strict_types=1);

namespace App\Http\Controllers\Disburse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Handles the post-redemption redirect based on voucher instructions.
 *
 * Redirects the user to the URL specified in the voucher's rider instructions,
 * if present. Otherwise, falls back to a default route.
 */
class DisburseSuccessRedirectController extends Controller
{
    /**
     * Handle the incoming request and redirect accordingly.
     */
    public function __invoke(Request $request, string $code)
    {
        Log::info("[DisburseSuccessRedirectController] Invoked for voucher code: {$code}");

        // Fetch voucher by code (no state validation - allow redeemed vouchers)
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            Log::warning("[DisburseSuccessRedirectController] Voucher not found: {$code}");

            return redirect()->route('disburse.start')
                ->withErrors(['error' => 'Invalid voucher code.']);
        }

        $redirectUrl = $voucher->instructions->rider->url ?? null;

        if ($redirectUrl) {
            // Only redirect to http(s) URLs — intent:// and other app-specific
            // schemes fail silently on desktop browsers.
            if (preg_match('#^https?://#i', $redirectUrl)) {
                Log::info("[DisburseSuccessRedirectController] Redirecting to rider URL: {$redirectUrl}");

                return inertia()->location($redirectUrl);
            }

            // Try to extract browser_fallback_url from Android intent:// URIs
            if (str_starts_with($redirectUrl, 'intent://')) {
                if (preg_match('/S\.browser_fallback_url=([^;]+)/', $redirectUrl, $m)) {
                    $fallback = urldecode($m[1]);
                    Log::info("[DisburseSuccessRedirectController] Extracted intent fallback URL: {$fallback}");

                    return inertia()->location($fallback);
                }
            }

            Log::warning("[DisburseSuccessRedirectController] Unsupported URL scheme, skipping redirect: {$redirectUrl}");
        }

        $fallbackUrl = config('disburse.success.redirect.fallback_url', '/disburse');
        Log::warning("[DisburseSuccessRedirectController] No usable rider URL; redirecting to fallback: {$fallbackUrl}");

        return inertia()->location($fallbackUrl);
    }
}
