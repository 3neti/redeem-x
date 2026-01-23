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
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentConfirmationNotification;

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

        // Send SMS using Notification + EngageSpark channel
        Notification::route('engage_spark', $this->payerMobile)
            ->notify(new PaymentConfirmationNotification($paymentRequest));

        // Persist a database notification on the PaymentRequest for audit/UI scripts
        $paymentRequest->notify(new PaymentConfirmationNotification($paymentRequest));

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
}
