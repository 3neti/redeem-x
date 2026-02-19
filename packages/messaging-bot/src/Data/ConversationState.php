<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Represents the state of an active conversation flow.
 *
 * This DTO is serialized to cache between messages, allowing multi-step
 * flows to maintain context across multiple user interactions.
 */
class ConversationState extends Data
{
    public function __construct(
        public Platform $platform,
        public string $chatId,
        public Intent $intent,
        public string $currentStep,
        public array $context = [],
        public ?CarbonImmutable $startedAt = null,
    ) {
        $this->startedAt ??= CarbonImmutable::now();
    }

    /**
     * Get the cache key for this conversation.
     */
    public function cacheKey(): string
    {
        return "messaging_conversation:{$this->platform->value}:{$this->chatId}";
    }

    /**
     * Advance to the next step.
     */
    public function advanceTo(string $step): self
    {
        return new self(
            platform: $this->platform,
            chatId: $this->chatId,
            intent: $this->intent,
            currentStep: $step,
            context: $this->context,
            startedAt: $this->startedAt,
        );
    }

    /**
     * Store a value in the context.
     */
    public function with(string $key, mixed $value): self
    {
        $context = $this->context;
        $context[$key] = $value;

        return new self(
            platform: $this->platform,
            chatId: $this->chatId,
            intent: $this->intent,
            currentStep: $this->currentStep,
            context: $context,
            startedAt: $this->startedAt,
        );
    }

    /**
     * Get a value from the context.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if a context key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }

    /**
     * Start a new conversation flow.
     */
    public static function start(
        Platform $platform,
        string $chatId,
        Intent $intent,
        string $initialStep = 'start'
    ): self {
        return new self(
            platform: $platform,
            chatId: $chatId,
            intent: $intent,
            currentStep: $initialStep,
        );
    }

    /**
     * Calculate how long this conversation has been active.
     */
    public function duration(): \DateInterval
    {
        return $this->startedAt->diff(CarbonImmutable::now());
    }

    /**
     * Check if this conversation is stale (older than TTL).
     */
    public function isStale(int $ttlSeconds): bool
    {
        return $this->startedAt->addSeconds($ttlSeconds)->isPast();
    }
}
