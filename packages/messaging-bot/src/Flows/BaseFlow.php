<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Flows;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use LBHurtado\MessagingBot\Contracts\FlowInterface;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Base class for multi-step conversation flows.
 *
 * Provides common functionality for step management, validation,
 * and state transitions while allowing subclasses to define
 * specific step logic.
 */
abstract class BaseFlow implements FlowInterface
{
    /**
     * The authenticated user for this flow.
     */
    protected ?User $user = null;

    /**
     * Process the current step and return a response.
     */
    public function process(NormalizedUpdate $update, ConversationState $state): array
    {
        $step = $state->currentStep;
        $input = trim($update->text ?? '');

        $this->log('info', "Processing step: {$step}", [
            'chat_id' => $update->chatId,
            'input' => $input,
        ]);

        // Handle cancel command
        if ($this->isCancelCommand($input)) {
            return [
                'response' => $this->cancelledResponse(),
                'state' => null,
            ];
        }

        // Get step handler method
        $method = 'handle'.ucfirst($step);

        if (! method_exists($this, $method)) {
            $this->log('error', "Step handler not found: {$method}");

            return [
                'response' => NormalizedResponse::text('❌ Invalid flow state. Please start over.'),
                'state' => null,
            ];
        }

        try {
            return $this->{$method}($update, $state, $input);
        } catch (\Throwable $e) {
            $this->log('error', 'Flow step failed', [
                'step' => $step,
                'error' => $e->getMessage(),
            ]);

            return [
                'response' => $this->errorResponse($e),
                'state' => null,
            ];
        }
    }

    /**
     * Get the prompt for a specific step.
     */
    public function promptFor(string $step, ConversationState $state): NormalizedResponse
    {
        $method = 'prompt'.ucfirst($step);

        if (method_exists($this, $method)) {
            return $this->{$method}($state);
        }

        return NormalizedResponse::text("Please provide input for: {$step}");
    }

    /**
     * Validate input for a specific step.
     */
    public function validateStep(string $step, string $input, ConversationState $state): bool
    {
        $method = 'validate'.ucfirst($step);

        if (method_exists($this, $method)) {
            return $this->{$method}($input, $state);
        }

        return filled($input);
    }

    /**
     * Determine if this flow requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Determine if this flow requires admin privileges.
     */
    public function requiresAdmin(): bool
    {
        return false;
    }

    /**
     * Check if input is a cancel command.
     */
    protected function isCancelCommand(string $input): bool
    {
        return in_array(strtolower($input), ['/cancel', 'cancel', 'exit', 'quit'], true);
    }

    /**
     * Check if input is a skip command.
     */
    protected function isSkipCommand(string $input): bool
    {
        return in_array(strtolower($input), ['skip', '/skip', '-'], true);
    }

    /**
     * Build a cancelled response.
     */
    protected function cancelledResponse(): NormalizedResponse
    {
        return NormalizedResponse::text(
            "✅ Operation cancelled.\n\n".
            'Send /help to see available commands.'
        );
    }

    /**
     * Build an error response.
     */
    protected function errorResponse(\Throwable $e): NormalizedResponse
    {
        return NormalizedResponse::text(
            "❌ Something went wrong. Please try again.\n\n".
            'Send /cancel to exit this flow.'
        );
    }

    /**
     * Build a validation error response.
     */
    protected function validationError(string $message): NormalizedResponse
    {
        return NormalizedResponse::text("⚠️ {$message}\n\nPlease try again or send /cancel to exit.");
    }

    /**
     * Advance to the next step.
     */
    protected function advanceTo(string $nextStep, ConversationState $state): array
    {
        $newState = $state->advanceTo($nextStep);

        return [
            'response' => $this->promptFor($nextStep, $newState),
            'state' => $newState,
        ];
    }

    /**
     * Complete the flow.
     */
    protected function complete(NormalizedResponse $response): array
    {
        return [
            'response' => $response,
            'state' => null,
        ];
    }

    /**
     * Set the authenticated user.
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the authenticated user.
     */
    protected function user(): ?User
    {
        return $this->user;
    }

    /**
     * Log a message with flow context.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $prefix = '['.class_basename($this).']';

        Log::{$level}("{$prefix} {$message}", $context);
    }
}
