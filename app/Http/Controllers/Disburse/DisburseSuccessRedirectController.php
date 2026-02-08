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
            Log::info("[DisburseSuccessRedirectController] Redirecting to rider URL: {$redirectUrl}");

            return inertia()->location($redirectUrl);
        }

        $fallbackUrl = config('disburse.success.redirect.fallback_url', '/disburse');
        Log::warning("[DisburseSuccessRedirectController] No rider URL found; redirecting to fallback: {$fallbackUrl}");

        return inertia()->location($fallbackUrl);
    }
}
