<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Handles the /help command.
 *
 * Shows all available commands and their descriptions.
 */
class HelpHandler extends BaseMessagingHandler
{
    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        $isAdmin = $this->isAdmin($update);

        $text = "📋 *Available Commands*\n\n";

        // Public commands
        $text .= "*General*\n";
        $text .= "/start - Welcome message\n";
        $text .= "/help - Show this help\n";
        $text .= "/cancel - Cancel current operation\n\n";

        $text .= "*Vouchers*\n";
        $text .= "/redeem - Redeem a voucher code\n";
        $text .= "/balance - Check your wallet balance\n";

        // Admin commands
        if ($isAdmin) {
            $text .= "\n*Admin Commands* 🔐\n";
            $text .= "/generate - Generate new vouchers\n";
            $text .= "/disburse - Quick single voucher creation\n";
        }

        $text .= "\n💡 *Tips*\n";
        $text .= "• Send /cancel anytime to stop a flow\n";
        $text .= "• Send SKIP to skip optional fields";

        return NormalizedResponse::markdown($text);
    }
}
