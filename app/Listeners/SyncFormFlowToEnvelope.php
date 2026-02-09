<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\SyncFormFlowDataToEnvelope;
use App\Events\FormFlowCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sync form flow collected data to settlement envelope.
 *
 * This listener is triggered when a form flow completes for voucher redemption.
 * It delegates to the SyncFormFlowDataToEnvelope action.
 *
 * Runs on queue to avoid blocking the form flow callback response.
 */
class SyncFormFlowToEnvelope implements ShouldQueue
{
    public function __construct(
        protected SyncFormFlowDataToEnvelope $syncAction
    ) {}

    public function handle(FormFlowCompleted $event): void
    {
        Log::info('[SyncFormFlowToEnvelope] Form flow completed, triggering sync', [
            'voucher' => $event->voucher->code,
            'flow_id' => $event->flowId,
        ]);

        try {
            $result = $this->syncAction->execute($event->voucher, $event->collectedData);

            if ($result->hasErrors()) {
                Log::warning('[SyncFormFlowToEnvelope] Sync completed with errors', [
                    'voucher' => $event->voucher->code,
                    'attachment_errors' => $result->attachmentErrors,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[SyncFormFlowToEnvelope] Failed to sync form flow data', [
                'voucher' => $event->voucher->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger queue retry
        }
    }
}
