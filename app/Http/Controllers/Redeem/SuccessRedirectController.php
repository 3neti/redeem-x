<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redeem;

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
class SuccessRedirectController extends Controller
{
    /**
     * Handle the incoming request and redirect accordingly.
     */
    public function __invoke(Request $request, string $code)
    {
        Log::info("[SuccessRedirectController] Invoked for voucher code: {$code}");

        // Fetch voucher by code (no state validation - allow redeemed vouchers)
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            Log::warning("[SuccessRedirectController] Voucher not found: {$code}");

            return redirect()->route('redeem.start')
                ->withErrors(['error' => 'Invalid voucher code.']);
        }

        $redirectUrl = $voucher->instructions->rider->url ?? null;

        if ($redirectUrl) {
            Log::info("[SuccessRedirectController] Redirecting to rider URL: {$redirectUrl}");

            return inertia()->location($redirectUrl);
        }

        $fallbackUrl = config('redeem.success.redirect.fallback_url', '/redeem');
        Log::warning("[SuccessRedirectController] No rider URL found; redirecting to fallback: {$fallbackUrl}");

        return inertia()->location($fallbackUrl);
    }
}
