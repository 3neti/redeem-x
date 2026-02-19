<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use Brick\Money\Money;
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
        return NormalizedResponse::text(
            "💳 *Redeem Voucher*\n\n".
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
            ->with('voucher_code', $voucher->code)
            ->with('voucher_amount', $voucher->cash->amount)
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

        return NormalizedResponse::text(
            "✅ Voucher found!\n\n".
            "Code: *{$state->get('voucher_code')}*\n".
            "Amount: *{$amount}*\n\n".
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
            ->with('mobile', $mobile)
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

        return NormalizedResponse::text(
            "📋 *Confirm Redemption*\n\n".
            "Voucher: *{$state->get('voucher_code')}*\n".
            "Amount: *{$amount}*\n".
            "Payout to: *{$state->get('mobile')}*\n\n".
            "Send *YES* to confirm or *NO* to cancel."
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
        $this->log('info', 'Executing redemption', [
            'code' => $state->get('voucher_code'),
            'mobile' => $state->get('mobile'),
        ]);

        try {
            // Call the redemption action
            // In a real implementation, this would call RedeemViaSms or similar action
            $voucher = Voucher::where('code', $state->get('voucher_code'))->first();

            if (! $voucher || $voucher->isRedeemed()) {
                return $this->complete(
                    NormalizedResponse::text('❌ Voucher is no longer available for redemption.')
                );
            }

            // TODO: Call actual redemption action
            // $result = RedeemViaSms::run($voucher, $state->get('mobile'));

            $amount = $this->formatMoney($state->get('voucher_amount'));

            return $this->complete(
                NormalizedResponse::text(
                    "✅ *Redemption Successful!*\n\n".
                    "Amount: *{$amount}*\n".
                    "Sent to: *{$state->get('mobile')}*\n\n".
                    "The funds will be transferred shortly.\n\n".
                    "Thank you for using PayCode! 🎉"
                )
            );

        } catch (\Throwable $e) {
            $this->log('error', 'Redemption failed', ['error' => $e->getMessage()]);

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
