<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Engine;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\Intent;
use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;
use LBHurtado\MessagingBot\Services\ConversationStore;

/**
 * Main orchestration engine for the messaging bot.
 *
 * Coordinates between intent routing, conversation state,
 * handlers, and flows to process incoming messages.
 */
class MessagingKernel
{
    public function __construct(
        protected IntentRouter $router,
        protected ConversationStore $store,
    ) {}

    /**
     * Handle an incoming message update.
     */
    public function handle(NormalizedUpdate $update): NormalizedResponse
    {
        $this->log('info', 'Handling update', [
            'platform' => $update->platform->value,
            'chat_id' => $update->chatId,
            'text' => $update->text,
        ]);

        // Check for active conversation
        $state = $this->store->get($update->platform, $update->chatId);

        if ($state) {
            return $this->continueFlow($update, $state);
        }

        // Resolve intent and route
        $intent = $this->router->resolveIntent($update);

        $this->log('info', 'Resolved intent', ['intent' => $intent->value]);

        // Handle cancel without active flow
        if ($intent === Intent::Cancel) {
            return NormalizedResponse::text("ℹ️ No active operation to cancel.\n\nSend /help to see available commands.");
        }

        // Check if this starts a flow
        if ($this->router->isFlow($intent)) {
            return $this->startFlow($update, $intent);
        }

        // Check for single-step handler
        if ($this->router->isHandler($intent)) {
            return $this->executeHandler($update, $intent);
        }

        // Unknown command
        return $this->unknownCommandResponse();
    }

    /**
     * Start a new multi-step flow.
     */
    protected function startFlow(NormalizedUpdate $update, Intent $intent): NormalizedResponse
    {
        $flow = $this->router->getFlow($intent);

        if (! $flow) {
            return NormalizedResponse::text('❌ This feature is not yet available.');
        }

        // Check authorization
        if ($flow->requiresAdmin() && ! $this->isAdmin($update)) {
            return NormalizedResponse::text('🚫 This command requires admin privileges.');
        }

        // Initialize conversation state
        $state = ConversationState::start(
            platform: $update->platform,
            chatId: $update->chatId,
            intent: $intent,
            initialStep: $flow->initialStep(),
        );

        // Store state
        $this->store->put($state);

        $this->log('info', 'Started flow', [
            'intent' => $intent->value,
            'step' => $flow->initialStep(),
        ]);

        // Return first prompt
        return $flow->promptFor($flow->initialStep(), $state);
    }

    /**
     * Continue an active flow.
     */
    protected function continueFlow(NormalizedUpdate $update, ConversationState $state): NormalizedResponse
    {
        $flow = $this->router->getFlow($state->intent);

        if (! $flow) {
            $this->store->forget($update->platform, $update->chatId);

            return NormalizedResponse::text('❌ Flow expired. Please start again.');
        }

        // Find user if needed
        if ($flow->requiresAuth()) {
            $user = $this->findUser($update);
            $flow->setUser($user);
        }

        // Process the step
        $result = $flow->process($update, $state);

        // Update or clear state
        if ($result['state']) {
            $this->store->put($result['state']);
        } else {
            $this->store->forget($update->platform, $update->chatId);
        }

        return $result['response'];
    }

    /**
     * Execute a single-step handler.
     */
    protected function executeHandler(NormalizedUpdate $update, Intent $intent): NormalizedResponse
    {
        $handler = $this->router->getHandler($intent);

        if (! $handler) {
            return NormalizedResponse::text('❌ This feature is not yet available.');
        }

        return $handler->handle($update);
    }

    /**
     * Build an unknown command response.
     */
    protected function unknownCommandResponse(): NormalizedResponse
    {
        return NormalizedResponse::text(
            "🤔 I didn't understand that.\n\n".
            "Send /help to see available commands."
        );
    }

    /**
     * Find the user for this update.
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
     * Check if the sender is an admin.
     */
    protected function isAdmin(NormalizedUpdate $update): bool
    {
        $adminIds = config("messaging-bot.drivers.{$update->platform->value}.admin_chat_ids", []);

        return in_array($update->chatId, $adminIds, true);
    }

    /**
     * Log a message with kernel context.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        Log::{$level}("[MessagingKernel] {$message}", $context);
    }
}
