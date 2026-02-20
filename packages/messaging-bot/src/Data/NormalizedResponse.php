<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Data;

use Spatie\LaravelData\Data;

/**
 * Normalized representation of an outgoing message to any platform.
 *
 * This DTO provides a consistent way to build responses that can be
 * translated into platform-specific formats by each driver.
 */
class NormalizedResponse extends Data
{
    public function __construct(
        public string $text,
        public array $buttons = [],
        public ?string $parseMode = null,
        public bool $disableNotification = false,
        public bool $requestContact = false,
    ) {}

    /**
     * Create a simple text response.
     */
    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * Create a response with Markdown formatting.
     */
    public static function markdown(string $text): self
    {
        return new self(text: $text, parseMode: 'MarkdownV2');
    }

    /**
     * Create a response with HTML formatting.
     */
    public static function html(string $text): self
    {
        return new self(text: $text, parseMode: 'HTML');
    }

    /**
     * Add inline buttons to the response.
     */
    public function withButtons(array $buttons): self
    {
        return new self(
            text: $this->text,
            buttons: $buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
        );
    }

    /**
     * Send silently without notification sound.
     */
    public function silent(): self
    {
        return new self(
            text: $this->text,
            buttons: $this->buttons,
            parseMode: $this->parseMode,
            disableNotification: true,
        );
    }

    /**
     * Determine if response has buttons.
     */
    public function hasButtons(): bool
    {
        return ! empty($this->buttons);
    }

    /**
     * Request the user to share their contact (phone number).
     *
     * This flag tells the driver to show a "Share Phone" keyboard button.
     */
    public function withContactRequest(): self
    {
        return new self(
            text: $this->text,
            buttons: $this->buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
            requestContact: true,
        );
    }

    /**
     * Determine if this response requests contact sharing.
     */
    public function wantsContactRequest(): bool
    {
        return $this->requestContact;
    }

    /**
     * Build a confirmation prompt with Yes/No buttons.
     */
    public static function confirm(string $text): self
    {
        return (new self(text: $text))->withButtons([
            ['text' => '✅ Yes', 'callback_data' => 'confirm_yes'],
            ['text' => '❌ No', 'callback_data' => 'confirm_no'],
        ]);
    }
}
