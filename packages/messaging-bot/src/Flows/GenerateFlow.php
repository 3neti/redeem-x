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
 * Multi-step flow for voucher generation (admin only).
 *
 * Steps:
 * 1. promptAmount - Ask for voucher amount
 * 2. promptCount - Ask for number of vouchers
 * 3. promptCampaign - Ask for campaign name (optional)
 * 4. confirm - Confirm generation details
 * 5. finalize - Execute generation
 */
class GenerateFlow extends BaseFlow
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
        return ['promptAmount', 'promptCount', 'promptCampaign', 'confirm', 'finalize'];
    }

    // Step 1: Prompt for amount
    protected function promptPromptAmount(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::text(
            "🎟️ *Generate Vouchers*\n\n".
            "Enter the voucher amount (e.g., 500):\n\n".
            "Send /cancel to exit."
        );
    }

    protected function handlePromptAmount(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        // Parse amount
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
            ->advanceTo('promptCount');

        return [
            'response' => $this->promptPromptCount($newState),
            'state' => $newState,
        ];
    }

    // Step 2: Prompt for count
    protected function promptPromptCount(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('amount'));

        return NormalizedResponse::text(
            "Amount: *{$amount}*\n\n".
            "How many vouchers? (1-100)\n\n".
            "Send SKIP for 1 voucher."
        );
    }

    protected function handlePromptCount(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $count = 1;

        if (! $this->isSkipCommand($input)) {
            $count = filter_var($input, FILTER_VALIDATE_INT);

            if ($count === false || $count < 1 || $count > 100) {
                return [
                    'response' => $this->validationError('Invalid count. Please enter a number between 1 and 100.'),
                    'state' => $state,
                ];
            }
        }

        $newState = $state
            ->set('count', $count)
            ->advanceTo('promptCampaign');

        return [
            'response' => $this->promptPromptCampaign($newState),
            'state' => $newState,
        ];
    }

    // Step 3: Prompt for campaign (optional)
    protected function promptPromptCampaign(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::text(
            "Campaign name? (optional)\n\n".
            "Send SKIP to generate without a campaign."
        );
    }

    protected function handlePromptCampaign(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $campaign = $this->isSkipCommand($input) ? null : trim($input);

        $newState = $state
            ->set('campaign', $campaign)
            ->advanceTo('confirm');

        return [
            'response' => $this->promptConfirm($newState),
            'state' => $newState,
        ];
    }

    // Step 4: Confirm generation
    protected function promptConfirm(ConversationState $state): NormalizedResponse
    {
        $amount = $this->formatMoney($state->get('amount'));
        $count = $state->get('count');
        $total = $this->formatMoney($state->get('amount') * $count);
        $campaign = $state->get('campaign') ?? 'None';

        return NormalizedResponse::text(
            "📋 *Confirm Generation*\n\n".
            "Amount per voucher: *{$amount}*\n".
            "Number of vouchers: *{$count}*\n".
            "Campaign: *{$campaign}*\n".
            "Total value: *{$total}*\n\n".
            "Send *YES* to generate or *NO* to cancel."
        );
    }

    protected function handleConfirm(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $response = strtolower($input);

        if (in_array($response, ['no', 'n', 'cancel'])) {
            return $this->complete(
                NormalizedResponse::text("❌ Generation cancelled.\n\nSend /help to see available commands.")
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

    // Step 5: Execute generation
    protected function handleFinalize(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $this->log('info', 'Generating vouchers', [
            'amount' => $state->get('amount'),
            'count' => $state->get('count'),
            'campaign' => $state->get('campaign'),
        ]);

        try {
            $instructions = VoucherInstructionsData::from([
                'cash' => [
                    'amount' => $state->get('amount'),
                    'currency' => Number::defaultCurrency(),
                ],
                'count' => $state->get('count'),
                'voucher_type' => null, // redeemable
            ]);

            $vouchers = GenerateVouchers::run($instructions);

            $codes = $vouchers->pluck('code')->implode("\n• ");
            $amount = $this->formatMoney($state->get('amount'));
            $count = $vouchers->count();
            $total = $this->formatMoney($state->get('amount') * $count);

            return $this->complete(
                NormalizedResponse::text(
                    "✅ *Vouchers Generated!*\n\n".
                    "• {$codes}\n\n".
                    "Amount: *{$amount}* each\n".
                    "Count: *{$count}*\n".
                    "Total: *{$total}*\n\n".
                    "Share the codes with recipients. 🎉"
                )
            );

        } catch (\Throwable $e) {
            $this->log('error', 'Generation failed', ['error' => $e->getMessage()]);

            return $this->complete(
                NormalizedResponse::text(
                    "❌ Generation failed: {$e->getMessage()}\n\n".
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
