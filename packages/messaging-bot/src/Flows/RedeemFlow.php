<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use App\Actions\Api\Redemption\RedeemViaSms;
use Brick\Money\Money;
use Illuminate\Support\Facades\Log;
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
            "💳 <b>Redeem Voucher</b>\n\n".
            "Please send the voucher code you want to redeem.\n\n".
            "Send /cancel to exit."
        );
    }

    protected function handlePromptCode(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
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
            return [
                'response' => NormalizedResponse::text('❌ This voucher has already been redeemed.'),
                'state' => null,
            ];
        }

        if ($voucher->isExpired()) {
            return [
                'response' => NormalizedResponse::text('❌ This voucher has expired.'),
                'state' => null,
            ];
        }

        // Check voucher type
        if ($voucher->voucher_type !== VoucherType::REDEEMABLE) {
            return [
                'response' => NormalizedResponse::text(
                    "⚠️ This voucher type ({$voucher->voucher_type->value}) cannot be redeemed via bot.\n\n".
                    'Please use the web interface to process this voucher.'
                ),
                'state' => null,
            ];
        }

        // Store voucher info and advance
        $newState = $state
            ->set('voucher_code', $voucher->code)
            ->set('voucher_amount', $voucher->cash->amount)
            ->advanceTo('promptMobile');

        return [
            'response' => $this->promptPromptMobile($newState),
            'state' => $newState,
        ];
    }

    // Step 2: Prompt for mobile number
    protected function promptPromptMobile(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));

        return NormalizedResponse::html(
            "✅ Voucher found!\n\n".
            "Code: <b>{$state->get('voucher_code')}</b>\n".
            "Amount: <b>{$amount}</b>\n\n".
            "Please send the mobile number for payout (e.g., 09171234567)"
        );
    }

    protected function handlePromptMobile(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Normalize and validate mobile
        $mobile = preg_replace('/[^0-9+]/', '', $input);

        if (! preg_match('/^(\+?63|0)?9\d{9}$/', $mobile)) {
            return [
                'response' => $this->validationError('Invalid mobile number. Please enter a valid PH mobile number.'),
                'state' => $state,
            ];
        }

        // Normalize to +63 format
        if (str_starts_with($mobile, '0')) {
            $mobile = '+63'.substr($mobile, 1);
        } elseif (! str_starts_with($mobile, '+')) {
            $mobile = '+'.$mobile;
        }

        // Store mobile and advance to confirm
        $newState = $state
            ->set('mobile', $mobile)
            ->advanceTo('confirm');

        return [
            'response' => $this->promptConfirm($newState),
            'state' => $newState,
        ];
    }

    // Step 3: Confirm redemption
    protected function promptConfirm(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('voucher_amount'));

        return NormalizedResponse::html(
            "📋 <b>Confirm Redemption</b>\n\n".
            "Voucher: <b>{$state->get('voucher_code')}</b>\n".
            "Amount: <b>{$amount}</b>\n".
            "Payout to: <b>{$state->get('mobile')}</b>\n\n".
            "Send <b>YES</b> to confirm or <b>NO</b> to cancel."
        );
    }

    protected function handleConfirm(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        if (in_array($response, ['no', 'n', 'cancel'])) {
            return $this->complete(
                NormalizedResponse::text("❌ Redemption cancelled.\n\nSend /help to see available commands.")
            );
        }

        if (! in_array($response, ['yes', 'y', 'confirm'])) {
            return [
                'response' => $this->validationError("Please send YES to confirm or NO to cancel."),
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

            // Call the redemption action via HTTP simulation
            // This reuses the existing SMS redemption logic
            request()->merge([
                'voucher_code' => $voucherCode,
                'mobile' => $mobile,
                'bank_spec' => null, // Will use default (GCASH)
            ]);

            $response = app(RedeemViaSms::class)->asController(request());
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
            $bankAccount = $result['data']['bank_account'] ?? 'your account';

            Log::info('[RedeemFlow] Redemption successful via messaging', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'platform' => $update->platform->value,
                'chat_id' => $update->chatId,
            ]);

            return $this->complete(
                NormalizedResponse::html(
                    "✅ <b>Redemption Successful!</b>\n\n".
                    "Amount: <b>{$amount}</b>\n".
                    "Sent to: <b>{$bankAccount}</b>\n\n".
                    "The funds will be transferred shortly.\n\n".
                    "Thank you for using PayCode! 🎉"
                )
            );

        } catch (\Throwable $e) {
            Log::error('[RedeemFlow] Redemption failed', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return $this->complete(
                NormalizedResponse::text(
                    "❌ Redemption failed. Please try again later.\n\n".
                    "If the problem persists, contact support."
                )
            );
        }
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
}
