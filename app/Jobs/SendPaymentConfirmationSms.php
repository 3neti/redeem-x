<?php

namespace App\Jobs;

use App\Events\PaymentDetectedButNotConfirmed;
use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use LBHurtado\EngageSpark\Facades\Sms;

class SendPaymentConfirmationSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    public function __construct(
        public int $paymentRequestId,
        public string $payerMobile,
        public float $amount,
        public string $voucherCode,
    ) {}

    public function handle(): void
    {
        $paymentRequest = PaymentRequest::find($this->paymentRequestId);

        // Race condition guard: check if still pending
        if (!$paymentRequest || $paymentRequest->status !== 'pending') {
            Log::info('Skipping SMS - payment already confirmed', [
                'payment_request_id' => $this->paymentRequestId,
                'current_status' => $paymentRequest?->status,
            ]);
            return;
        }

        // Generate signed confirmation link (24hr expiry)
        $signedUrl = URL::signedRoute('pay.confirm', [
            'paymentRequest' => $paymentRequest->id,
        ], now()->addHours(24));

        // Send SMS via EngageSpark
        $message = "Payment received! ₱{$this->amount} for voucher {$this->voucherCode}. "
            . "Confirm here: {$signedUrl}";

        Sms::send($this->payerMobile, $message);

        Log::info('Payment confirmation SMS sent', [
            'payment_request_id' => $this->paymentRequestId,
            'mobile' => $this->payerMobile,
            'amount' => $this->amount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send payment confirmation SMS', [
            'payment_request_id' => $this->paymentRequestId,
            'mobile' => $this->payerMobile,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Create job from event
     */
    public static function fromEvent(PaymentDetectedButNotConfirmed $event): self
    {
        return new self(
            $event->paymentRequestId,
            $event->payerMobile,
            $event->amount,
            $event->voucherCode,
        );
    }
}
