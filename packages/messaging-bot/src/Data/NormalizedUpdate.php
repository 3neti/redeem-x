<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Normalized representation of an incoming message from any platform.
 *
 * This DTO abstracts away platform-specific payload structures, allowing
 * the core engine to work with a consistent interface regardless of
 * whether the message came from Telegram, WhatsApp, or Viber.
 */
class NormalizedUpdate extends Data
{
    public function __construct(
        public Platform $platform,
        public string $chatId,
        public ?string $userId = null,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $text = null,
        public ?string $messageId = null,
        public array $rawPayload = [],
        public ?CarbonImmutable $timestamp = null,
    ) {
        $this->timestamp ??= CarbonImmutable::now();
    }

    /**
     * Get a unique identifier for this conversation.
     */
    public function conversationKey(): string
    {
        return "{$this->platform->value}:{$this->chatId}";
    }

    /**
     * Determine if this update has text content.
     */
    public function hasText(): bool
    {
        return filled($this->text);
    }

    /**
     * Get the display name for the sender.
     */
    public function senderName(): string
    {
        return $this->firstName
            ?? $this->username
            ?? "User {$this->chatId}";
    }

    /**
     * Parse the intent from the message text.
     */
    public function intent(): Intent
    {
        if (! $this->hasText()) {
            return Intent::Unknown;
        }

        return Intent::fromText($this->text);
    }

    /**
     * Create from a Telegram update payload.
     */
    public static function fromTelegram(array $payload): self
    {
        $message = $payload['message'] ?? $payload['callback_query']['message'] ?? [];
        $from = $message['from'] ?? $payload['callback_query']['from'] ?? [];
        $chat = $message['chat'] ?? [];

        return new self(
            platform: Platform::Telegram,
            chatId: (string) ($chat['id'] ?? $from['id'] ?? ''),
            userId: isset($from['id']) ? (string) $from['id'] : null,
            username: $from['username'] ?? null,
            firstName: $from['first_name'] ?? null,
            text: $message['text'] ?? $payload['callback_query']['data'] ?? null,
            messageId: isset($message['message_id']) ? (string) $message['message_id'] : null,
            rawPayload: $payload,
            timestamp: isset($message['date'])
                ? CarbonImmutable::createFromTimestamp($message['date'])
                : CarbonImmutable::now(),
        );
    }
}
