<?php

namespace App\Notifications;

use App\Models\PaymentRequest as PaymentRequestModel;
use Illuminate\Support\Facades\URL;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * Payment Confirmation Notification
 *
 * Sends SMS confirmation when settlement payment is received.
 * Used by SendPaymentConfirmationSms job after payment webhook.
 *
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Uses lang/en/notifications.php for localization templates
 * - Implements NotificationInterface (getNotificationType, getNotificationData, getAuditMetadata)
 * - Database logging and queue priority managed by BaseNotification
 */
class PaymentConfirmationNotification extends BaseNotification
{
    public function __construct(
        protected PaymentRequestModel $paymentRequest
    ) {}

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'payment_confirmation';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        $pr = $this->paymentRequest->fresh(['voucher']);
        $signedUrl = $this->buildConfirmationUrl($pr);
        $amount = number_format($pr->getAmountInMajorUnits(), 0);
        $code = $pr->voucher?->code ?? 'N/A';

        return [
            'payment_request_id' => $pr->id,
            'voucher_id' => $pr->voucher_id,
            'voucher_code' => $code,
            'amount' => $pr->amount,
            'currency' => $pr->currency,
            'status' => $pr->status,
            'message' => $this->buildMessage($amount, $code, $signedUrl),
            'confirmation_url' => $signedUrl,
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'payment_request_id' => $this->paymentRequest->id,
            'voucher_code' => $this->paymentRequest->voucher?->code ?? 'N/A',
            'amount' => $this->paymentRequest->amount,
        ]);
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $pr = $this->paymentRequest->fresh(['voucher']);
        $signedUrl = $this->buildConfirmationUrl($pr);
        $amount = number_format($pr->getAmountInMajorUnits(), 0);
        $code = $pr->voucher?->code ?? 'N/A';

        // Build context for template processing
        $context = [
            'amount' => $amount,
            'voucher_code' => $code,
            'confirmation_url' => $signedUrl,
        ];

        // Use localized template
        $content = $this->getLocalizedTemplate('notifications.payment_confirmation.sms', $context);

        return (new EngageSparkMessage)->content($content);
    }

    /**
     * Build confirmation URL (24-hour signed URL).
     */
    protected function buildConfirmationUrl(PaymentRequestModel $pr): string
    {
        return URL::signedRoute('pay.confirm', [
            'paymentRequest' => $pr->reference_id,
        ], now()->addHours(24));
    }

    /**
     * Build SMS message (for backward compatibility).
     */
    protected function buildMessage(string $amount, string $code, string $url): string
    {
        return "Payment received! ₱{$amount} for voucher {$code}. Confirm here: {$url}";
    }
}
