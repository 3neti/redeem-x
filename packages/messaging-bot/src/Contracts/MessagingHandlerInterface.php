<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Contracts;

use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Contract for single-step message handlers.
 *
 * Handlers process a single message and return a response immediately,
 * without maintaining conversation state between messages.
 */
interface MessagingHandlerInterface
{
    /**
     * Handle an incoming message and return a response.
     */
    public function handle(NormalizedUpdate $update): NormalizedResponse;

    /**
     * Determine if this handler requires authentication.
     */
    public function requiresAuth(): bool;

    /**
     * Determine if this handler requires admin privileges.
     */
    public function requiresAdmin(): bool;
}
