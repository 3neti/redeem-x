<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Contracts;

use Illuminate\Http\Request;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Data\Platform;

/**
 * Contract for messaging platform drivers.
 *
 * Each driver is responsible for translating between the platform's
 * native API format and the normalized DTOs used by the core engine.
 */
interface MessagingDriverInterface
{
    /**
     * Get the platform this driver handles.
     */
    public function platform(): Platform;

    /**
     * Parse an incoming webhook payload into a normalized update.
     */
    public function parseUpdate(array $payload): NormalizedUpdate;

    /**
     * Send a response message to a chat.
     */
    public function sendMessage(string $chatId, NormalizedResponse $response): void;

    /**
     * Verify the authenticity of an incoming webhook request.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Determine if this driver is properly configured.
     */
    public function isConfigured(): bool;
}
