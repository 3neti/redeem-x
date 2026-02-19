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

        $text = "📋 <b>Available Commands</b>\n\n";

        // Public commands
        $text .= "<b>General</b>\n";
        $text .= "/start - Welcome message\n";
        $text .= "/help - Show this help\n";
        $text .= "/link - Link your account\n";
        $text .= "/cancel - Cancel current operation\n\n";

        $text .= "<b>Vouchers</b>\n";
        $text .= "/redeem - Redeem a voucher code\n";
        $text .= "/balance - Check your wallet balance\n";

        // Admin commands
        if ($isAdmin) {
            $text .= "\n<b>Admin Commands</b> 🔐\n";
            $text .= "/generate - Generate new vouchers\n";
            $text .= "/disburse - Quick single voucher creation\n";
        }

        $text .= "\n💡 <b>Tips</b>\n";
        $text .= "• Send /cancel anytime to stop a flow\n";
        $text .= "• Send SKIP to skip optional fields";

        return NormalizedResponse::html($text);
    }
}
