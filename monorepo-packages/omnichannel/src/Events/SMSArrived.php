<?php

namespace LBHurtado\OmniChannel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\OmniChannel\Data\SMSData;

class SMSArrived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SMSData $data) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
