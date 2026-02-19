<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use LBHurtado\MessagingBot\Contracts\MessagingHandlerInterface;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Base handler providing common functionality for all message handlers.
 *
 * Uses the template method pattern to provide authentication, logging,
 * and error handling while allowing subclasses to focus on business logic.
 */
abstract class BaseMessagingHandler implements MessagingHandlerInterface
{
    /**
     * The authenticated user for this request.
     */
    protected ?User $user = null;

    /**
     * Handle an incoming message (template method).
     */
    final public function handle(NormalizedUpdate $update): NormalizedResponse
    {
        $this->log('info', 'Processing message', [
            'chat_id' => $update->chatId,
            'text' => $update->text,
        ]);

        // Attempt to find authenticated user
        $this->user = $this->findUser($update);

        // Check authentication requirement
        if ($this->requiresAuth() && ! $this->user) {
            $this->log('warning', 'Unauthenticated request', [
                'chat_id' => $update->chatId,
            ]);

            return $this->unauthenticatedResponse();
        }

        // Check admin requirement
        if ($this->requiresAdmin() && ! $this->isAdmin($update)) {
            $this->log('warning', 'Unauthorized admin request', [
                'chat_id' => $update->chatId,
            ]);

            return $this->unauthorizedResponse();
        }

        // Execute the handler logic
        try {
            return $this->process($update);
        } catch (\Throwable $e) {
            $this->log('error', 'Handler failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse($e);
        }
    }

    /**
     * Process the message (to be implemented by subclasses).
     */
    abstract protected function process(NormalizedUpdate $update): NormalizedResponse;

    /**
     * Determine if this handler requires authentication.
     */
    public function requiresAuth(): bool
    {
        return false;
    }

    /**
     * Determine if this handler requires admin privileges.
     */
    public function requiresAdmin(): bool
    {
        return false;
    }

    /**
     * Find the user associated with this update.
     */
    protected function findUser(NormalizedUpdate $update): ?User
    {
        $channelName = $update->platform->channelName();

        return User::whereHas('channels', function ($query) use ($channelName, $update) {
            $query->where('name', $channelName)
                ->where('value', $update->chatId);
        })->first();
    }

    /**
     * Determine if the sender is an admin.
     */
    protected function isAdmin(NormalizedUpdate $update): bool
    {
        $adminIds = config("messaging-bot.drivers.{$update->platform->value}.admin_chat_ids", []);

        return in_array($update->chatId, $adminIds, true);
    }

    /**
     * Get the authenticated user.
     */
    protected function user(): ?User
    {
        return $this->user;
    }

    /**
     * Build an unauthenticated response.
     */
    protected function unauthenticatedResponse(): NormalizedResponse
    {
        return NormalizedResponse::text(
            "⚠️ You need to link your account first.\n\n".
            "Please register at our website to connect your {$this->platformName()} account."
        );
    }

    /**
     * Build an unauthorized response.
     */
    protected function unauthorizedResponse(): NormalizedResponse
    {
        return NormalizedResponse::text(
            '🚫 This command requires admin privileges.'
        );
    }

    /**
     * Build an error response.
     */
    protected function errorResponse(\Throwable $e): NormalizedResponse
    {
        return NormalizedResponse::text(
            "❌ Something went wrong. Please try again later.\n\n".
            'If the problem persists, contact support.'
        );
    }

    /**
     * Get the platform display name.
     */
    protected function platformName(): string
    {
        return 'messaging';
    }

    /**
     * Log a message with handler context.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $prefix = '['.class_basename($this).']';

        Log::{$level}("{$prefix} {$message}", $context);
    }
}
