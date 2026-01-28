<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
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
 */
class BalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $type,
        protected array $balances,
        protected ?string $message = null
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // For AnonymousNotifiable, use configured channels only (no database)
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return config('voucher-notifications.balance.channels', ['engage_spark']);
        }
        
        // For User models, always include database for audit trail + configured channels
        $channels = config('voucher-notifications.balance.channels', ['engage_spark']);
        
        return array_unique(array_merge($channels, ['database']));
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $message = $this->message ?? $this->buildSmsMessage();
        
        return (new EngageSparkMessage())->content($message);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->type === 'system' ? 'System Balance' : 'Your Balance';
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello,');
        
        if ($this->type === 'user') {
            $mail->line("Your current wallet balance is **{$this->formatMoney($this->balances['wallet'])}**.");
        } else {
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
        
        $mail->line('');
        $mail->line('Thank you for using our service!');
        
        return $mail;
    }

    /**
     * Get the webhook representation of the notification.
     */
    public function toWebhook(object $notifiable): array
    {
        return [
            'type' => 'balance',
            'balance_type' => $this->type,
            'balances' => $this->balances,
            'message' => $this->message ?? $this->buildSmsMessage(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'balance',
            'balance_type' => $this->type,
            'balances' => $this->balances,
            'message' => $this->message ?? $this->buildSmsMessage(),
        ];
    }

    /**
     * Build SMS message from balance data.
     */
    protected function buildSmsMessage(): string
    {
        if ($this->type === 'user') {
            return "Balance: {$this->formatMoney($this->balances['wallet'])}";
        }
        
        $lines = [
            "Wallet: {$this->formatMoney($this->balances['wallet'])} | Products: {$this->formatMoney($this->balances['products'])}",
        ];
        
        if (isset($this->balances['bank'])) {
            $bankLine = "Bank: {$this->formatMoney($this->balances['bank'])}";
            if (isset($this->balances['bank_timestamp'])) {
                $bankLine .= " (as of {$this->balances['bank_timestamp']})";
            }
            if ($this->balances['bank_stale'] ?? false) {
                $bankLine .= " ⚠️ STALE";
            }
            $lines[] = $bankLine;
        }
        
        return implode("\n", $lines);
    }

    /**
     * Format money amount.
     */
    protected function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            $money = \Brick\Money\Money::of($amount, $currency);
            return $money->formatTo('en_PH');
        } catch (\Throwable $e) {
            return '₱' . number_format($amount, 2);
        }
    }
}
