<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redeem;

use App\Actions\Contact\FetchContactKYCResult;
use App\Actions\Contact\InitiateContactKYC;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Handle KYC verification during voucher redemption.
 */
class KYCRedemptionController extends Controller
{
    /**
     * Initiate KYC verification for the redeemer.
     * 
     * Gets mobile from session, creates/finds contact, generates HyperVerge link,
     * and redirects user to HyperVerge mobile flow.
     */
    public function initiate(Voucher $voucher): RedirectResponse
    {
        // Get mobile from session
        $mobile = Session::get("redeem.{$voucher->code}.mobile");
        $country = Session::get("redeem.{$voucher->code}.country", 'PH');

        if (!$mobile) {
            Log::error('[KYCRedemptionController] No mobile in session', [
                'voucher' => $voucher->code,
            ]);

            return redirect()
                ->route('redeem.wallet', $voucher)
                ->with('error', 'Mobile number is required for KYC verification.');
        }

        // Get or create contact
        $phoneNumber = new PhoneNumber($mobile, $country);
        $contact = Contact::fromPhoneNumber($phoneNumber);

        Log::info('[KYCRedemptionController] Initiating KYC', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'mobile' => $phoneNumber->formatE164(),
        ]);

        // Check if already approved
        if ($contact->isKycApproved()) {
            Log::info('[KYCRedemptionController] KYC already approved', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
            ]);

            return redirect()
                ->route('redeem.confirm', $voucher)
                ->with('success', 'KYC already verified. Proceeding to redemption.');
        }

        // Generate onboarding link
        try {
            InitiateContactKYC::run($contact, $voucher);

            Log::info('[KYCRedemptionController] Redirecting to HyperVerge', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
                'onboarding_url' => $contact->kyc_onboarding_url,
            ]);

            // Redirect to HyperVerge
            return redirect()->away($contact->kyc_onboarding_url);
        } catch (\Exception $e) {
            Log::error('[KYCRedemptionController] Failed to initiate KYC', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('redeem.finalize', $voucher)
                ->with('error', 'Failed to start KYC verification. Please try again.');
        }
    }

    /**
     * Handle callback from HyperVerge after user completes KYC.
     * 
     * This is where HyperVerge redirects the user after they complete
     * the verification process in their mobile app.
     */
    public function callback(Request $request, Voucher $voucher): Response|RedirectResponse
    {
        // Get contact from session mobile
        $mobile = Session::get("redeem.{$voucher->code}.mobile");
        $country = Session::get("redeem.{$voucher->code}.country", 'PH');

        if (!$mobile) {
            Log::error('[KYCRedemptionController] No mobile in session on callback', [
                'voucher' => $voucher->code,
            ]);

            return redirect()
                ->route('redeem.start')
                ->with('error', 'Session expired. Please start redemption again.');
        }

        $phoneNumber = new PhoneNumber($mobile, $country);
        $contact = Contact::fromPhoneNumber($phoneNumber);

        Log::info('[KYCRedemptionController] KYC callback received', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'transaction_id' => $contact->kyc_transaction_id,
        ]);

        // Update status to processing (webhook will update to approved/rejected)
        $contact->update([
            'kyc_status' => 'processing',
            'kyc_submitted_at' => now(),
        ]);

        // Render status page (with polling)
        return Inertia::render('redeem/KYCStatus', [
            'voucher_code' => $voucher->code,
            'contact_id' => $contact->id,
            'transaction_id' => $contact->kyc_transaction_id,
        ]);
    }

    /**
     * Check KYC status for a contact (AJAX polling endpoint).
     * 
     * Fetches latest results from HyperVerge and returns current status.
     */
    public function status(Request $request, Voucher $voucher): JsonResponse
    {
        // Get contact from session
        $mobile = Session::get("redeem.{$voucher->code}.mobile");
        $country = Session::get("redeem.{$voucher->code}.country", 'PH');

        if (!$mobile) {
            return response()->json([
                'status' => null,
                'error' => 'Session expired',
            ], 400);
        }

        $phoneNumber = new PhoneNumber($mobile, $country);
        $contact = Contact::fromPhoneNumber($phoneNumber);

        // Fetch latest results if still processing
        if (in_array($contact->kyc_status, ['pending', 'processing'])) {
            try {
                FetchContactKYCResult::run($contact);
                $contact->refresh();
            } catch (\Exception $e) {
                // Still processing, return current status
                Log::debug('[KYCRedemptionController] Results not ready yet', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::debug('[KYCRedemptionController] Status check', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'status' => $contact->kyc_status,
        ]);

        return response()->json([
            'status' => $contact->kyc_status,
            'transaction_id' => $contact->kyc_transaction_id,
            'completed_at' => $contact->kyc_completed_at?->toIso8601String(),
            'rejection_reasons' => $contact->kyc_rejection_reasons,
        ]);
    }
}
