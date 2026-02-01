<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Help Notification
 * 
 * Sends help text via SMS for the HELP command.
 * Provides general command syntax or command-specific help.
 */
class HelpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $message
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // For AnonymousNotifiable, use configured channels only (no database)
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['engage_spark'];
        }
        
        // For User models, always include database for audit trail + SMS
        return ['engage_spark', 'database'];
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        return (new EngageSparkMessage())->content($this->message);
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'help',
            'message' => $this->message,
        ];
    }
}
