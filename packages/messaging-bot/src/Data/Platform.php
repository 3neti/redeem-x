<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Data;

enum Platform: string
{
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Viber = 'viber';
    case Messenger = 'messenger';

    /**
     * Get the display name for this platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::WhatsApp => 'WhatsApp',
            self::Viber => 'Viber',
            self::Messenger => 'Messenger',
        };
    }

    /**
     * Get the channel name used in the channels relationship.
     */
    public function channelName(): string
    {
        return match ($this) {
            self::Telegram => 'telegram_id',
            self::WhatsApp => 'whatsapp_id',
            self::Viber => 'viber_id',
            self::Messenger => 'messenger_id',
        };
    }
}
