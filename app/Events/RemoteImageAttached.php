<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;

/**
 * Dispatched when a remote image has been downloaded and attached to an envelope.
 */
class RemoteImageAttached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Envelope $envelope,
        public EnvelopeAttachment $attachment,
        public string $sourceUrl,
        public string $docType
    ) {}
}
