<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use LBHurtado\EngageSpark\EngageSparkMessage;

class PaymentConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected PaymentRequest $paymentRequest
    ) {}

    public function via(object $notifiable): array
    {
        // Primary: EngageSpark SMS; also store in database for audit
        return ['engage_spark', 'database'];
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $pr = $this->paymentRequest->fresh(['voucher']);

        // 24-hour expiry signed URL
        $signedUrl = URL::signedRoute('pay.confirm', [
            'paymentRequest' => $pr->id,
        ], now()->addHours(24));

        $amount = number_format($pr->getAmountInMajorUnits(), 0);
        $code = $pr->voucher?->code ?? 'N/A';

        $content = "Payment received! ₱{$amount} for voucher {$code}. Confirm here: {$signedUrl}";

        return (new EngageSparkMessage())
            ->content($content);
    }

    public function toArray(object $notifiable): array
    {
        $pr = $this->paymentRequest;
        return [
            'payment_request_id' => $pr->id,
            'voucher_id' => $pr->voucher_id,
            'voucher_code' => $pr->voucher?->code,
            'amount' => $pr->amount,
            'currency' => $pr->currency,
            'status' => $pr->status,
        ];
    }
}
