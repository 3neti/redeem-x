<?php

namespace App\Notifications;

use App\Models\AccountBalance;
use App\Models\BalanceAlert;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Low Balance Alert Notification
 * 
 * Alerts administrators when account balance falls below threshold.
 * Used by BalanceService when monitoring gateway balances.
 * 
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Uses lang/en/notifications.php for localization templates
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 */
class LowBalanceAlert extends BaseNotification
{
    public function __construct(
        protected AccountBalance $balance,
        protected BalanceAlert $alert
    ) {}

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'low_balance_alert';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        return [
            'account_number' => $this->balance->account_number,
            'gateway' => $this->balance->gateway,
            'balance' => $this->balance->balance,
            'available_balance' => $this->balance->available_balance,
            'threshold' => $this->alert->threshold,
            'currency' => $this->balance->currency,
            'checked_at' => $this->balance->checked_at->toIso8601String(),
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'account_number' => $this->balance->account_number,
            'gateway' => $this->balance->gateway,
            'below_threshold' => $this->balance->balance < $this->alert->threshold,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Build context for template processing
        $context = $this->buildMailContext();
        
        // Get localized templates
        $subject = $this->getLocalizedTemplate('notifications.low_balance_alert.email.subject', $context);
        $greeting = $this->getLocalizedTemplate('notifications.low_balance_alert.email.greeting', $context);
        $body = $this->getLocalizedTemplate('notifications.low_balance_alert.email.body', $context);
        $footer = $this->getLocalizedTemplate('notifications.low_balance_alert.email.footer', $context);
        
        // Get detail lines
        $details = __('notifications.low_balance_alert.email.details');
        
        $mail = (new MailMessage)
            ->error()
            ->subject($subject)
            ->greeting($greeting)
            ->line($body);
        
        // Add detail lines
        foreach ($details as $key => $template) {
            $line = $this->getLocalizedTemplate('notifications.low_balance_alert.email.details.' . $key, $context);
            $mail->line($line);
        }
        
        $mail->line($footer);
        
        return $mail;
    }

    /**
     * Build template context for email.
     */
    protected function buildMailContext(): array
    {
        return [
            'account_number' => $this->balance->account_number,
            'gateway' => ucfirst($this->balance->gateway),
            'formatted_balance' => $this->balance->formatted_balance,
            'formatted_available_balance' => $this->balance->formatted_available_balance,
            'formatted_threshold' => $this->alert->formatted_threshold,
            'checked_at' => $this->balance->checked_at->format('F j, Y g:i A'),
        ];
    }
}
