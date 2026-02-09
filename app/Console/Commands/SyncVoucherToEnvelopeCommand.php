<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SyncFormFlowDataToEnvelope;
use Illuminate\Console\Command;
use LBHurtado\SettlementEnvelope\Services\FormFlowDataMapper;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Sync voucher input data to its settlement envelope.
 *
 * Useful for:
 * - Testing the FormFlowDataMapper without creating new vouchers
 * - Retroactively syncing data for vouchers redeemed before envelope sync was implemented
 * - Debugging envelope payload/attachment issues
 */
class SyncVoucherToEnvelopeCommand extends Command
{
    protected $signature = 'voucher:sync-to-envelope
                            {code : The voucher code}
                            {--dry-run : Show what would be synced without making changes}
                            {--force : Overwrite existing envelope payload}';

    protected $description = 'Sync voucher persisted inputs to its settlement envelope';

    public function __construct(
        protected SyncFormFlowDataToEnvelope $syncAction,
        protected FormFlowDataMapper $mapper
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $code = strtoupper($this->argument('code'));
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Find voucher
        $voucher = Voucher::where('code', $code)->first();
        if (! $voucher) {
            $this->error("Voucher not found: {$code}");

            return self::FAILURE;
        }

        $this->info("Voucher: {$voucher->code}");
        $voucherType = $voucher->instructions->voucher_type;
        $this->line('  Type: '.($voucherType?->value ?? $voucherType ?? 'N/A'));
        $this->line('  Redeemed: '.($voucher->redeemed_at?->toDateTimeString() ?? 'No'));

        // Check envelope
        $envelope = $voucher->envelope;
        if (! $envelope) {
            $this->error('Voucher has no settlement envelope');

            return self::FAILURE;
        }

        $this->line("  Envelope ID: {$envelope->id}");
        $this->line("  Envelope Status: {$envelope->status->value}");
        $this->line("  Envelope Driver: {$envelope->driver_id}");

        // Check if envelope already has payload
        if (! empty($envelope->payload) && ! $force) {
            $this->warn('Envelope already has payload. Use --force to overwrite.');
            $this->line('  Current payload keys: '.implode(', ', array_keys($envelope->payload)));

            return self::FAILURE;
        }

        // Get persisted inputs from voucher
        $inputs = $voucher->inputs;
        if ($inputs->isEmpty()) {
            $this->warn('Voucher has no persisted inputs');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Persisted Inputs:');

        // Convert inputs to collected_data format (simulate form flow structure)
        $collectedData = $this->buildCollectedDataFromInputs($voucher);

        // Show what we found
        foreach ($collectedData as $stepName => $stepData) {
            $this->line("  [{$stepName}]");
            foreach ($stepData as $key => $value) {
                $displayValue = is_string($value) && strlen($value) > 50
                    ? substr($value, 0, 50).'... ('.strlen($value).' chars)'
                    : json_encode($value);
                $this->line("    {$key}: {$displayValue}");
            }
        }

        // Map to payload (for preview)
        $payload = $this->mapper->toPayload($collectedData);

        $this->newLine();
        $this->info('Mapped Payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Extract attachments (for preview)
        $attachments = $this->mapper->extractAttachments($collectedData);

        $this->newLine();
        $this->info('Attachments to upload: '.count($attachments));
        foreach ($attachments as $docType => $file) {
            $this->line("  - {$docType}: {$file->getClientOriginalName()} ({$file->getSize()} bytes)");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run - no changes made. Remove --dry-run to apply.');

            return self::SUCCESS;
        }

        // Apply changes using the shared action
        $this->newLine();
        $this->info('Applying changes...');

        $result = $this->syncAction->execute($voucher, $collectedData);

        if (! $result->success) {
            $this->error("  \u2717 {$result->error}");

            return self::FAILURE;
        }

        // Show results
        if ($result->payloadUpdated) {
            $this->line('  \u2713 Payload updated: '.implode(', ', $result->payloadKeys));
        }

        $this->line("  \u2713 Attachments uploaded: {$result->attachmentsUploaded}");

        if (! empty($result->attachmentErrors)) {
            foreach ($result->attachmentErrors as $docType => $error) {
                $this->error("  \u2717 {$docType}: {$error}");
            }
        }

        $this->newLine();
        $this->info('Sync completed!');

        // Show final state
        $envelope->refresh();
        $this->line('  Final payload keys: '.implode(', ', array_keys($envelope->payload ?? [])));
        $this->line('  Final status: '.$envelope->status->value);

        return self::SUCCESS;
    }

    /**
     * Build collected_data structure from voucher's persisted inputs.
     *
     * Maps flat input key-value pairs back into step-organized structure.
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
