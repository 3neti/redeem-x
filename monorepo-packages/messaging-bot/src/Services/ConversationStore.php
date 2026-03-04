<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LBHurtado\MessagingBot\Data\ConversationState;
use LBHurtado\MessagingBot\Data\Platform;

/**
 * Cache-based storage for conversation state.
 *
 * Stores and retrieves conversation state between messages,
 * enabling multi-step flows to maintain context.
 */
class ConversationStore
{
    public function __construct(
        protected CacheRepository $cache,
        protected int $ttlSeconds = 1800,
    ) {}

    /**
     * Get the conversation state for a chat.
     */
    public function get(Platform $platform, string $chatId): ?ConversationState
    {
        $key = $this->cacheKey($platform, $chatId);

        $data = $this->cache->get($key);

        if (! $data) {
            return null;
        }

        return ConversationState::from($data);
    }

    /**
     * Store conversation state.
     */
    public function put(ConversationState $state): void
    {
        $key = $state->cacheKey();

        $this->cache->put($key, $state->toArray(), $this->ttlSeconds);
    }

    /**
     * Remove conversation state.
     */
    public function forget(Platform $platform, string $chatId): void
    {
        $key = $this->cacheKey($platform, $chatId);

        $this->cache->forget($key);
    }

    /**
     * Check if a conversation exists.
     */
    public function has(Platform $platform, string $chatId): bool
    {
        return $this->cache->has($this->cacheKey($platform, $chatId));
    }

    /**
     * Build the cache key for a conversation.
     */
    protected function cacheKey(Platform $platform, string $chatId): string
    {
        return "messaging_conversation:{$platform->value}:{$chatId}";
    }

    /**
     * Get the TTL in seconds.
     */
    public function ttl(): int
    {
        return $this->ttlSeconds;
    }
}
