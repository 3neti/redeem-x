<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Data;

enum Intent: string
{
    case Start = 'start';
    case Help = 'help';
    case Balance = 'balance';
    case Redeem = 'redeem';
    case Generate = 'generate';
    case Disburse = 'disburse';
    case Cancel = 'cancel';
    case Unknown = 'unknown';

    /**
     * Determine if this intent requires admin privileges.
     */
    public function requiresAdmin(): bool
    {
        return match ($this) {
            self::Generate, self::Disburse => true,
            default => false,
        };
    }

    /**
     * Determine if this intent is a multi-step flow.
     */
    public function isFlow(): bool
    {
        return match ($this) {
            self::Redeem, self::Generate, self::Disburse => true,
            default => false,
        };
    }

    /**
     * Get the command patterns that map to this intent.
     */
    public function patterns(): array
    {
        return match ($this) {
            self::Start => ['/start', 'start', 'hi', 'hello'],
            self::Help => ['/help', 'help', '?'],
            self::Balance => ['/balance', 'balance', 'bal'],
            self::Redeem => ['/redeem', 'redeem'],
            self::Generate => ['/generate', 'generate', 'gen'],
            self::Disburse => ['/disburse', 'disburse', 'send'],
            self::Cancel => ['/cancel', 'cancel', 'exit', 'quit'],
            self::Unknown => [],
        };
    }

    /**
     * Parse text input into an Intent.
     */
    public static function fromText(string $text): self
    {
        $normalized = strtolower(trim($text));

        foreach (self::cases() as $intent) {
            if (in_array($normalized, $intent->patterns(), true)) {
                return $intent;
            }
        }

        return self::Unknown;
    }
}
