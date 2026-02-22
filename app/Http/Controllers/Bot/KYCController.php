<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bot;

use App\Actions\Contact\FetchContactKYCResult;
use App\Actions\Contact\InitiateContactKYC;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Controller for Telegram Bot KYC verification flow.
 *
 * This controller handles the KYC initiation and callback for bot users.
 * Unlike the web flow, the bot flow uses cache as a bridge between
 * the HyperVerge callback session and the Telegram bot session.
 */
class KYCController extends Controller
{
    /**
     * Cache key prefix for storing KYC state.
     */
    protected const CACHE_PREFIX = 'bot:kyc:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Initiate KYC verification for a bot user.
     *
     * This is called when user taps "Verify Identity" in the bot.
     * Generates HyperVerge onboarding link and stores state in cache.
     */
    public function initiate(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $voucherCode = $request->query('voucher');
        $chatId = $request->query('chat_id');
        $mobile = $request->query('mobile');

        if (! $voucherCode || ! $chatId) {
            return response('Missing required parameters', 400);
        }

        $voucher = Voucher::where('code', strtoupper($voucherCode))->first();

        if (! $voucher) {
            return response('Voucher not found', 404);
        }

        Log::info('[Bot/KYCController] Initiating KYC', [
            'voucher' => $voucherCode,
            'chat_id' => $chatId,
            'mobile' => $mobile,
        ]);

        // Get or create contact from mobile
        $contact = null;
        if ($mobile) {
            try {
                $phoneNumber = new PhoneNumber($mobile, 'PH');
                $contact = Contact::fromPhoneNumber($phoneNumber);
            } catch (\Exception $e) {
                Log::warning('[Bot/KYCController] Failed to create contact from mobile', [
                    'mobile' => $mobile,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check if contact already has approved KYC
        if ($contact && $contact->isKycApproved()) {
            Log::info('[Bot/KYCController] KYC already approved', [
                'contact_id' => $contact->id,
                'chat_id' => $chatId,
            ]);

            // Store approved status in cache for bot to pick up
            $this->storeKycCache($chatId, [
                'voucher_code' => $voucher->code,
                'status' => 'approved',
                'contact_id' => $contact->id,
                'transaction_id' => $contact->kyc_transaction_id,
            ]);

            return Inertia::render('Bot/KYCSuccess', [
                'status' => 'approved',
                'message' => 'Identity already verified!',
            ]);
        }

        // Generate unique transaction ID for this bot session
        $transactionId = "bot_{$chatId}_" . now()->timestamp;

        // Build redirect URL for callback (bot-specific)
        $redirectUrl = route('bot.kyc.callback');

        try {
            // Check if fake mode is enabled
            if (config('hyperverge.use_fake', false)) {
                // In fake mode, redirect directly to callback with auto_approved status
                $onboardingUrl = $redirectUrl . '?transactionId=' . urlencode($transactionId) . '&status=auto_approved';

                Log::info('[Bot/KYCController] 🎭 FAKE MODE - Skipping HyperVerge', [
                    'chat_id' => $chatId,
                    'fake_callback_url' => $onboardingUrl,
                ]);
            } else {
                // Generate real HyperVerge onboarding link
                $onboardingUrl = GenerateOnboardingLink::get(
                    transactionId: $transactionId,
                    redirectUrl: $redirectUrl,
                    options: [
                        'validateWorkflowInputs' => 'no',
                        'allowEmptyWorkflowInputs' => 'yes',
                    ]
                );
            }

            // Store state in cache for callback to find
            $this->storeKycCache($chatId, [
                'voucher_code' => $voucher->code,
                'transaction_id' => $transactionId,
                'status' => 'pending',
                'contact_id' => $contact?->id,
                'mobile' => $mobile,
            ]);

            // Also store reverse mapping: transaction_id -> chat_id
            Cache::put("bot:kyc_tx:{$transactionId}", $chatId, self::CACHE_TTL);

            if ($contact) {
                // Update contact with KYC details (like web flow)
                $contact->update([
                    'kyc_transaction_id' => $transactionId,
                    'kyc_onboarding_url' => $onboardingUrl,
                    'kyc_status' => 'pending',
                ]);
            }

            Log::info('[Bot/KYCController] Redirecting to HyperVerge', [
                'chat_id' => $chatId,
                'transaction_id' => $transactionId,
            ]);

            // Redirect to HyperVerge
            return Inertia::location($onboardingUrl);

        } catch (\Exception $e) {
            Log::error('[Bot/KYCController] Failed to initiate KYC', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('Bot/KYCSuccess', [
                'status' => 'error',
                'message' => 'Failed to start identity verification. Please try again.',
            ]);
        }
    }

    /**
     * Handle callback from HyperVerge after user completes KYC.
     *
     * Updates the cache status so the bot can pick it up when
     * user taps "Continue" in Telegram.
     */
    public function callback(Request $request): Response
    {
        $callbackStatus = $request->query('status');
        $transactionId = $request->query('transactionId');

        Log::info('[Bot/KYCController] KYC callback received', [
            'transaction_id' => $transactionId,
            'status' => $callbackStatus,
        ]);

        // Find chat_id from transaction_id mapping
        $chatId = Cache::get("bot:kyc_tx:{$transactionId}");

        if (! $chatId) {
            Log::error('[Bot/KYCController] No chat_id found for transaction', [
                'transaction_id' => $transactionId,
            ]);

            return Inertia::render('Bot/KYCSuccess', [
                'status' => 'error',
                'message' => 'Session expired. Please start over in Telegram.',
            ]);
        }

        // Get existing cache data
        $cacheData = $this->getKycCache($chatId) ?? [];

        // Handle user cancellation
        if ($callbackStatus === 'user_cancelled') {
            $cacheData['status'] = 'cancelled';
            $this->storeKycCache($chatId, $cacheData);

            return Inertia::render('Bot/KYCSuccess', [
                'status' => 'cancelled',
                'message' => 'Verification cancelled. You can try again in Telegram.',
            ]);
        }

        // Handle auto-approval
        if ($callbackStatus === 'auto_approved') {
            $cacheData['status'] = 'approved';
            $this->storeKycCache($chatId, $cacheData);

            // Update contact if available
            if (! empty($cacheData['contact_id'])) {
                $contact = Contact::find($cacheData['contact_id']);
                if ($contact) {
                    $contact->update([
                        'kyc_status' => 'approved',
                        'kyc_submitted_at' => now(),
                        'kyc_completed_at' => now(),
                    ]);
                }
            }

            Log::info('[Bot/KYCController] KYC auto-approved', [
                'chat_id' => $chatId,
                'transaction_id' => $transactionId,
            ]);

            return Inertia::render('Bot/KYCSuccess', [
                'status' => 'approved',
                'message' => 'Identity verified! Return to Telegram and tap Continue.',
            ]);
        }

        // For other statuses (needs_review, etc.), try to fetch results
        try {
            if (! empty($cacheData['contact_id'])) {
                $contact = Contact::find($cacheData['contact_id']);
                if ($contact) {
                    FetchContactKYCResult::run($contact);
                    $contact->refresh();
                    $cacheData['status'] = $contact->kyc_status;
                }
            } else {
                // No contact yet, mark as processing
                $cacheData['status'] = 'processing';
            }

            $this->storeKycCache($chatId, $cacheData);

        } catch (\Exception $e) {
            Log::debug('[Bot/KYCController] Results not ready yet', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            $cacheData['status'] = 'processing';
            $this->storeKycCache($chatId, $cacheData);
        }

        // Determine message based on status
        $message = match ($cacheData['status']) {
            'approved' => 'Identity verified! Return to Telegram and tap Continue.',
            'rejected' => 'Verification failed. Return to Telegram to try again.',
            'processing' => 'Verification in progress. Return to Telegram and tap Continue.',
            default => 'Return to Telegram and tap Continue to check status.',
        };

        return Inertia::render('Bot/KYCSuccess', [
            'status' => $cacheData['status'],
            'message' => $message,
        ]);
    }

    /**
     * Get KYC status for a chat (used by bot flow).
     */
    public static function getKycStatus(string $chatId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $chatId;

        return Cache::get($cacheKey);
    }

    /**
     * Clear KYC cache for a chat.
     */
    public static function clearKycCache(string $chatId): void
    {
        $cacheKey = self::CACHE_PREFIX . $chatId;
        Cache::forget($cacheKey);

        // Also try to clear transaction mapping if we know it
        $data = Cache::get($cacheKey);
        if (! empty($data['transaction_id'])) {
            Cache::forget("bot:kyc_tx:{$data['transaction_id']}");
        }
    }

    /**
     * Store KYC data in cache.
     */
    protected function storeKycCache(string $chatId, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . $chatId;
        Cache::put($cacheKey, $data, self::CACHE_TTL);
    }

    /**
     * Get KYC data from cache.
     */
    protected function getKycCache(string $chatId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $chatId;

        return Cache::get($cacheKey);
    }
}
