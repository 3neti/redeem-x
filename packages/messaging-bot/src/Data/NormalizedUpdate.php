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
        public ?string $phoneNumber = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $photoFileId = null,
        public array $rawPayload = [],
        public ?CarbonImmutable $timestamp = null,
    ) {
        $this->timestamp ??= CarbonImmutable::now();
    }

    /**
     * Check if this update contains a shared contact with phone number.
     */
    public function hasPhoneNumber(): bool
    {
        return filled($this->phoneNumber);
    }

    /**
     * Check if this update contains a shared location.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Check if this update contains a photo.
     */
    public function hasPhoto(): bool
    {
        return $this->photoFileId !== null;
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
        $callbackQuery = $payload['callback_query'] ?? null;
        $message = $payload['message'] ?? $callbackQuery['message'] ?? [];
        
        // For callback queries, 'from' is the user who pressed the button
        $from = $callbackQuery['from'] ?? $message['from'] ?? [];
        
        // Chat ID: prefer callback_query.message.chat.id, then message.chat.id, then from.id
        $chat = $message['chat'] ?? [];
        $chatId = $chat['id'] ?? $from['id'] ?? '';
        
        // Extract phone number from shared contact
        $contact = $message['contact'] ?? null;
        $phoneNumber = $contact['phone_number'] ?? null;

        // Extract location from shared location
        $location = $message['location'] ?? null;
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;

        // Extract photo file_id (use largest size - last element)
        $photos = $message['photo'] ?? [];
        $photoFileId = ! empty($photos) ? end($photos)['file_id'] ?? null : null;

        // Extract web_app_data (sent from Mini Apps via sendData())
        $webAppData = $message['web_app_data']['data'] ?? null;

        // Text: callback_query.data takes precedence, then web_app_data, then message.text
        $text = $callbackQuery['data'] ?? $webAppData ?? $message['text'] ?? null;

        return new self(
            platform: Platform::Telegram,
            chatId: (string) $chatId,
            userId: isset($from['id']) ? (string) $from['id'] : null,
            username: $from['username'] ?? null,
            firstName: $from['first_name'] ?? null,
            text: $text,
            messageId: isset($message['message_id']) ? (string) $message['message_id'] : null,
            phoneNumber: $phoneNumber,
            latitude: $latitude,
            longitude: $longitude,
            photoFileId: $photoFileId,
            rawPayload: $payload,
            timestamp: isset($message['date'])
                ? CarbonImmutable::createFromTimestamp($message['date'])
                : CarbonImmutable::now(),
        );
    }

    /**
     * Check if this update is from a callback query (inline button press).
     */
    public function isCallbackQuery(): bool
    {
        return isset($this->rawPayload['callback_query']);
    }

    /**
     * Get the callback query ID (for answering).
     */
    public function callbackQueryId(): ?string
    {
        return $this->rawPayload['callback_query']['id'] ?? null;
    }

    /**
     * Create a fake update for testing.
     *
     * @param  ?string  $text  Message text (null for contact-only shares)
     */
    public static function fake(
        ?string $text,
        string $chatId = '12345',
        Platform $platform = Platform::Telegram,
        ?string $phoneNumber = null,
        ?string $firstName = 'TestUser',
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $photoFileId = null,
    ): self {
        return new self(
            platform: $platform,
            chatId: $chatId,
            userId: $chatId,
            username: 'testuser',
            firstName: $firstName,
            text: $text,
            messageId: (string) time(),
            phoneNumber: $phoneNumber,
            latitude: $latitude,
            longitude: $longitude,
            photoFileId: $photoFileId,
            rawPayload: [],
            timestamp: CarbonImmutable::now(),
        );
    }
}
