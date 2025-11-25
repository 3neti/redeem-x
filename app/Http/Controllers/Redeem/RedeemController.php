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
use LBHurtado\MoneyIssuer\Support\BankRegistry;

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
        // Get code from query string
        $code = request()->query('code');

        // Only validate if this is an Inertia request (form submission)
        // to avoid redirect loop when visiting /redeem?code=XXX directly
        if ($code && request()->header('X-Inertia')) {
            $code = strtoupper(trim($code));

            try {
                $voucher = Voucher::where('code', $code)->firstOrFail();

                // Check if voucher can be redeemed
                if ($voucher->isRedeemed()) {
                    return redirect()->route('redeem.start')
                        ->withInput(['code' => $code])
                        ->withErrors([
                            'code' => 'This voucher has already been redeemed.',
                        ]);
                }

                if ($voucher->isExpired()) {
                    return redirect()->route('redeem.start')
                        ->withInput(['code' => $code])
                        ->withErrors([
                            'code' => 'This voucher has expired.',
                        ]);
                }

                if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
                    return redirect()->route('redeem.start')
                        ->withInput(['code' => $code])
                        ->withErrors([
                            'code' => 'This voucher is not yet active.',
                        ]);
                }

                // Valid voucher, redirect to wallet
                return redirect()->route('redeem.wallet', ['voucher' => $voucher->code]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return redirect()->route('redeem.start')
                    ->withInput(['code' => $code])
                    ->withErrors([
                        'code' => 'Invalid voucher code.',
                    ]);
            }
        }

        return Inertia::render('redeem/Start', [
            'initial_code' => old('code', $code),
        ]);
    }

    /**
     * Show the wallet page (API-first flow).
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function wallet(Voucher $voucher): Response
    {
        // Load banks from BankRegistry
        // TODO: Implement PesoNet settlement rail support in addition to InstaPay
        $bankRegistry = new BankRegistry();
        $allowedRails = config('redeem.bank_select.allowed_settlement_rails', ['INSTAPAY']);

        $banks = collect($bankRegistry->all())
            ->filter(function ($bank) use ($allowedRails) {
                // If no rails specified, include all banks
                if (empty($allowedRails)) {
                    return true;
                }

                // Check if bank has any of the allowed settlement rails
                $bankRails = array_keys($bank['settlement_rail'] ?? []);
                return !empty(array_intersect($bankRails, $allowedRails));
            })
            ->map(fn($bank, $code) => [
                'code' => $code,
                'name' => $bank['full_name'] ?? $code,
            ])
            ->unique('code') // Remove duplicates by code
            ->values()
            ->toArray();

        return Inertia::render('redeem/Wallet', [
            'voucher_code' => $voucher->code,
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $voucher->amount,
                'currency' => $voucher->currency,
                'created_at' => $voucher->created_at?->toIso8601String(),
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'owner' => $voucher->owner ? [
                    'name' => $voucher->owner->name,
                    'email' => $voucher->owner->email,
                ] : null,
                'count' => $voucher->instructions->count ?? null,
            ],
            'banks' => $banks,
            'config' => array_merge(
                config('redeem.wallet'),
                [
                    'bank_select' => config('redeem.bank_select'),
                    'widget' => config('redeem.widget'),
                ]
            ),
        ]);
    }

    /**
     * Show the inputs page (API-first flow).
     * Collects email, birthdate, name, and other text inputs.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function inputs(Voucher $voucher): Response
    {
        return Inertia::render('redeem/Inputs', [
            'voucher_code' => $voucher->code,
        ]);
    }

    /**
     * Show the location page (API-first flow).
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function location(Voucher $voucher): Response
    {
        return Inertia::render('redeem/Location', [
            'voucher_code' => $voucher->code,
        ]);
    }

    /**
     * Show the selfie page (API-first flow).
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function selfie(Voucher $voucher): Response
    {
        return Inertia::render('redeem/Selfie', [
            'voucher_code' => $voucher->code,
            'image_config' => config('model-input.image_quality.selfie'),
        ]);
    }

    /**
     * Show the signature page (API-first flow).
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function signature(Voucher $voucher): Response
    {
        return Inertia::render('redeem/Signature', [
            'voucher_code' => $voucher->code,
            'image_config' => config('model-input.image_quality.signature'),
        ]);
    }

    /**
     * Store redemption session data (called from frontend before navigation).
     * This ensures session data is available for KYC and other backend operations.
     *
     * @param  Voucher  $voucher
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeSession(Voucher $voucher): \Illuminate\Http\JsonResponse
    {
        $data = request()->validate([
            'mobile' => 'required|string',
            'country' => 'required|string',
            'secret' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'account_number' => 'nullable|string',
            'inputs' => 'nullable|array',
        ]);

        Log::debug('[RedeemController] Storing session data', [
            'voucher' => $voucher->code,
            'mobile' => $data['mobile'],
            'has_inputs' => !empty($data['inputs']),
        ]);

        // Store in Laravel session
        Session::put("redeem.{$voucher->code}.mobile", $data['mobile']);
        Session::put("redeem.{$voucher->code}.country", $data['country']);
        
        if (!empty($data['secret'])) {
            Session::put("redeem.{$voucher->code}.secret", $data['secret']);
        }
        
        if (!empty($data['bank_code'])) {
            Session::put("redeem.{$voucher->code}.bank_code", $data['bank_code']);
        }
        
        if (!empty($data['account_number'])) {
            Session::put("redeem.{$voucher->code}.account_number", $data['account_number']);
        }
        
        if (!empty($data['inputs'])) {
            Session::put("redeem.{$voucher->code}.inputs", $data['inputs']);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Show the finalize page.
     * This page displays a summary of all collected data before final confirmation.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function finalize(Voucher $voucher): Response
    {
        Log::info('[RedeemController] finalize method called', [
            'voucher' => $voucher->code,
        ]);
        
        // Check if KYC is required
        $inputFields = array_map(
            fn($field) => $field->value ?? $field, 
            $voucher->instructions->inputs->fields ?? []
        );
        $kycRequired = in_array('kyc', $inputFields);
        
        Log::info('[RedeemController] finalize - KYC required check', [
            'voucher' => $voucher->code,
            'kyc_required' => $kycRequired,
            'inputs_fields' => $inputFields,
        ]);

        $kycStatus = null;
        if ($kycRequired) {
            $mobile = Session::get("redeem.{$voucher->code}.mobile");
            $country = Session::get("redeem.{$voucher->code}.country", 'PH');

            if ($mobile) {
                $phoneNumber = new PhoneNumber($mobile, $country);
                $contact = Contact::fromPhoneNumber($phoneNumber);

                Log::debug('[RedeemController] finalize - KYC check', [
                    'voucher' => $voucher->code,
                    'contact_id' => $contact->id,
                    'kyc_status' => $contact->kyc_status,
                    'is_approved' => $contact->isKycApproved(),
                ]);

                $kycStatus = [
                    'required' => true,
                    'completed' => $contact->isKycApproved(),
                    'status' => $contact->kyc_status,
                ];
            } else {
                Log::warning('[RedeemController] finalize - No mobile in session for KYC check', [
                    'voucher' => $voucher->code,
                ]);

                $kycStatus = [
                    'required' => true,
                    'completed' => false,
                    'status' => null,
                ];
            }
        }

        return Inertia::render('redeem/Finalize', [
            'voucher_code' => $voucher->code,
            'config' => config('redeem.finalize'),
            'kyc' => $kycStatus,
        ]);
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
        } catch (\App\Exceptions\VoucherNotProcessedException $e) {
            // Voucher still being processed - return 425 with retry guidance
            Log::info('[RedeemController] Voucher not yet processed', [
                'voucher' => $voucherCode,
                'retry_after' => 3,
            ]);

            return redirect()
                ->route('redeem.finalize', $voucher)
                ->with('voucher_processing', true)
                ->with('error', $e->getMessage());
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

            return Inertia::render('redeem/Error', [
                'message' => 'This voucher has not been redeemed yet.',
            ]);
        }

        Log::info('[RedeemController] Showing success page', [
            'voucher' => $voucher->code,
        ]);

        return Inertia::render('redeem/Success', [
            'voucher' => VoucherData::fromModel($voucher),
            'mobile' => $voucher->contact?->mobile ?? null,
            'rider' => [
                'message' => $voucher->instructions->rider->message ?? null,
                'url' => $voucher->instructions->rider->url ?? null,
            ],
            'config' => config('redeem.success'),
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
