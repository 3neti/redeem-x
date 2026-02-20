<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use App\Actions\Api\Redemption\RedeemViaSms;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Multi-step flow for voucher redemption.
 *
 * Steps:
 * 1. promptCode - Ask for voucher code
 * 2. validateCode - Validate and show voucher details
 * 3. promptMobile - Ask for payout mobile number
 * 4. confirm - Confirm redemption details
 * 5. finalize - Execute redemption
 */
class RedeemFlow extends BaseFlow
{
    public function initialStep(): string
    {
        return 'promptCode';
    }

    public function steps(): array
    {
        return ['promptCode', 'promptMobile', 'confirm', 'finalize'];
    }

    // Step 1: Prompt for voucher code
    protected function promptPromptCode(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::html(
            "💳 <b>Enter Pay Code:</b>"
        );
    }

    protected function handlePromptCode(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Handle exit request
        if (strtolower($input) === 'exit') {
            return $this->complete(
                NormalizedResponse::text("👋 Exited. Send /redeem to try again.")
            );
        }

        // Validate code format
        if (! preg_match('/^[A-Z0-9-]{4,20}$/i', $input)) {
            return [
                'response' => $this->validationError('Invalid voucher code format. Please try again.'),
                'state' => $state,
            ];
        }

        // Find voucher
        $voucher = Voucher::where('code', strtoupper($input))->first();

        if (! $voucher) {
            return [
                'response' => $this->validationError('Voucher not found. Please check the code and try again.'),
                'state' => $state,
            ];
        }

        // Validate voucher status
        if ($voucher->isRedeemed()) {
            return $this->retryableError('This voucher has already been redeemed.', $state);
        }

        if ($voucher->isExpired()) {
            return $this->retryableError('This voucher has expired.', $state);
        }

        // Check voucher type - only REDEEMABLE supported
        if ($voucher->voucher_type !== VoucherType::REDEEMABLE) {
            return $this->retryableError(
                "This voucher type ({$voucher->voucher_type->value}) cannot be redeemed via bot.",
                $state
            );
        }

        // Check if voucher requires user interaction (inputs/validation)
        if ($this->requiresUserInteraction($voucher)) {
            $url = config('app.url')."/redeem?code={$voucher->code}";

            return [
                'response' => NormalizedResponse::html(
                    "⚠️ <b>Web Redemption Required</b>\n\n".
                    "This voucher requires additional information.\n".
                    "Please redeem at:\n<code>{$url}</code>\n\n".
                    "Enter another code or tap Exit."
                )->withInlineButtons([
                    ['text' => '🚪 Exit', 'callback_data' => 'exit'],
                ]),
                'state' => $state,
            ];
        }

        // Get display amount from instructions (stored as float in instructions)
        $amount = $voucher->instructions->cash->amount ?? 0;

        // Store voucher info
        $newState = $state
            ->set('voucher_code', $voucher->code)
            ->set('voucher_amount', $amount);

        // Check for cached phone number (returning user)
        $cachedPhone = $this->getCachedPhone($update->chatId);

        if ($cachedPhone) {
            // Skip to confirmation with cached phone
            $newState = $newState
                ->set('mobile', $cachedPhone)
                ->advanceTo('confirm');

            return [
                'response' => $this->promptConfirmWithCachedPhone($newState),
                'state' => $newState,
            ];
        }

        // First-time user - ask for phone
        $newState = $newState->advanceTo('promptMobile');

        return [
            'response' => $this->promptPromptMobile($newState),
            'state' => $newState,
        ];
    }

