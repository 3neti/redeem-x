<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Simple test notification to debug SMS/Email delivery
 * 
 * NOT queued - runs synchronously for immediate feedback
 */
class TestSimpleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message = 'Test notification'
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['engage_spark', 'mail', 'database'];
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
            ->line('This is a test notification.');
    }

    /**
     * Get the array representation for database.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'test',
            'message' => $this->message,
        ];
    }
}
