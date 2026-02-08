<?php

declare(strict_types=1);

namespace App\Notifications;

use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Help Notification
 *
 * Sends help text via SMS for the HELP command.
 * Provides general command syntax or command-specific help.
 *
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 *
 * Note: Help messages are kept in SMSHelp handler (multi-line formatted strings)
 * Future improvement: Move to lang/en/notifications.php if localization needed
 */
class HelpNotification extends BaseNotification
{
    public function __construct(
        protected string $message
    ) {}

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'help';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        return [
            'message' => $this->message,
            'message_preview' => substr($this->message, 0, 50).'...',
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'message_length' => strlen($this->message),
            'is_general_help' => str_starts_with($this->message, 'Commands:'),
        ]);
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        return (new EngageSparkMessage)->content($this->message);
    }
}
