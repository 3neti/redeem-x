<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Handles the /start command.
 *
 * Welcomes users and shows available commands.
 */
class StartHandler extends BaseMessagingHandler
{
    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        $name = $update->senderName();

        $text = <<<TEXT
        👋 Welcome, {$name}!

        I'm your PayCode assistant. I can help you:

        💰 /redeem - Redeem a voucher code
        💵 /balance - Check your wallet balance
        ❓ /help - See all available commands

        Send a command to get started!
        TEXT;

        return NormalizedResponse::text($text);
    }
}
