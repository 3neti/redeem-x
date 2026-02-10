<?php

declare(strict_types=1);

namespace App\Actions\Envelope;

use App\Events\FormFlowCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Actions\SyncFormFlowToEnvelope;
use LBHurtado\SettlementEnvelope\Data\FormFlowSyncResultData;
use LBHurtado\SettlementEnvelope\Services\FormFlowDataMapper;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sync form flow collected data to a voucher's settlement envelope.
 *
 * This action consolidates:
 * - Direct invocation via handle()
 * - Async job dispatch via asJob()
 * - Event listening via asListener()
 * - Artisan command via asCommand()
 */
class SyncFormFlowData
{
    use AsAction;

    public string $commandSignature = 'voucher:sync-to-envelope
                            {code : The voucher code}
                            {--dry-run : Show what would be synced without making changes}
                            {--force : Overwrite existing envelope payload}';

    public string $commandDescription = 'Sync voucher persisted inputs to its settlement envelope';

    public function __construct(
        protected SyncFormFlowToEnvelope $syncAction,
        protected FormFlowDataMapper $mapper
    ) {}

    /**
     * Core logic - sync collected data to voucher's envelope.
     */
    public function handle(Voucher $voucher, array $collectedData): FormFlowSyncResultData
    {
        $envelope = $voucher->envelope;

        if (! $envelope) {
            return FormFlowSyncResultData::failure('Voucher has no settlement envelope');
        }

        return $this->syncAction->execute($envelope, $collectedData, $voucher->code);
    }

