<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentDetectedButNotConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $paymentRequestId,
        public string $payerMobile,
        public float $amount,
        public string $voucherCode,
    ) {}
}
