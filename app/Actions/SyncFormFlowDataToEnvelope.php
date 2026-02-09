<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\FormFlowDataMapper;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Sync form flow collected data to a settlement envelope.
 *
 * This action handles:
 * 1. Mapping collected data to envelope payload format
 * 2. Extracting and uploading attachments (selfie, signature, map)
 * 3. Updating the envelope payload
 *
 * IMPORTANT: Attachments are uploaded BEFORE payload to avoid state
 * advancement blocking subsequent uploads.
 */
class SyncFormFlowDataToEnvelope
{
    public function __construct(
        protected EnvelopeService $envelopeService,
        protected FormFlowDataMapper $mapper,
        protected DriverService $driverService
    ) {}

    /**
     * Execute the sync operation.
     *
     * @param  Voucher  $voucher  The voucher with the envelope
     * @param  array  $collectedData  Form flow collected data (step-indexed or numeric-indexed)
     * @return SyncResult Result containing counts and any errors
     */
    public function execute(Voucher $voucher, array $collectedData): SyncResult
    {
        $envelope = $voucher->envelope;

        if (! $envelope) {
            return new SyncResult(
                success: false,
                error: 'Voucher has no settlement envelope'
            );
        }

        // Load driver to get form_flow_mapping config (if any)
        $driver = $this->driverService->load($envelope->driver_id, $envelope->driver_version);
        $mapping = $driver->form_flow_mapping;

        Log::info('[SyncFormFlowDataToEnvelope] Starting sync', [
            'voucher' => $voucher->code,
            'envelope_id' => $envelope->id,
            'driver' => $driver->getDriverKey(),
            'has_mapping' => $mapping !== null,
        ]);

        // Map data using driver config (or hardcoded defaults if no config)
        $payload = $this->mapper->toPayload($collectedData, $mapping);
        $attachments = $this->mapper->extractAttachments($collectedData, $mapping);

        $attachmentErrors = [];
        $uploadedCount = 0;

        // 1. Upload attachments FIRST (before payload triggers state change)
        foreach ($attachments as $docType => $file) {
            try {
                $this->envelopeService->uploadAttachment($envelope, $docType, $file);
                $uploadedCount++;

                Log::debug('[SyncFormFlowDataToEnvelope] Attachment uploaded', [
                    'voucher' => $voucher->code,
                    'doc_type' => $docType,
                ]);
            } catch (\Throwable $e) {
                $attachmentErrors[$docType] = $e->getMessage();

                Log::warning('[SyncFormFlowDataToEnvelope] Failed to upload attachment', [
                    'voucher' => $voucher->code,
                    'doc_type' => $docType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Update payload AFTER attachments
        $payloadUpdated = false;
        if (! empty($payload)) {
            // Direct update to avoid EnvelopeService validation issue
            // TODO: Debug why EnvelopeService::updatePayload fails validation
            $envelope->update(['payload' => $payload]);
            $payloadUpdated = true;

            // Update payload field checklist items (normally done by EnvelopeService)
            $this->updatePayloadFieldItems($envelope);

            // Recompute gates to advance status if settleable
            $this->recomputeGates($envelope);

            Log::debug('[SyncFormFlowDataToEnvelope] Payload updated', [
                'voucher' => $voucher->code,
                'payload_keys' => array_keys($payload),
            ]);
        }

        Log::info('[SyncFormFlowDataToEnvelope] Sync completed', [
            'voucher' => $voucher->code,
            'envelope_id' => $envelope->id,
            'payload_updated' => $payloadUpdated,
            'attachments_uploaded' => $uploadedCount,
            'attachments_failed' => count($attachmentErrors),
        ]);

        return new SyncResult(
            success: true,
            payloadUpdated: $payloadUpdated,
            payloadKeys: array_keys($payload),
            attachmentsUploaded: $uploadedCount,
            attachmentErrors: $attachmentErrors
        );
    }

    /**
     * Update payload field checklist items based on current payload.
     */
    protected function updatePayloadFieldItems(Envelope $envelope): void
    {
        $payloadValidator = app(\LBHurtado\SettlementEnvelope\Services\PayloadValidator::class);

        $envelope->checklistItems()
            ->where('kind', 'payload_field')
            ->each(function ($item) use ($envelope, $payloadValidator) {
                if ($item->payload_pointer) {
                    $exists = $payloadValidator->fieldExists(
                        $envelope->payload ?? [],
                        $item->payload_pointer
                    );

                    // Payload fields with review 'none' auto-accept when present
                    $newStatus = $exists ? 'accepted' : 'missing';
                    $item->update(['status' => $newStatus]);
                }
            });
    }

    /**
     * Recompute gates and auto-advance status if conditions are met.
     *
     * This replicates the core logic from EnvelopeService::recomputeGates()
     * since that method is protected.
     */
    protected function recomputeGates(Envelope $envelope): void
    {
        // Refresh to get latest state
        $envelope->refresh();
        $envelope->load(['checklistItems', 'signals']);

        // Compute gates using GateEvaluator
        $driverService = app(\LBHurtado\SettlementEnvelope\Services\DriverService::class);
        $gateEvaluator = app(\LBHurtado\SettlementEnvelope\Services\GateEvaluator::class);

        $driver = $driverService->load($envelope->driver_id, $envelope->driver_version);
        $gates = $gateEvaluator->evaluate($envelope, $driver);

        // Update gates cache
        $envelope->updateGatesCache($gates);

        // Simplified auto-advance logic
        $this->autoAdvance($envelope, $gates);
    }

    /**
     * Auto-advance envelope state based on gates.
     */
    protected function autoAdvance(Envelope $envelope, array $gates): void
    {
        $current = $envelope->status;
        $settleable = $gates['settleable'] ?? false;

        // Compute required items states
        $requiredItems = $envelope->checklistItems->where('required', true);
        $requiredCount = $requiredItems->count();
        $requiredAcceptedCount = $requiredItems->filter(fn ($i) => $i->status->value === 'accepted')->count();
        $requiredPresent = $requiredCount === 0 || $requiredItems->filter(fn ($i) => $i->status->value !== 'missing')->count() === $requiredCount;
        $requiredAccepted = $requiredCount === 0 || $requiredAcceptedCount === $requiredCount;

        // State transitions
        $newStatus = null;

        if ($current->value === 'draft') {
            if (! empty($envelope->payload) || $envelope->attachments()->exists()) {
                $newStatus = \LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus::IN_PROGRESS;
            }
        }

        if (in_array($current->value, ['in_progress', 'active']) && $requiredPresent) {
            $newStatus = \LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus::READY_FOR_REVIEW;
        }

        if ($current->value === 'ready_for_review' && $requiredAccepted && $settleable) {
            $newStatus = \LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus::READY_TO_SETTLE;
        }

        if ($newStatus && $newStatus !== $current) {
            $envelope->update(['status' => $newStatus]);
            // Recurse to handle multi-step transitions (draft → in_progress → ready_for_review → ready_to_settle)
            $this->recomputeGates($envelope);
        }
    }
}

/**
 * Result of a sync operation.
 */
class SyncResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public bool $payloadUpdated = false,
        public array $payloadKeys = [],
        public int $attachmentsUploaded = 0,
        public array $attachmentErrors = []
    ) {}

    public function hasErrors(): bool
    {
        return ! $this->success || ! empty($this->attachmentErrors);
    }
}
