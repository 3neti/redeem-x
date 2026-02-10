<?php

namespace App\Pipelines\RedeemedVoucher;

use App\Jobs\SyncEnvelopeAndAttachImages;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * SyncEnvelopeData pipeline stage dispatches a job to:
 * 1. Sync form flow collected data to the voucher's envelope
 * 2. Attach KYC images (ID card, selfie) and map snapshot
 *
 * Runs asynchronously on 'high' queue to process promptly
 * before S3 signed URLs expire (~15 minutes).
 */
class SyncEnvelopeData
{
    /**
     * Dispatch async job for envelope sync and image attachment.
     *
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        $envelope = $voucher->envelope;

        if (! $envelope) {
            Log::debug('[SyncEnvelopeData] No envelope found; skipping', [
                'voucher' => $voucher->code,
            ]);

            return $next($voucher);
        }

        // Get redeemer metadata
        $redeemer = $voucher->redeemers->first();
        if (! $redeemer) {
            Log::debug('[SyncEnvelopeData] No redeemer found; skipping', [
                'voucher' => $voucher->code,
            ]);

            return $next($voucher);
        }

        // Extract collected data from inputs
        $inputs = $redeemer->metadata['redemption']['inputs'] ?? [];
        $collectedDataJson = $inputs['_form_flow_collected_data'] ?? null;

        if (! $collectedDataJson) {
            Log::debug('[SyncEnvelopeData] No form flow data found in inputs; skipping', [
                'voucher' => $voucher->code,
                'envelope_id' => $envelope->id,
            ]);

            return $next($voucher);
        }

        // Dispatch async job on high priority queue
        Log::info('[SyncEnvelopeData] Dispatching async job', [
            'voucher' => $voucher->code,
            'envelope_id' => $envelope->id,
        ]);

        SyncEnvelopeAndAttachImages::dispatch($voucher, $collectedDataJson)
            ->onQueue('high');

        return $next($voucher);
    }
}
