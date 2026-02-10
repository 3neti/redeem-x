<?php

namespace App\Jobs;

use App\Actions\Envelope\AttachKYCImagesToEnvelope;
use App\Actions\Envelope\AttachMapSnapshotToEnvelope;
use App\Actions\Envelope\SyncFormFlowData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Sync form flow data to envelope and attach images.
 *
 * This job handles:
 * 1. Syncing collected form flow data to the envelope payload
 * 2. Attaching KYC images (ID card, selfie) from S3 URLs
 * 3. Generating and attaching map snapshot from coordinates
 *
 * Runs on 'high' queue to process promptly before S3 URLs expire (~15 min).
 */
class SyncEnvelopeAndAttachImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Voucher $voucher,
        public string $collectedDataJson
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $envelope = $this->voucher->envelope;

        if (! $envelope) {
            Log::warning('[SyncEnvelopeAndAttachImages] No envelope found', [
                'voucher' => $this->voucher->code,
            ]);

            return;
        }

        Log::info('[SyncEnvelopeAndAttachImages] Starting job', [
            'voucher' => $this->voucher->code,
            'envelope_id' => $envelope->id,
        ]);

        // Step 1: Decode and validate collected data
        $collectedData = json_decode($this->collectedDataJson, true);

        if (empty($collectedData) || ! is_array($collectedData)) {
            Log::warning('[SyncEnvelopeAndAttachImages] Invalid collected data', [
                'voucher' => $this->voucher->code,
            ]);

            return;
        }

        // Step 2: Sync form flow data to envelope
        Log::info('[SyncEnvelopeAndAttachImages] Syncing form flow data', [
            'voucher' => $this->voucher->code,
            'collected_data_keys' => array_keys($collectedData),
        ]);

        $result = SyncFormFlowData::run($this->voucher, $collectedData);

        if (! $result->success) {
            Log::error('[SyncEnvelopeAndAttachImages] Sync failed', [
                'voucher' => $this->voucher->code,
                'error' => $result->error,
            ]);

            // Don't continue if sync failed
            return;
        }

        Log::info('[SyncEnvelopeAndAttachImages] Sync completed', [
            'voucher' => $this->voucher->code,
            'payload_updated' => $result->payloadUpdated,
            'attachments_uploaded' => $result->attachmentsUploaded,
        ]);

        // Refresh envelope to get updated payload
        $envelope->refresh();
        $payload = $envelope->payload ?? [];

        // Step 3: Attach KYC images if URLs exist
        $hasKycUrls = ! empty($payload['kyc']['id_card_full_url'])
                   || ! empty($payload['kyc']['selfie_url']);

        if ($hasKycUrls) {
            Log::info('[SyncEnvelopeAndAttachImages] Attaching KYC images', [
                'voucher' => $this->voucher->code,
            ]);

            $kycAttached = AttachKYCImagesToEnvelope::run($this->voucher, null, sync: true);

            Log::info('[SyncEnvelopeAndAttachImages] KYC images attached', [
                'voucher' => $this->voucher->code,
                'count' => $kycAttached,
            ]);
        }

        // Step 4: Generate and attach map snapshot if location exists
        $hasLocation = ! empty($payload['location']['latitude'])
                    && ! empty($payload['location']['longitude']);

        if ($hasLocation) {
            Log::info('[SyncEnvelopeAndAttachImages] Generating map snapshot', [
                'voucher' => $this->voucher->code,
            ]);

            $mapAttachment = AttachMapSnapshotToEnvelope::run($this->voucher);

            if ($mapAttachment) {
                Log::info('[SyncEnvelopeAndAttachImages] Map snapshot attached', [
                    'voucher' => $this->voucher->code,
                    'attachment_id' => $mapAttachment->id,
                ]);
            }
        }

        Log::info('[SyncEnvelopeAndAttachImages] Job completed', [
            'voucher' => $this->voucher->code,
            'envelope_id' => $envelope->id,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[SyncEnvelopeAndAttachImages] Job failed', [
            'voucher' => $this->voucher->code,
            'error' => $exception->getMessage(),
        ]);
    }
}
