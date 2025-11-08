<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redeem;

use App\Actions\Voucher\ProcessRedemption;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use LBHurtado\Voucher\Data\VoucherData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Redemption controller.
 *
 * Handles redemption start, confirmation, and success pages.
 */
class RedeemController extends Controller
{
    /**
     * Show the redemption start page.
     * If code is provided in query string, validate and redirect to wallet step.
     *
     * @return Response|RedirectResponse
     */
    public function start(): Response|RedirectResponse
    {
        // If code is provided in query string, validate and redirect to wallet step
        $code = request()->query('code');
        if ($code) {
            try {
                $voucher = Voucher::where('code', strtoupper(trim($code)))->firstOrFail();
                
                // Check if voucher can be redeemed
                if ($voucher->isRedeemed()) {
                    return redirect()->route('redeem.start')
                        ->with('error', 'This voucher has already been redeemed.');
                }
                
                if ($voucher->isExpired()) {
                    return redirect()->route('redeem.start')
                        ->with('error', 'This voucher has expired.');
                }
                
                if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
                    return redirect()->route('redeem.start')
                        ->with('error', 'This voucher is not yet active.');
                }
                
                return redirect()->route('redeem.wallet', ['voucher' => $voucher->code]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return redirect()->route('redeem.start')
                    ->with('error', 'Invalid voucher code.');
            }
        }
        
        return Inertia::render('Redeem/Start');
    }

    /**
     * Confirm and execute voucher redemption.
     *
     * @param  Voucher  $voucher
     * @return RedirectResponse
     */
    public function confirm(Voucher $voucher): RedirectResponse
    {
        $voucherCode = $voucher->code;

        Log::info('[RedeemController] Confirming redemption', [
            'voucher' => $voucherCode,
        ]);

        // Gather all session data
        $mobile = Session::get("redeem.{$voucherCode}.mobile");
        $country = Session::get("redeem.{$voucherCode}.country", 'PH');
        $wallet = Session::get("redeem.{$voucherCode}.wallet", []);
        $inputs = Session::get("redeem.{$voucherCode}.inputs", []);
        $signature = Session::get("redeem.{$voucherCode}.signature");

        // Validate required data
        if (! $mobile) {
            Log::error('[RedeemController] No mobile number in session', [
                'voucher' => $voucherCode,
            ]);

            return redirect()
                ->route('redeem.wallet', $voucher)
                ->with('error', 'Mobile number is required.');
        }

        // Merge all inputs
        $allInputs = array_merge(
            $inputs,
            $signature ? ['signature' => $signature] : []
        );

        // Prepare bank account data
        $bankAccount = [
            'bank_code' => $wallet['bank_code'] ?? null,
            'account_number' => $wallet['account_number'] ?? null,
        ];

        try {
            // Create PhoneNumber instance
            $phoneNumber = new PhoneNumber($mobile, $country);

            // Process redemption (uses transaction)
            ProcessRedemption::run(
                $voucher,
                $phoneNumber,
                $allInputs,
                $bankAccount
            );

            Log::info('[RedeemController] Redemption successful', [
                'voucher' => $voucherCode,
                'mobile' => $phoneNumber->formatE164(),
            ]);

            // Clear session data
            $this->clearRedemptionSession($voucherCode);

            // Mark as redeemed in session (for success page)
            Session::put("redeem.{$voucherCode}.redeemed", true);

            return redirect()
                ->route('redeem.success', $voucher)
                ->with('success', 'Voucher redeemed successfully!');
        } catch (\Throwable $e) {
            Log::error('[RedeemController] Redemption failed', [
                'voucher' => $voucherCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('redeem.finalize', $voucher)
                ->with('error', 'Failed to redeem voucher. Please try again.');
        }
    }

    /**
     * Show redemption success page.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function success(Voucher $voucher): Response
    {
        // Verify voucher is redeemed
        if (! $voucher->isRedeemed()) {
            Log::warning('[RedeemController] Accessed success page for unredeemed voucher', [
                'voucher' => $voucher->code,
            ]);

            return Inertia::render('Redeem/Error', [
                'message' => 'This voucher has not been redeemed yet.',
            ]);
        }

        Log::info('[RedeemController] Showing success page', [
            'voucher' => $voucher->code,
        ]);

        return Inertia::render('Redeem/Success', [
            'voucher' => VoucherData::fromModel($voucher),
            'rider' => [
                'message' => $voucher->instructions->rider->message ?? null,
                'url' => $voucher->instructions->rider->url ?? config('x-change.redeem.success.rider'),
            ],
            'redirect_timeout' => config('x-change.redeem.success.redirect_timeout', 5),
        ]);
    }

    /**
     * Clear redemption session data.
     *
     * @param  string  $voucherCode
     * @return void
     */
    protected function clearRedemptionSession(string $voucherCode): void
    {
        $keys = [
            "redeem.{$voucherCode}.mobile",
            "redeem.{$voucherCode}.country",
            "redeem.{$voucherCode}.wallet",
            "redeem.{$voucherCode}.bank_code",
            "redeem.{$voucherCode}.account_number",
            "redeem.{$voucherCode}.inputs",
            "redeem.{$voucherCode}.signature",
            "redeem.{$voucherCode}.plugins",
        ];

        Session::forget($keys);

        Log::debug('[RedeemController] Cleared redemption session', [
            'voucher' => $voucherCode,
        ]);
    }
}
