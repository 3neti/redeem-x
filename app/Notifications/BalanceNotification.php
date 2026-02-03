<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Balance Notification
 * 
 * Sends balance information via SMS, email, and webhook.
 * Used by SMS BALANCE command to notify users of their balance.
 * 
 * Supports two types:
 * - user: Wallet balance only
 * - system: Wallet + Products + Bank balance (admin only)
 * 
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Uses lang/en/notifications.php for localization
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 */
class BalanceNotification extends BaseNotification
{
    public function __construct(
        protected string $type,
        protected array $balances,
        protected ?string $message = null
    ) {}

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'balance';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        return [
            'balance_type' => $this->type,
            'balances' => $this->balances,
            'message' => $this->message ?? $this->buildSmsMessage(),
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'balance_type' => $this->type,
            'wallet_balance' => $this->balances['wallet'] ?? null,
            'has_bank_balance' => isset($this->balances['bank']),
        ]);
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        // Build context for template processing
        $context = $this->buildBalanceContext();
        
        // Use localized template
        $templateKey = $this->type === 'system' 
            ? 'notifications.balance.system.sms'
            : 'notifications.balance.user.sms';
        
        $message = $this->getLocalizedTemplate($templateKey, $context);
        
        return (new EngageSparkMessage())->content($message);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Build context for template processing
        $context = $this->buildBalanceContext();
        
        // Get localized subject and greeting
        $subjectKey = $this->type === 'system' 
            ? 'notifications.balance.system.email.subject'
            : 'notifications.balance.user.email.subject';
        
        $greetingKey = $this->type === 'system'
            ? 'notifications.balance.system.email.greeting'
            : 'notifications.balance.user.email.greeting';
        
        $subject = $this->getLocalizedTemplate($subjectKey, $context);
        $greeting = $this->getLocalizedTemplate($greetingKey, $context);
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);
        
        if ($this->type === 'user') {
            $bodyKey = 'notifications.balance.user.email.body';
            $body = $this->getLocalizedTemplate($bodyKey, $context);
            $mail->line($body);
            
            $salutation = $this->getLocalizedTemplate('notifications.balance.user.email.salutation', $context);
            $mail->line('');
            $mail->line($salutation);
        } else {
            // System balance with detailed breakdown
            $mail->line('**System Balance Summary:**');
            $mail->line('');
            $mail->line("• **Wallet Balance:** {$this->formatMoney($this->balances['wallet'])}");
            $mail->line("• **Products Balance:** {$this->formatMoney($this->balances['products'])}");
            
            if (isset($this->balances['bank'])) {
                $bankLine = "• **Bank Balance:** {$this->formatMoney($this->balances['bank'])}";
                if (isset($this->balances['bank_timestamp'])) {
                    $bankLine .= " (as of {$this->balances['bank_timestamp']})";
                }
                if ($this->balances['bank_stale'] ?? false) {
                    $bankLine .= " ⚠️ STALE";
                }
                $mail->line($bankLine);
            }
        }
        
        return $mail;
    }

    /**
     * Get the webhook representation of the notification.
     */
    public function toWebhook(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'message' => $this->message ?? $this->buildSmsMessage(),
        ]);
    }

    /**
     * Build SMS message from balance data.
     * 
     * This method is kept for backward compatibility with existing code
     * that passes a custom message. New code should rely on localized templates.
     */
    protected function buildSmsMessage(): string
    {
        $context = $this->buildBalanceContext();
        
        if ($this->type === 'user') {
            return $this->getLocalizedTemplate('notifications.balance.user.sms', $context);
        }
        
        // For system balance, build composite message
        $baseLine = $this->getLocalizedTemplate('notifications.balance.system.sms', $context);
        
        if (isset($this->balances['bank'])) {
            $bankLine = "Bank: {$this->formatMoney($this->balances['bank'])}";
            if (isset($this->balances['bank_timestamp'])) {
                $bankLine .= " (as of {$this->balances['bank_timestamp']})";
            }
            if ($this->balances['bank_stale'] ?? false) {
                $bankLine .= " ⚠️ STALE";
            }
            return $baseLine . "\n" . $bankLine;
        }
        
        return $baseLine;
    }

    /**
     * Build template context for balance notifications.
     */
    protected function buildBalanceContext(): array
    {
        return [
            'formatted_balance' => $this->formatMoney($this->balances['wallet']),
            'wallet' => $this->formatMoney($this->balances['wallet'] ?? 0),
            'products' => $this->formatMoney($this->balances['products'] ?? 0),
            'bank_line' => isset($this->balances['bank']) 
                ? " | Bank: {$this->formatMoney($this->balances['bank'])}" 
                : '',
        ];
    }
}