    // Step 2: Prompt for mobile number (combined with voucher found message)
    protected function promptPromptMobile(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));

        return NormalizedResponse::html(
            "✅ <b>{$amount}</b> found!\n\n".
            "We need your mobile number to send the funds."
        )->withContactRequest();
    }

    protected function handlePromptMobile(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Check if user shared their contact (phone number from Telegram)
        if ($update->hasPhoneNumber()) {
            $mobile = $this->normalizePhone($update->phoneNumber);

            $this->log('info', 'Received phone from contact share', [
                'phone' => $mobile,
            ]);

            return $this->advanceToConfirm($state, $mobile);
        }

        // Handle manual text input
        $mobile = preg_replace('/[^0-9+]/', '', $input);

        if (! preg_match('/^(\+?63|0)?9\d{9}$/', $mobile)) {
            return [
                'response' => $this->validationError('Invalid mobile number. Please enter a valid PH mobile number or tap the Share button.'),
                'state' => $state,
            ];
        }

        $mobile = $this->normalizePhone($mobile);

        return $this->advanceToConfirm($state, $mobile);
    }

    /**
     * Normalize phone to +63 format.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '+63'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '+')) {
            return '+'.$phone;
        }

        return $phone;
    }

    /**
     * Advance to confirmation step with mobile number.
     */
    protected function advanceToConfirm(ConversationState $state, string $mobile): array
    {
        $newState = $state
            ->set('mobile', $mobile)
            ->advanceTo('confirm');

        return [
            'response' => $this->promptConfirm($newState),
            'state' => $newState,
        ];
    }

    // Step 3: Confirm redemption with inline buttons
    protected function promptConfirm(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));
        $mobile = $state->get('mobile');
        $bankName = $this->getBankName($mobile);
        $displayMobile = $this->formatMobileForDisplay($mobile);

        return NormalizedResponse::html(
            "📋 You will receive:\n\n".
            "<b>{$amount}</b> → <b>{$bankName}:{$displayMobile}</b>"
        )->withInlineButtons([
            ['text' => '✅ Accept', 'callback_data' => 'accept'],
            ['text' => '✏️ Change Account', 'callback_data' => 'change'],
        ]);
    }

    /**
     * Confirm prompt for returning users (with cached phone).
     * Shows a "Use Different Number" option instead of "Change Account".
     */
    protected function promptConfirmWithCachedPhone(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));
        $mobile = $state->get('mobile');
        $bankName = $this->getBankName($mobile);
        $displayMobile = $this->formatMobileForDisplay($mobile);

        return NormalizedResponse::html(
            "✅ <b>{$amount}</b> found!\n\n".
            "Send to <b>{$bankName}:{$displayMobile}</b>?"
        )->withInlineButtons([
            ['text' => '✅ Accept', 'callback_data' => 'accept'],
            ['text' => '📱 Different Number', 'callback_data' => 'change'],
        ]);
    }

    protected function handleConfirm(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        // Handle inline button callbacks or text responses
        if (in_array($response, ['change', 'no', 'n', 'cancel'])) {
            // User wants to change account - go back to mobile prompt
            $newState = $state->advanceTo('promptMobile');

            return [
                'response' => NormalizedResponse::html(
                    "✏️ Enter your mobile number:"
                )->withContactRequest(),
                'state' => $newState,
            ];
        }

        if (! in_array($response, ['accept', 'yes', 'y', 'confirm'])) {
            return [
                'response' => NormalizedResponse::html(
                    "Please tap <b>Accept</b> or <b>Change Account</b>."
                )->withInlineButtons([
                    ['text' => '✅ Accept', 'callback_data' => 'accept'],
                    ['text' => '✏️ Change Account', 'callback_data' => 'change'],
                ]),
                'state' => $state,
            ];
        }

        // Advance to finalize
        $newState = $state->advanceTo('finalize');

        return $this->handleFinalize($update, $newState, $input);
    }

    // Step 4: Execute redemption
    protected function handleFinalize(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $voucherCode = $state->get('voucher_code');
        $mobile = $state->get('mobile');

        $this->log('info', 'Executing redemption', [
            'code' => $voucherCode,
            'mobile' => $mobile,
            'platform' => $update->platform->value,
        ]);

        try {
            // Re-validate voucher before redemption
            $voucher = Voucher::where('code', $voucherCode)->first();

            if (! $voucher || $voucher->isRedeemed()) {
                return $this->complete(
                    NormalizedResponse::text('❌ Voucher is no longer available for redemption.')
                );
            }

            // Call the redemption action
            $actionRequest = ActionRequest::create('', 'POST', [
                'voucher_code' => $voucherCode,
                'mobile' => $mobile,
                'bank_spec' => null, // Will use default (GCASH)
            ]);

            $response = app(RedeemViaSms::class)->asController($actionRequest);
            $result = json_decode($response->getContent(), true);

            if (! ($result['success'] ?? false)) {
                $errorMessage = $result['message'] ?? 'Redemption failed';

                // Handle specific error cases
                if (($result['error'] ?? '') === 'requires_web') {
                    return $this->complete(
                        NormalizedResponse::html(
                            "⚠️ <b>Web Redemption Required</b>\n\n".
                            "This voucher needs additional information.\n".
                            "Please redeem at:\n".
                            "<code>{$result['redemption_url']}</code>"
                        )
                    );
                }

                return $this->complete(
                    NormalizedResponse::text("❌ {$errorMessage}")
                );
            }

            // Success!
            $voucherData = $result['data']['voucher'] ?? [];
            $amount = $this->formatMoney($voucherData['amount'] ?? $state->get('voucher_amount'));
            $bankName = $this->getBankName($mobile);
            $displayMobile = $this->formatMobileForDisplay($mobile);

            // Cache phone number for future redemptions (30 days)
            $this->cachePhone($update->chatId, $mobile);

            Log::info('[RedeemFlow] Redemption successful via messaging', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'platform' => $update->platform->value,
                'chat_id' => $update->chatId,
            ]);

            return $this->complete(
                NormalizedResponse::html(
                    "🎉 <b>Done!</b>\n\n".
                    "{$amount} is on the way to your {$bankName}.\n".
                    "Account: {$displayMobile}"
                )
            );

        } catch (\Throwable $e) {
            Log::error('[RedeemFlow] Redemption failed', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Show detailed error in non-production
            $errorMessage = "❌ Redemption failed. Please try again later.\n\n";
            
            if (! app()->isProduction()) {
                $errorMessage .= "Error: {$e->getMessage()}\n";
                $errorMessage .= "File: {$e->getFile()}:{$e->getLine()}\n\n";
            }
            
            $errorMessage .= "If the problem persists, contact support.";

            return $this->complete(
                NormalizedResponse::text($errorMessage)
            );
        }
    }

    /**
     * Check if voucher requires user interaction.
     *
     * Returns true if:
     * - Voucher has input fields to collect
     * - Voucher has secret validation
     * - Voucher has location validation
     */
    protected function requiresUserInteraction(Voucher $voucher): bool
    {
        $hasInputs = ! empty($voucher->instructions->inputs->fields ?? []);
        $hasValidation = ($voucher->instructions->cash->validation->secret ?? false)
                      || ($voucher->instructions->cash->validation->location ?? false);

        return $hasInputs || $hasValidation;
    }

    /**
     * Format amount as money.
     */
    protected function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            return Money::of($amount, $currency)->formatTo('en_PH');
        } catch (\Throwable $e) {
            return '₱'.number_format($amount, 2);
        }
    }

    /**
     * Get friendly bank name from mobile number.
     *
     * For PH mobile numbers, default to GCash (most common e-wallet).
     */
    protected function getBankName(string $mobile): string
    {
        // In the future, we could look up the user's preferred bank
        // For now, default to GCash for PH mobile numbers
        return 'GCash';
    }

    /**
     * Format mobile number for display (e.g., 0917xxxxxxx format).
     */
    protected function formatMobileForDisplay(string $mobile): string
    {
        // Remove + prefix and convert +63 to 0 for PH numbers
        $mobile = ltrim($mobile, '+');

        if (str_starts_with($mobile, '63')) {
            return '0'.substr($mobile, 2);
        }

        return $mobile;
    }

    /**
     * Return a retryable error that keeps the user in the flow.
     */
    protected function retryableError(string $message, ConversationState $state): array
    {
        return [
            'response' => NormalizedResponse::html(
                "❌ {$message}\n\n".
                "Enter another code or tap Exit."
            )->withInlineButtons([
                ['text' => '🚪 Exit', 'callback_data' => 'exit'],
            ]),
            'state' => $state,
        ];
    }

    /**
     * Get cached phone number for a chat.
     */
    protected function getCachedPhone(string $chatId): ?string
    {
        return Cache::get($this->phoneCacheKey($chatId));
    }

    /**
     * Cache phone number for a chat.
     */
    protected function cachePhone(string $chatId, string $phone): void
    {
        Cache::put($this->phoneCacheKey($chatId), $phone, now()->addDays(30));
    }

    /**
     * Get cache key for phone number.
     */
    protected function phoneCacheKey(string $chatId): string
    {
        return "messaging:phone:{$chatId}";
    }
}
