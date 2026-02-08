<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Migrate existing payable/settlement vouchers to use settlement envelopes.
 *
 * This command creates envelopes for existing vouchers that were created
 * before the envelope system was implemented. It:
 * - Creates a payable.default envelope for each qualifying voucher
 * - Copies external_metadata to envelope payload
 * - Copies voucher_attachments to envelope documents
 * - Preserves original voucher data (non-destructive)
 *
 * @deprecated The voucher external_metadata and voucher_attachments fields
 *             are deprecated. New vouchers automatically create envelopes.
 */
class MigrateVouchersToEnvelopes extends Command
{
    protected $signature = 'vouchers:migrate-to-envelopes
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Skip confirmation prompt}
                            {--code= : Migrate a specific voucher code}
                            {--limit= : Limit number of vouchers to migrate}
                            {--skip-attachments : Skip copying attachments to envelope}';

    protected $description = 'Migrate existing payable/settlement vouchers to use settlement envelopes';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $errors = 0;

    public function handle(): int
    {
        $this->info('');
        $this->info('=== Voucher to Envelope Migration ===');
        $this->info('');

        // Build query
        $query = Voucher::query()
            ->whereIn('voucher_type', [VoucherType::PAYABLE, VoucherType::SETTLEMENT])
            ->whereDoesntHave('envelope') // Only vouchers without envelopes
            ->orderBy('created_at');

        // Apply filters
        if ($code = $this->option('code')) {
            $query->where('code', strtoupper($code));
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $vouchers = $query->get();
        $total = $vouchers->count();

        if ($total === 0) {
            $this->info('No vouchers found that need migration.');

            return self::SUCCESS;
        }

        $this->info("Found {$total} voucher(s) to migrate.");
        $this->newLine();

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Would migrate:');
            $this->newLine();

            foreach ($vouchers as $voucher) {
                $this->displayVoucherInfo($voucher);
            }

            $this->newLine();
            $this->info("Run without --dry-run to perform the migration.");

            return self::SUCCESS;
        }

        // Confirm unless --force
        if (! $this->option('force')) {
            if (! $this->confirm("Proceed with migrating {$total} voucher(s)?")) {
                $this->info('Migration cancelled.');

                return self::SUCCESS;
            }
        }

        // Perform migration
        $this->info('Starting migration...');
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($vouchers as $voucher) {
            try {
                $this->migrateVoucher($voucher);
                $this->migrated++;
            } catch (\Exception $e) {
                $this->errors++;
                \Log::error('[MigrateVouchersToEnvelopes] Failed to migrate voucher', [
                    'voucher_code' => $voucher->code,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Migration Complete ===');
        $this->info("Migrated: {$this->migrated}");
        $this->info("Skipped:  {$this->skipped}");

        if ($this->errors > 0) {
            $this->error("Errors:   {$this->errors}");
            $this->info('Check logs for error details.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function displayVoucherInfo(Voucher $voucher): void
    {
        $hasMetadata = ! empty($voucher->external_metadata);
        $attachmentCount = $voucher->getMedia('voucher_attachments')->count();

        $this->line("  [{$voucher->code}]");
        $this->line("    Type: {$voucher->voucher_type->value}");
        $this->line("    Created: {$voucher->created_at->toDateTimeString()}");
        $this->line("    Has external_metadata: ".($hasMetadata ? 'Yes' : 'No'));
        $this->line("    Attachments: {$attachmentCount}");
        $this->newLine();
    }

    private function migrateVoucher(Voucher $voucher): void
    {
        // Double-check voucher doesn't already have an envelope (race condition protection)
        if ($voucher->envelope()->exists()) {
            $this->skipped++;

            return;
        }

        // Get data to migrate
        $externalMetadata = $voucher->external_metadata;
        $attachments = $voucher->getMedia('voucher_attachments');

        // Create envelope with default payable driver
        $envelope = $voucher->createEnvelope(
            driverId: 'payable.default',
            driverVersion: '1.0.0',
            initialPayload: $externalMetadata,
            context: [
                'created_via' => 'migration_command',
                'migrated_at' => now()->toIso8601String(),
                'voucher_type' => $voucher->voucher_type->value,
                'original_attachments_count' => $attachments->count(),
            ],
            actor: null // System migration, no actor
        );

        // Copy attachments to envelope (unless skipped)
        if (! $this->option('skip-attachments') && $attachments->isNotEmpty()) {
            $envelopeService = app(EnvelopeService::class);
            foreach ($attachments as $media) {
                try {
                    // Get the file path from media
                    $filePath = $media->getPath();

                    if (file_exists($filePath)) {
                        // Create uploaded file from existing media
                        $uploadedFile = new \Illuminate\Http\UploadedFile(
                            $filePath,
                            $media->file_name,
                            $media->mime_type,
                            null,
                            true // test mode - allows non-uploaded files
                        );

                        // Upload to envelope via EnvelopeService
                        $envelopeService->uploadAttachment(
                            envelope: $envelope,
                            docType: 'REFERENCE_DOC',
                            file: $uploadedFile,
                            actor: null
                        );
                    }
                } catch (\Exception $e) {
                    \Log::warning('[MigrateVouchersToEnvelopes] Failed to copy attachment', [
                        'voucher_code' => $voucher->code,
                        'media_id' => $media->id,
                        'file_name' => $media->file_name,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other attachments
                }
            }
        }

        \Log::info('[MigrateVouchersToEnvelopes] Migrated voucher to envelope', [
            'voucher_code' => $voucher->code,
            'envelope_id' => $envelope->id,
            'has_payload' => ! empty($externalMetadata),
            'attachments_copied' => $attachments->count(),
        ]);
    }
}
