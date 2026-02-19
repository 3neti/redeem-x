<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use Brick\Money\Money;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Handles the /balance command.
 *
 * Shows the user's current wallet balance.
 */
class BalanceHandler extends BaseMessagingHandler
{
    /**
     * This handler requires authentication.
     */
    public function requiresAuth(): bool
    {
        return true;
    }

    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        $user = $this->user();

        if (! $user) {
            return NormalizedResponse::text(
                "⚠️ Account not found.\n\n".
                'Please link your Telegram account at our website first.'
            );
        }

        // Get wallet balance
        $balance = $user->balanceInt ?? 0;
        $formattedBalance = $this->formatMoney($balance / 100);

        $text = "💰 <b>Your Balance</b>\n\n";
        $text .= "Available: <b>{$formattedBalance}</b>\n\n";

        // Add recent transactions summary if available
        if (method_exists($user, 'transactions')) {
            $recentCount = $user->transactions()->where('created_at', '>=', now()->subDays(7))->count();
            $text .= "📊 {$recentCount} transactions this week";
        }

        return NormalizedResponse::html($text);
    }

    /**
     * Format amount as money.
     */
    protected function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            $money = Money::of($amount, $currency);

            return $money->formatTo('en_PH');
        } catch (\Throwable $e) {
            return '₱'.number_format($amount, 2);
        }
    }
}
