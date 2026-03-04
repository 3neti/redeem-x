<?php

namespace LBHurtado\Voucher\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\Voucher\Models\Voucher;

class DisburseInputPrepared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public DisburseInputData $input,
    ) {}
}
