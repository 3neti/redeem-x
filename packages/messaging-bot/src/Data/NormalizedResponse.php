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
        public bool $requestLocation = false,
        public bool $removeKeyboard = false,
        public ?string $webAppUrl = null,
        public ?string $webAppButtonText = null,
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
            requestContact: $this->requestContact,
            requestLocation: $this->requestLocation,
            removeKeyboard: $this->removeKeyboard,
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
            requestContact: $this->requestContact,
            requestLocation: $this->requestLocation,
            removeKeyboard: $this->removeKeyboard,
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
            requestLocation: false,
            removeKeyboard: false,
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
     * Request the user to share their location.
     *
     * This flag tells the driver to show a "Share Location" keyboard button.
     */
    public function withLocationRequest(): self
    {
        return new self(
            text: $this->text,
            buttons: $this->buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
            requestContact: false,
            requestLocation: true,
            removeKeyboard: false,
        );
    }

    /**
     * Remove the custom keyboard and return to default.
     */
    public function withKeyboardRemoved(): self
    {
        return new self(
            text: $this->text,
            buttons: $this->buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
            requestContact: false,
            requestLocation: false,
            removeKeyboard: true,
        );
    }

    /**
     * Determine if this response should remove the keyboard.
     */
    public function wantsKeyboardRemoved(): bool
    {
        return $this->removeKeyboard;
    }

    /**
     * Determine if this response requests location sharing.
     */
    public function wantsLocationRequest(): bool
    {
        return $this->requestLocation;
    }

    /**
     * Request to show a WebApp keyboard button.
     *
     * This flag tells the driver to show a keyboard button that opens a Mini App.
     */
    public function withWebAppButton(string $text, string $url): self
    {
        return new self(
            text: $this->text,
            buttons: $this->buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
            requestContact: false,
            requestLocation: false,
            removeKeyboard: false,
            webAppUrl: $url,
            webAppButtonText: $text,
        );
    }

    /**
     * Determine if this response wants a WebApp button.
     */
    public function wantsWebAppButton(): bool
    {
        return filled($this->webAppUrl) && filled($this->webAppButtonText);
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

    /**
     * Add inline keyboard buttons.
     *
     * @param  array<array{text: string, callback_data: string}>  $buttons
     */
    public function withInlineButtons(array $buttons): self
    {
        return new self(
            text: $this->text,
            buttons: $buttons,
            parseMode: $this->parseMode,
            disableNotification: $this->disableNotification,
            requestContact: $this->requestContact,
            requestLocation: $this->requestLocation,
            removeKeyboard: $this->removeKeyboard,
        );
    }
}
