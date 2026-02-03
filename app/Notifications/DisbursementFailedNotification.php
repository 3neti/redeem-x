<?php

namespace App\Notifications;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Disbursement Failed Notification
 * 
 * Alerts administrators when a voucher disbursement fails.
 * Used by NotifyAdminOfDisbursementFailure listener.
 * 
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Uses lang/en/notifications.php for localization templates
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 */
class DisbursementFailedNotification extends BaseNotification
{

    public function __construct(
        protected Voucher $voucher,
        protected string $errorMessage,
        protected string $errorType,
        protected ?string $mobile = null
    ) {}
    
    /**
     * Create from exception (convenience method)
     */
    public static function fromException(Voucher $voucher, \Throwable $exception, ?string $mobile = null): self
    {
        return new self(
            $voucher,
            $exception->getMessage(),
            get_class($exception),
            $mobile
        );
    }

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'disbursement_failed';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        return [
            'voucher_id' => $this->voucher->id,
            'voucher_code' => $this->voucher->code,
            'amount' => $this->voucher->cash?->amount,
            'error_message' => $this->errorMessage,
            'error_type' => $this->errorType,
            'occurred_at' => now()->toIso8601String(),
            'mobile' => $this->mobile,
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'voucher_code' => $this->voucher->code,
            'error_type' => $this->errorType,
            'has_mobile' => !empty($this->mobile),
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Refresh voucher to get latest cash entity in queued context
        $this->voucher->refresh();
        
        // Build context for template processing
        $context = $this->buildMailContext();
        
        // Get localized templates
        $subject = $this->getLocalizedTemplate('notifications.disbursement_failed.email.subject', $context);
        $greeting = $this->getLocalizedTemplate('notifications.disbursement_failed.email.greeting', $context);
        $body = $this->getLocalizedTemplate('notifications.disbursement_failed.email.body', $context);
        $footer = $this->getLocalizedTemplate('notifications.disbursement_failed.email.footer', $context);
        
        // Get detail lines
        $details = __('notifications.disbursement_failed.email.details');
        
        $mail = (new MailMessage)
            ->error()
            ->subject($subject)
            ->greeting($greeting)
            ->line($body);
        
        // Add detail lines
        foreach ($details as $key => $template) {
            $line = $this->getLocalizedTemplate('notifications.disbursement_failed.email.details.' . $key, $context);
            $mail->line($line);
        }
        
        $action = $this->getLocalizedTemplate('notifications.disbursement_failed.email.action', $context);
        $mail->action($action, url('/vouchers/' . $this->voucher->id));
        $mail->line($footer);
        
        return $mail;
    }

    /**
     * Build template context for email.
     */
    protected function buildMailContext(): array
    {
        return [
            'voucher_code' => $this->voucher->code,
            'formatted_amount' => $this->voucher->cash?->formatted_amount ?? 'Unknown',
            'mobile' => $this->mobile ?? 'Unknown',
            'error_message' => $this->errorMessage,
            'occurred_at' => now()->format('F j, Y g:i A'),
        ];
    }
}
