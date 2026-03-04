<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use Brick\Money\Money;
use Illuminate\Support\Number;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

/**
 * Quick single-voucher generation flow (admin only).
 *
 * A simplified version of GenerateFlow for quick disbursements.
 *
 * Steps:
 * 1. promptAmount - Ask for amount
 * 2. promptMemo - Ask for memo (optional)
 * 3. confirm - Confirm and generate
 */
class DisburseFlow extends BaseFlow
{
    public function requiresAdmin(): bool
    {
        return true;
    }

    public function initialStep(): string
    {
        return 'promptAmount';
    }

    public function steps(): array
    {
        return ['promptAmount', 'promptMemo', 'confirm', 'finalize'];
    }

    // Step 1: Prompt for amount
    protected function promptPromptAmount(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::text(
            "💸 *Quick Disburse*\n\n".
            "Enter the amount to disburse:\n\n".
            "Send /cancel to exit."
        );
    }

    protected function handlePromptAmount(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $amount = filter_var($input, FILTER_VALIDATE_FLOAT);

        if ($amount === false || $amount <= 0) {
            return [
                'response' => $this->validationError('Invalid amount. Please enter a positive number.'),
                'state' => $state,
            ];
        }

        if ($amount > 50000) {
            return [
                'response' => $this->validationError('Amount exceeds maximum limit (₱50,000).'),
                'state' => $state,
            ];
        }

        $newState = $state
            ->set('amount', $amount)
            ->advanceTo('promptMemo');

        return [
            'response' => $this->promptPromptMemo($newState),
            'state' => $newState,
        ];
    }

    // Step 2: Prompt for memo (optional)
    protected function promptPromptMemo(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('amount'));

        return NormalizedResponse::text(
            "Amount: *{$amount}*\n\n".
            "Add a memo/note? (optional)\n\n".
            "Send SKIP to continue without memo."
        );
    }

    protected function handlePromptMemo(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $memo = $this->isSkipCommand($input) ? null : trim($input);

        $newState = $state
            ->set('memo', $memo)
            ->advanceTo('confirm');

        return [
            'response' => $this->promptConfirm($newState),
            'state' => $newState,
        ];
    }

    // Step 3: Confirm
    protected function promptConfirm(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('amount'));
        $memo = $state->get('memo') ?? 'None';

        return NormalizedResponse::text(
            "📋 *Confirm Disbursement*\n\n".
            "Amount: *{$amount}*\n".
            "Memo: *{$memo}*\n\n".
            "Send *YES* to create voucher or *NO* to cancel."
        );
    }

    protected function handleConfirm(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        if (in_array($response, ['no', 'n', 'cancel'])) {
            return $this->complete(
                NormalizedResponse::text("❌ Disbursement cancelled.\n\nSend /help to see available commands.")
            );
        }

        if (! in_array($response, ['yes', 'y', 'confirm'])) {
            return [
                'response' => $this->validationError("Please send YES to confirm or NO to cancel."),
                'state' => $state,
            ];
        }

        $newState = $state->advanceTo('finalize');

        return $this->handleFinalize($update, $newState, $input);
    }

    // Step 4: Generate voucher
    protected function handleFinalize(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $this->log('info', 'Creating disbursement voucher', [
            'amount' => $state->get('amount'),
            'memo' => $state->get('memo'),
        ]);

        try {
            $instructions = VoucherInstructionsData::from([
                'cash' => [
                    'amount' => $state->get('amount'),
                    'currency' => Number::defaultCurrency(),
                ],
                'count' => 1,
                'voucher_type' => null,
                'rider' => [
                    'message' => $state->get('memo'),
                ],
            ]);

            $vouchers = GenerateVouchers::run($instructions);
            $voucher = $vouchers->first();

            $amount = $this->formatMoney($state->get('amount'));
            $memo = $state->get('memo') ? "\nMemo: {$state->get('memo')}" : '';

            return $this->complete(
                NormalizedResponse::text(
                    "✅ *Voucher Created!*\n\n".
                    "Code: `{$voucher->code}`\n".
                    "Amount: *{$amount}*{$memo}\n\n".
                    "Share this code with the recipient.\n".
                    "They can redeem it via /redeem or our website. 🎉"
                )
            );

        } catch (\Throwable $e) {
            $this->log('error', 'Disbursement failed', ['error' => $e->getMessage()]);

            return $this->complete(
                NormalizedResponse::text(
                    "❌ Failed to create voucher: {$e->getMessage()}\n\n".
                    "Please try again or contact support."
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
