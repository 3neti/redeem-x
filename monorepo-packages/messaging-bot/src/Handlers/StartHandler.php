<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Engine\MessagingKernel;

/**
 * Handles the /start command.
 *
 * Welcomes users and shows available commands.
 * Also handles deep links: t.me/bot?start=redeem_CODE
 */
class StartHandler extends BaseMessagingHandler
{
    public function __construct(
        protected MessagingKernel $kernel,
    ) {}

    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        // Check for deep link parameter: /start action_param
        $deepLink = $this->parseDeepLink($update->text);

        if ($deepLink) {
            return $this->handleDeepLink($update, $deepLink['action'], $deepLink['param']);
        }

        return $this->showWelcome($update);
    }

    /**
     * Parse deep link from /start command.
     *
     * Format: /start action_param (e.g., /start redeem_ABCD)
     *
     * @return array{action: string, param: string}|null
     */
    protected function parseDeepLink(?string $text): ?array
    {
        if (! $text || ! str_starts_with($text, '/start ')) {
            return null;
        }

        $payload = trim(substr($text, 7)); // Remove "/start "

        if (! str_contains($payload, '_')) {
            return null;
        }

        [$action, $param] = explode('_', $payload, 2);

        if (empty($action) || empty($param)) {
            return null;
        }

        return ['action' => strtolower($action), 'param' => $param];
    }

    /**
     * Handle deep link actions.
     */
    protected function handleDeepLink(NormalizedUpdate $update, string $action, string $param): NormalizedResponse
    {
        return match ($action) {
            'redeem', 'disburse' => $this->startRedeemFlow($update, $param),
            default => $this->showWelcome($update),
        };
    }

    /**
     * Start redeem flow with pre-filled voucher code.
     */
    protected function startRedeemFlow(NormalizedUpdate $update, string $code): NormalizedResponse
    {
        // Create a synthetic update with /redeem command to start the flow
        $redeemUpdate = NormalizedUpdate::fake(
            text: '/redeem',
            chatId: $update->chatId,
            platform: $update->platform,
            firstName: $update->firstName,
        );

        // Start the redeem flow
        $this->kernel->handle($redeemUpdate);

        // Now send the voucher code as the next input
        $codeUpdate = NormalizedUpdate::fake(
            text: $code,
            chatId: $update->chatId,
            platform: $update->platform,
            firstName: $update->firstName,
        );

        return $this->kernel->handle($codeUpdate);
    }

    /**
     * Show welcome message.
     */
    protected function showWelcome(NormalizedUpdate $update): NormalizedResponse
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
