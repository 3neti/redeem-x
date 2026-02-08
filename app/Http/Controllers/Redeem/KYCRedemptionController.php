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
     * Gets mobile from request or session, creates/finds contact, generates HyperVerge link,
     * and redirects user to HyperVerge mobile flow.
     */
    public function initiate(Request $request, Voucher $voucher): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        // Get mobile from request query params (passed from frontend) or session
        $mobile = $request->query('mobile') ?? Session::get("redeem.{$voucher->code}.mobile");
        $country = $request->query('country') ?? Session::get("redeem.{$voucher->code}.country", 'PH');

        if (! $mobile) {
            Log::error('[KYCRedemptionController] No mobile provided', [
                'voucher' => $voucher->code,
                'has_request_mobile' => $request->has('mobile'),
                'has_session_mobile' => Session::has("redeem.{$voucher->code}.mobile"),
            ]);

            return redirect()
                ->route('redeem.wallet', $voucher)
                ->with('error', 'Mobile number is required for KYC verification.');
        }

        // Store in session for callback/status endpoints
        Session::put("redeem.{$voucher->code}.mobile", $mobile);
        Session::put("redeem.{$voucher->code}.country", $country);

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
                ->route('redeem.finalize', $voucher)
                ->with('success', 'Identity already verified!');
        }

        // Generate onboarding link
        try {
            InitiateContactKYC::run($contact, $voucher);

            Log::info('[KYCRedemptionController] Redirecting to HyperVerge', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
                'onboarding_url' => $contact->kyc_onboarding_url,
            ]);

            // Use Inertia location for external redirect (prevents CORS)
            return Inertia::location($contact->kyc_onboarding_url);
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

        if (! $mobile) {
            Log::error('[KYCRedemptionController] No mobile in session on callback', [
                'voucher' => $voucher->code,
            ]);

            return redirect()
                ->route('redeem.start')
                ->with('error', 'Session expired. Please start redemption again.');
        }

        $phoneNumber = new PhoneNumber($mobile, $country);
        $contact = Contact::fromPhoneNumber($phoneNumber);

        // Check callback status from HyperVerge
        $callbackStatus = $request->query('status');
        $transactionId = $request->query('transactionId');

        Log::info('[KYCRedemptionController] KYC callback received', [
            'voucher' => $voucher->code,
            'contact_id' => $contact->id,
            'transaction_id' => $transactionId,
            'callback_status' => $callbackStatus,
        ]);

        // Handle user cancellation
        if ($callbackStatus === 'user_cancelled') {
            Log::warning('[KYCRedemptionController] User cancelled KYC', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
            ]);

            return redirect()
                ->route('redeem.start', ['code' => $voucher->code])
                ->with('error', 'Identity verification was cancelled. Please try again when ready.');
        }

        // Handle auto-approval (instant approval from HyperVerge)
        if ($callbackStatus === 'auto_approved') {
            Log::info('[KYCRedemptionController] KYC auto-approved', [
                'voucher' => $voucher->code,
                'contact_id' => $contact->id,
                'transaction_id' => $transactionId,
            ]);

            $contact->update([
                'kyc_status' => 'approved',
                'kyc_submitted_at' => now(),
                'kyc_completed_at' => now(),
            ]);

            // Redirect directly to finalize (KYC complete)
            return redirect()
                ->route('redeem.finalize', $voucher)
                ->with('success', 'Identity verified successfully!');
        }

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

        if (! $mobile) {
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
            } catch (\TypeError $e) {
                // HyperVerge API returned unexpected format
                Log::warning('[KYCRedemptionController] KYC result validation failed', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
                // Keep status as processing, will retry on next poll
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
