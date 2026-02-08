<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\InstructionsFormatter;
use App\Services\TemplateProcessor;
use App\Services\VoucherShareLinkBuilder;
use Illuminate\Notifications\Messages\MailMessage;
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
 *
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Already uses lang/en/notifications.php for localization (no changes needed)
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 */
class VouchersGeneratedSummary extends BaseNotification implements VouchersGeneratedNotificationInterface
{
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
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'vouchers_generated';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        $context = $this->buildContext();
        $format = config('voucher-notifications.vouchers_generated.instructions_format', 'none');

        $data = [
            'count' => $context['count'],
            'amount' => $context['amount'],
            'currency' => $context['currency'],
            'formatted_amount' => $context['formatted_amount'],
            'codes' => $this->vouchers->pluck('code')->toArray(),
            'instructions_format' => $format,
        ];

        // Include full instructions JSON for database audit
        if ($format !== 'none') {
            $first = $this->vouchers->first();
            $data['instructions_data'] = $first->instructions->toArray();
        }

        return $data;
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'voucher_count' => $this->vouchers->count(),
            'first_code' => $this->vouchers->first()?->code,
            'total_value' => $this->vouchers->sum(fn ($v) => $v->instructions->cash->amount ?? 0),
        ]);
    }

    /**
     * Get the EngageSpark SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $context = $this->buildContext();
        $format = config('voucher-notifications.vouchers_generated.instructions_format', 'none');

        // Choose template based on voucher count and instructions format
        $hasInstructions = $format !== 'none' && isset($context['instructions_formatted']);
        $templateKey = match (true) {
            $context['count'] === 1 && $hasInstructions => 'notifications.vouchers_generated.sms.single_with_instructions',
            $context['count'] === 1 => 'notifications.vouchers_generated.sms.single',
            $context['count'] <= self::MAX_CODES_DISPLAY && $hasInstructions => 'notifications.vouchers_generated.sms.multiple_with_instructions',
            $context['count'] <= self::MAX_CODES_DISPLAY => 'notifications.vouchers_generated.sms.multiple',
            $hasInstructions => 'notifications.vouchers_generated.sms.many_with_instructions',
            default => 'notifications.vouchers_generated.sms.many',
        };

        $template = __($templateKey);
        $message = TemplateProcessor::process($template, $context);

        return (new EngageSparkMessage)->content($message);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $context = $this->buildContext();
        $count = $context['count'];
        $formattedAmount = $context['formatted_amount'];
        $format = config('voucher-notifications.vouchers_generated.instructions_format', 'none');

        // Build code list for email
        $codesList = $this->vouchers->pluck('code')->join(', ');

        $mail = (new MailMessage)
            ->subject("Vouchers Generated: {$count} voucher".($count === 1 ? '' : 's'))
            ->greeting('Hello,')
            ->line("You have successfully generated **{$count} voucher".($count === 1 ? '' : 's')."** with an amount of **{$formattedAmount}".($count === 1 ? '' : ' each').'**.')
            ->line("**Voucher Codes:** {$codesList}");

        // Add instructions if configured
        if ($format !== 'none' && isset($context['instructions_formatted'])) {
            $mail->line('');
            $mail->line('**Instructions:**');
            $mail->line('');

            if ($format === 'json') {
                // Use pre-formatted code block for JSON
                $mail->line('```');
                foreach (explode("\n", $context['instructions_formatted']) as $line) {
                    $mail->line($line);
                }
                $mail->line('```');
            } else {
                // Human-readable format - split lines
                $lines = explode("\n", $context['instructions_formatted']);
                foreach ($lines as $line) {
                    $mail->line("• {$line}");
                }
            }
        }

        $mail->line('');
        $mail->line('These vouchers are ready to be redeemed.');
        $mail->line('Thank you for using our service!');

        return $mail;
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

        // Add formatted instructions if configured
        $format = config('voucher-notifications.vouchers_generated.instructions_format', 'none');
        if ($format !== 'none') {
            // Use first voucher's instructions for SMS (optimized)
            $context['instructions_formatted_sms'] = InstructionsFormatter::formatForSms($first->instructions, $format);
            // Use first voucher's instructions for email (full)
            $context['instructions_formatted'] = InstructionsFormatter::formatForEmail($first->instructions, $format);
        }

        // Add shareable links if configured
        $includeShareLinks = config('voucher-notifications.vouchers_generated.include_share_links', true);
        if ($includeShareLinks) {
            $links = VoucherShareLinkBuilder::buildLinks($first);
            $context['share_links'] = "\n".VoucherShareLinkBuilder::formatForSms($links);
        } else {
            $context['share_links'] = '';
        }

        return $context;
    }
}
