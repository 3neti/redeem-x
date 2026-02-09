<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Event fired when a form flow is completed for voucher redemption.
 *
 * This event is dispatched by DisburseController::complete() when the
 * form flow callback is received. Listeners can use this to sync
 * collected data to settlement envelopes, trigger notifications, etc.
 */
class FormFlowCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public array $collectedData,
        public string $flowId,
        public string $completedAt
    ) {}
}