    /**
     * Job execution - called when dispatched asynchronously.
     */
    public function asJob(Voucher $voucher, array $collectedData): void
    {
        Log::info('[SyncFormFlowData] Job started', [
            'voucher' => $voucher->code,
        ]);

        try {
            $result = $this->handle($voucher, $collectedData);

            if ($result->hasErrors()) {
                Log::warning('[SyncFormFlowData] Sync completed with errors', [
                    'voucher' => $voucher->code,
                    'attachment_errors' => $result->attachmentErrors,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[SyncFormFlowData] Failed to sync form flow data', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger queue retry
        }
    }

    /**
     * Event listener - dispatches job for async processing.
     */
    public function asListener(FormFlowCompleted $event): void
    {
        Log::info('[SyncFormFlowData] Form flow completed, dispatching job', [
            'voucher' => $event->voucher->code,
            'flow_id' => $event->flowId,
        ]);

        static::dispatch($event->voucher, $event->collectedData);
    }

    /**
     * Artisan command - sync voucher by code.
     */
    public function asCommand(Command $command): int
    {
        $code = strtoupper($command->argument('code'));
        $dryRun = $command->option('dry-run');
        $force = $command->option('force');

        // Find voucher
        $voucher = Voucher::where('code', $code)->first();
        if (! $voucher) {
            $command->error("Voucher not found: {$code}");

            return Command::FAILURE;
        }

        $command->info("Voucher: {$voucher->code}");
        $voucherType = $voucher->instructions->voucher_type;
        $command->line('  Type: '.($voucherType?->value ?? $voucherType ?? 'N/A'));
        $command->line('  Redeemed: '.($voucher->redeemed_at?->toDateTimeString() ?? 'No'));

        // Check envelope
        $envelope = $voucher->envelope;
        if (! $envelope) {
            $command->error('Voucher has no settlement envelope');

            return Command::FAILURE;
        }

        $command->line("  Envelope ID: {$envelope->id}");
        $command->line("  Envelope Status: {$envelope->status->value}");
        $command->line("  Envelope Driver: {$envelope->driver_id}");

        // Check if envelope already has payload
        if (! empty($envelope->payload) && ! $force) {
            $command->warn('Envelope already has payload. Use --force to overwrite.');
            $command->line('  Current payload keys: '.implode(', ', array_keys($envelope->payload)));

            return Command::FAILURE;
        }

        // Get persisted inputs from voucher
        $inputs = $voucher->inputs;
        if ($inputs->isEmpty()) {
            $command->warn('Voucher has no persisted inputs');

            return Command::SUCCESS;
        }

        $command->newLine();
        $command->info('Persisted Inputs:');

        // Convert inputs to collected_data format
        $collectedData = $this->buildCollectedDataFromInputs($voucher);

        // Show what we found
        foreach ($collectedData as $stepName => $stepData) {
            $command->line("  [{$stepName}]");
            foreach ($stepData as $key => $value) {
                $displayValue = is_string($value) && strlen($value) > 50
                    ? substr($value, 0, 50).'... ('.strlen($value).' chars)'
                    : json_encode($value);
                $command->line("    {$key}: {$displayValue}");
            }
        }

        // Map to payload (for preview)
        $payload = $this->mapper->toPayload($collectedData);

        $command->newLine();
        $command->info('Mapped Payload:');
        $command->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Extract attachments (for preview)
        $attachments = $this->mapper->extractAttachments($collectedData);

        $command->newLine();
        $command->info('Attachments to upload: '.count($attachments));
        foreach ($attachments as $docType => $file) {
            $command->line("  - {$docType}: {$file->getClientOriginalName()} ({$file->getSize()} bytes)");
        }

        if ($dryRun) {
            $command->newLine();
            $command->warn('Dry run - no changes made. Remove --dry-run to apply.');

            return Command::SUCCESS;
        }

        // Apply changes
        $command->newLine();
        $command->info('Applying changes...');

        $result = $this->handle($voucher, $collectedData);

        if (! $result->success) {
            $command->error("  ✗ {$result->error}");

            return Command::FAILURE;
        }

        // Show results
        if ($result->payloadUpdated) {
            $command->line('  ✓ Payload updated: '.implode(', ', $result->payloadKeys));
        }

        $command->line("  ✓ Attachments uploaded: {$result->attachmentsUploaded}");

        if (! empty($result->attachmentErrors)) {
            foreach ($result->attachmentErrors as $docType => $error) {
                $command->error("  ✗ {$docType}: {$error}");
            }
        }

        $command->newLine();
        $command->info('Sync completed!');

        // Show final state
        $envelope->refresh();
        $command->line('  Final payload keys: '.implode(', ', array_keys($envelope->payload ?? [])));
        $command->line('  Final status: '.$envelope->status->value);

        return Command::SUCCESS;
    }

    /**
     * Build collected_data structure from voucher's persisted inputs.
     */
    protected function buildCollectedDataFromInputs(Voucher $voucher): array
    {
        $inputs = $voucher->inputs->pluck('value', 'name')->toArray();

        // Organize inputs by step based on known field mappings
        $steps = [
            'wallet_info' => ['mobile', 'bank_code', 'account_number', 'settlement_rail', 'amount'],
            'bio_fields' => ['full_name', 'name', 'email', 'birth_date', 'address', 'reference_code', 'gross_monthly_income'],
            'kyc_verification' => ['status', 'transaction_id', 'id_front', 'id_back', 'kyc_selfie'],
            'location_capture' => ['latitude', 'longitude', 'accuracy', 'timestamp', 'map', 'formatted_address'],
            'selfie_capture' => ['selfie', 'width', 'height', 'format'],
            'signature_capture' => ['signature'],
            'splash_page' => ['splash_viewed', 'viewed_at'],
        ];

        $collectedData = [];

        foreach ($steps as $stepName => $fields) {
            $stepData = [];
            foreach ($fields as $field) {
                if (isset($inputs[$field])) {
                    $stepData[$field] = $inputs[$field];
                }
            }
            if (! empty($stepData)) {
                $stepData['_step_name'] = $stepName;
                $collectedData[$stepName] = $stepData;
            }
        }

        // Handle any remaining inputs not in known steps
        $knownFields = array_merge(...array_values($steps));
        $unknownInputs = array_diff_key($inputs, array_flip($knownFields));
        if (! empty($unknownInputs)) {
            $collectedData['_unknown'] = $unknownInputs;
        }

        return $collectedData;
    }
}
