<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Contracts;

use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Contract for multi-step conversation flows.
 *
 * Flows manage stateful conversations that require multiple messages
 * to complete, such as voucher redemption or generation.
 */
interface FlowInterface
{
    /**
     * Get the initial step name for this flow.
     */
    public function initialStep(): string;

    /**
     * Get all step names in order.
     */
    public function steps(): array;

    /**
     * Process the current step and return a response.
     *
     * @return array{response: NormalizedResponse, state: ConversationState|null}
     *               Returns null state when flow is complete
     */
    public function process(NormalizedUpdate $update, ConversationState $state): array;

    /**
     * Get the prompt for a specific step.
     */
    public function promptFor(string $step, ConversationState $state): NormalizedResponse;

    /**
     * Validate input for a specific step.
     */
    public function validateStep(string $step, string $input, ConversationState $state): bool;

    /**
     * Determine if this flow requires authentication.
     */
    public function requiresAuth(): bool;

    /**
     * Determine if this flow requires admin privileges.
     */
    public function requiresAdmin(): bool;
}
