<?php

namespace App\Notifications;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Simple test notification to debug SMS/Email delivery
 * 
 * NOT queued - runs synchronously for immediate feedback
 * 
 * PROOF OF CONCEPT for BaseNotification migration pattern:
 * - Extends BaseNotification (gets via() with config-driven channel resolution)
 * - Implements required interface methods (getNotificationType, getNotificationData, getAuditMetadata)
 * - Uses config/notifications.php for channel configuration
 * - Database logging handled by BaseNotification (respects config)
 * - Demonstrates simplified notification implementation
 */
class TestSimpleNotification extends BaseNotification
{
    public function __construct(
        public string $message = 'Test notification'
    ) {}

    /**
     * Get the notification type identifier
     */
    public function getNotificationType(): string
    {
        return 'test';
    }

    /**
     * Get the notification data payload
     */
    public function getNotificationData(): array
    {
        return [
            'message' => $this->message,
        ];
    }

    /**
     * Get audit metadata for this notification
     */
    public function getAuditMetadata(): array
    {
        return [
            'notification_class' => static::class,
            'message_preview' => substr($this->message, 0, 50),
        ];
    }

    /**
     * Determine if this notification should be queued
     * 
     * Override BaseNotification - test notifications run synchronously
     */
    public function shouldQueue(object $notifiable): bool
    {
        return false;
    }

    /**
     * Get the EngageSpark SMS representation.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        return (new EngageSparkMessage())->content("TEST SMS: {$this->message}");
    }

    /**
     * Get the mail representation.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test Notification')
            ->line("TEST EMAIL: {$this->message}")
            ->line('This is a test notification.')
            ->line('This notification now extends BaseNotification for standardized behavior.');
    }
}
