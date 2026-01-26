<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\TemplateProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use LBHurtado\EngageSpark\EngageSparkMessage;
use LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface;

/**
 * Vouchers Generated Summary Notification
 * 
 * Sends SMS notification summarizing generated vouchers.
 * Used by NotifyBatchCreator pipeline stage after voucher generation.
 * 
 * Message formats:
 * - Single: "1 voucher generated (₱100) - ABCD"
 * - Multiple: "3 vouchers generated (₱100 each) - ABCD, EFGH, IJKL"
 * - Many: "10 vouchers generated (₱100 each) - CODE1, CODE2, CODE3, +7 more"
 */
class VouchersGeneratedSummary extends Notification implements ShouldQueue, VouchersGeneratedNotificationInterface
{
    use Queueable;

    /**
     * Maximum number of voucher codes to show in message before truncating
     */
    private const MAX_CODES_DISPLAY = 3;

    public function __construct(
        protected Collection $vouchers
    ) {}

    /**
     * Create a new notification instance (factory method for interface).
     */
    public static function make(Collection $vouchers): static
    {
        return new static($vouchers);
    }

    /**
     * Get the notification's delivery channels.
     * 
     * Always includes 'database' for audit trail.
     * Additional channels (SMS/email) configured via config/voucher-notifications.php
     */
    public function via(object $notifiable): array
    {
        // For AnonymousNotifiable, use configured channels only (no database)
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return config('voucher-notifications.vouchers_generated.channels', ['engage_spark']);
        }
        
        // For User models, always include database for audit trail + configured channels
        $channels = config('voucher-notifications.vouchers_generated.channels', ['engage_spark']);
        
        return array_unique(array_merge($channels, ['database']));
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $context = $this->buildContext();
        
        // Choose template based on voucher count
        $templateKey = match (true) {
            $context['count'] === 1 => 'notifications.vouchers_generated.sms.single',
            $context['count'] <= self::MAX_CODES_DISPLAY => 'notifications.vouchers_generated.sms.multiple',
            default => 'notifications.vouchers_generated.sms.many',
        };
        
        $template = __($templateKey);
        $message = TemplateProcessor::process($template, $context);
        
        return (new EngageSparkMessage())->content($message);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $context = $this->buildContext();
        $count = $context['count'];
        $formattedAmount = $context['formatted_amount'];
        
        // Build code list for email
        $codesList = $this->vouchers->pluck('code')->join(', ');
        
        return (new MailMessage)
            ->subject("Vouchers Generated: {$count} voucher" . ($count === 1 ? '' : 's'))
            ->greeting('Hello,')
            ->line("You have successfully generated **{$count} voucher" . ($count === 1 ? '' : 's') . "** with an amount of **{$formattedAmount}" . ($count === 1 ? '' : ' each') . "**.")
            ->line("**Voucher Codes:** {$codesList}")
            ->line('These vouchers are ready to be redeemed.')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification for database storage.
     */
    public function toArray(object $notifiable): array
    {
        $context = $this->buildContext();
        
        return [
            'type' => 'vouchers_generated',
            'count' => $context['count'],
            'amount' => $context['amount'],
            'currency' => $context['currency'],
            'formatted_amount' => $context['formatted_amount'],
            'codes' => $this->vouchers->pluck('code')->toArray(),
        ];
    }

    /**
     * Build template context from vouchers collection.
     */
    protected function buildContext(): array
    {
        $count = $this->vouchers->count();
        $first = $this->vouchers->first();
        
        // Get amount from first voucher's instructions
        $amount = $first->instructions->cash->amount ?? 0;
        $currency = $first->instructions->cash->currency ?? 'PHP';
        
        // Format amount using Brick Money
        $money = \Brick\Money\Money::of($amount, $currency);
        $formattedAmount = $money->formatTo(Number::defaultLocale());
        
        // Collect voucher codes
        $allCodes = $this->vouchers->pluck('code');
        
        // Build context based on count
        $context = [
            'count' => $count,
            'amount' => $amount,
            'currency' => $currency,
            'formatted_amount' => $formattedAmount,
        ];
        
        if ($count === 1) {
            // Single voucher
            $context['code'] = $allCodes->first();
        } elseif ($count <= self::MAX_CODES_DISPLAY) {
            // Multiple vouchers (show all)
            $context['codes'] = $allCodes->join(', ');
        } else {
            // Many vouchers (show first 3 + count)
            $firstCodes = $allCodes->take(self::MAX_CODES_DISPLAY);
            $context['first_codes'] = $firstCodes->join(', ');
            $context['remaining'] = $count - self::MAX_CODES_DISPLAY;
        }
        
        return $context;
    }
}
