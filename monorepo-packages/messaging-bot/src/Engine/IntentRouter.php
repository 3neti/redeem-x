<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Engine;

use LBHurtado\MessagingBot\Contracts\FlowInterface;
use LBHurtado\MessagingBot\Contracts\MessagingHandlerInterface;
use LBHurtado\MessagingBot\Data\Intent;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

/**
 * Routes incoming messages to appropriate handlers or flows.
 *
 * Determines the user's intent from message text and resolves
 * the corresponding handler or flow class.
 */
class IntentRouter
{
    public function __construct(
        protected array $handlers = [],
        protected array $flows = [],
    ) {}

    /**
     * Resolve the intent from an update.
     */
    public function resolveIntent(NormalizedUpdate $update): Intent
    {
        return $update->intent();
    }

    /**
     * Get the handler for an intent.
     */
    public function getHandler(Intent $intent): ?MessagingHandlerInterface
    {
        $handlerClass = $this->handlers[$intent->value] ?? null;

        if (! $handlerClass) {
            return null;
        }

        return app($handlerClass);
    }

    /**
     * Get the flow for an intent.
     */
    public function getFlow(Intent $intent): ?FlowInterface
    {
        $flowClass = $this->flows[$intent->value] ?? null;

        if (! $flowClass) {
            return null;
        }

        return app($flowClass);
    }

    /**
     * Determine if an intent should start a flow.
     */
    public function isFlow(Intent $intent): bool
    {
        return $intent->isFlow() && isset($this->flows[$intent->value]);
    }

    /**
     * Determine if an intent has a single-step handler.
     */
    public function isHandler(Intent $intent): bool
    {
        return isset($this->handlers[$intent->value]);
    }

    /**
     * Get all registered handlers.
     */
    public function handlers(): array
    {
        return $this->handlers;
    }

    /**
     * Get all registered flows.
     */
    public function flows(): array
    {
        return $this->flows;
    }
}
