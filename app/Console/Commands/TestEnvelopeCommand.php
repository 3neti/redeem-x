<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

class TestEnvelopeCommand extends Command
{
    protected $signature = 'test:envelope
        {--voucher= : Voucher code (generates fresh test voucher if not specified)}
        {--user=lester@hurtado.ph : User email for voucher ownership}
        {--type=settlement : Voucher type (redeemable|payable|settlement)}
        {--driver=simple.envelope : Driver ID}
        {--driver-version=1.0.0 : Driver version}
        {--lifecycle=partial : Run full or partial lifecycle (full|partial)}
        {--upload-doc : Upload test document attachment}
        {--with-context : Update envelope context/metadata}
        {--auto-settle : Automatically lock and settle when settleable}
        {--detailed : Show detailed output including payload versions}';

    protected $description = 'Test settlement envelope lifecycle with a voucher';

    public function handle(EnvelopeService $envelopeService): int
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Settlement Envelope Lifecycle Test');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 1: SETUP
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('SETUP');

        $voucherCode = $this->option('voucher');
        if ($voucherCode) {
            $voucher = Voucher::where('code', $voucherCode)->first();
            if (!$voucher) {
                $this->error("Voucher not found: {$voucherCode}");
                return self::FAILURE;
            }
            $this->line("Using existing voucher: {$voucher->code}");
        } else {
            $userEmail = $this->option('user');
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                $this->error("User not found: {$userEmail}");
                return self::FAILURE;
            }
            Auth::login($user);
            $this->line("✓ Authenticated as: {$user->email}");

            $voucherType = VoucherType::from($this->option('type'));
            $instructions = VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 1000,
                    'currency' => 'PHP',
                    'validation' => [],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
                'count' => 1,
                'voucher_type' => $voucherType,
                'target_amount' => $voucherType !== VoucherType::REDEEMABLE ? 1000.0 : null,
            ]);
            $vouchers = GenerateVouchers::run($instructions);
            $voucher = $vouchers->first();
            $this->line("✓ Generated voucher: {$voucher->code} (type: {$voucherType->value})");
        }

        $driverId = $this->option('driver');
        $driverVersion = $this->option('driver-version');
        $this->line("✓ Driver: {$driverId}@{$driverVersion}");
        $this->showOptions();
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 2: CREATE ENVELOPE
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('CREATE ENVELOPE');

        try {
            $envelope = $voucher->createEnvelope(
                driverId: $driverId,
                driverVersion: $driverVersion,
                initialPayload: ['name' => 'Test User']
            );
            $this->step("Envelope created: {$envelope->reference_code}");
            $this->detail("Status: {$envelope->status->value}");
            $this->detail("Payload version: {$envelope->payload_version}");
        } catch (\Exception $e) {
            $this->error("✗ Failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->showChecklist($envelope);

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 3: EVIDENCE COLLECTION
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('EVIDENCE COLLECTION');

        // Update payload
        try {
            $envelope = $envelopeService->updatePayload($envelope, [
                'reference_code' => $voucher->code,
                'amount' => 1000,
                'notes' => 'Test envelope via artisan command',
            ]);
            $this->step("Payload updated (v{$envelope->payload_version})");
            if ($this->option('detailed')) {
                $this->detail("Payload: " . json_encode($envelope->payload));
            }
        } catch (\Exception $e) {
            $this->error("✗ Payload update failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Upload document
        if ($this->option('upload-doc')) {
            try {
                $attachment = $this->uploadTestDocument($envelope, $envelopeService);
                $this->step("Document uploaded: {$attachment->original_filename}");
                $this->detail("Type: {$attachment->doc_type}, Size: " . number_format($attachment->size) . " bytes");
                $this->detail("Review status: {$attachment->review_status}");

                // Review attachment if needed
                $checklistItem = $envelope->checklistItems()->where('doc_type', $attachment->doc_type)->first();
                if ($checklistItem && $checklistItem->review_mode !== 'none') {
                    $envelopeService->reviewAttachment($attachment, 'accepted', Auth::user());
                    $this->step("Document reviewed: accepted");
                }

                $envelope->refresh();
                $this->showChecklist($envelope);
            } catch (\Exception $e) {
                $this->warn("⚠ Document upload skipped: {$e->getMessage()}");
            }
        }

        // Update context
        if ($this->option('with-context')) {
            try {
                $envelope = $envelopeService->updateContext($envelope, [
                    'source' => 'test:envelope command',
                    'test_timestamp' => now()->toIso8601String(),
                    'environment' => app()->environment(),
                    'notes' => 'Automated test run',
                ]);
                $this->step("Context updated");
                if ($this->option('detailed')) {
                    $this->detail("Context: " . json_encode($envelope->context));
                }
            } catch (\Exception $e) {
                $this->warn("⚠ Context update failed: {$e->getMessage()}");
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 4: SIGNALS
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('SIGNALS');

        $this->showSignals($envelope, 'Before');

        try {
            $envelopeService->setSignal($envelope, 'approved', true);
            $envelope->refresh();
            $this->step("Signal set: approved = true");
        } catch (\Exception $e) {
            $this->error("✗ Signal set failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->showSignals($envelope, 'After');

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 5: GATES
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('GATES');

        $gates = $envelopeService->computeGates($envelope);
        foreach ($gates as $gate => $value) {
            $icon = $value ? '✓' : '✗';
            $this->line("  {$icon} {$gate}: " . ($value ? 'true' : 'false'));
        }
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 6: STATUS TRANSITIONS (if full lifecycle)
        // ─────────────────────────────────────────────────────────────────────
        $isFullLifecycle = $this->option('lifecycle') === 'full';
        $autoSettle = $this->option('auto-settle');

        // Show current state (auto-transitions already happened)
        $this->phase('STATE MACHINE');
        $this->showStateProgress($envelope);

        if ($isFullLifecycle || $autoSettle) {
            $this->phase('STATUS TRANSITIONS');

            // Note: DRAFT → IN_PROGRESS → READY_FOR_REVIEW → READY_TO_SETTLE
            // happens automatically via auto-transitions in EnvelopeService
            
            $envelope->refresh();
            $this->step("Current state: {$envelope->status->label()}");

            // Lock (requires READY_TO_SETTLE state)
            if ($envelope->status === EnvelopeStatus::READY_TO_SETTLE) {
                try {
                    $envelope = $envelopeService->lock($envelope);
                    $this->step("Locked: ready_to_settle → locked");
                    $this->detail("Locked at: {$envelope->locked_at}");
                } catch (\Exception $e) {
                    $this->warn("⚠ Lock failed: {$e->getMessage()}");
                }
            } elseif (!in_array($envelope->status, [EnvelopeStatus::LOCKED, EnvelopeStatus::SETTLED])) {
                $this->warn("⚠ Cannot lock: envelope must be in READY_TO_SETTLE state (current: {$envelope->status->value})");
                $this->detail("Missing requirements:");
                
                // Show what's missing
                $requiredItems = $envelope->checklistItems->where('required', true);
                $missingItems = $requiredItems->filter(fn($item) => $item->status->value === 'missing');
                $pendingReview = $requiredItems->filter(fn($item) => in_array($item->status->value, ['uploaded', 'needs_review']));
                
                if ($missingItems->count() > 0) {
                    $this->detail("  - Missing items: " . $missingItems->pluck('label')->join(', '));
                }
                if ($pendingReview->count() > 0) {
                    $this->detail("  - Pending review: " . $pendingReview->pluck('label')->join(', '));
                }
                if (!$envelope->isSettleable()) {
                    $this->detail("  - Settleable gate: false");
                }
            }

            // Settle (requires LOCKED state)
            if ($envelope->status === EnvelopeStatus::LOCKED) {
                try {
                    $envelope = $envelopeService->settle($envelope);
                    $this->step("Settled: locked → settled");
                    $this->detail("Settled at: {$envelope->settled_at}");
                } catch (\Exception $e) {
                    $this->warn("⚠ Settlement failed: {$e->getMessage()}");
                }
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 7: AUDIT LOG
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('AUDIT LOG');

        $envelope->load('auditLogs');
        foreach ($envelope->auditLogs->reverse() as $log) {
            $this->line("  [{$log->created_at->format('H:i:s')}] {$log->action}");
        }
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 8: PAYLOAD VERSIONS (if verbose)
        // ─────────────────────────────────────────────────────────────────────
        if ($this->option('detailed')) {
            $this->phase('PAYLOAD VERSIONS');

            $envelope->load('payloadVersions');
            foreach ($envelope->payloadVersions as $version) {
                $this->line("  v{$version->version} [{$version->created_at->format('H:i:s')}]");
                if ($version->patch) {
                    $this->detail("Patch: " . json_encode($version->patch));
                }
            }
            $this->newLine();
        }

        // ─────────────────────────────────────────────────────────────────────
        // SUMMARY
        // ─────────────────────────────────────────────────────────────────────
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════');

        $summaryData = [
            ['Reference Code', $envelope->reference_code],
            ['Driver', "{$envelope->driver_id}@{$envelope->driver_version}"],
            ['Status', $envelope->status->value],
            ['Payload Version', $envelope->payload_version],
            ['Settleable', $envelope->isSettleable() ? 'Yes' : 'No'],
            ['Checklist Items', $envelope->checklistItems->count()],
            ['Attachments', $envelope->attachments()->count()],
            ['Audit Entries', $envelope->auditLogs->count()],
        ];

        if ($envelope->context) {
            $summaryData[] = ['Context Keys', implode(', ', array_keys($envelope->context))];
        }

        if ($envelope->locked_at) {
            $summaryData[] = ['Locked At', $envelope->locked_at->format('Y-m-d H:i:s')];
        }

        if ($envelope->settled_at) {
            $summaryData[] = ['Settled At', $envelope->settled_at->format('Y-m-d H:i:s')];
        }

        $this->table(['Property', 'Value'], $summaryData);

        $this->newLine();
        $statusIcon = $envelope->status === EnvelopeStatus::SETTLED ? '✓✓✓' : '✓';
        $this->info("{$statusIcon} Test completed!");

        return self::SUCCESS;
    }

    private function phase(string $title): void
    {
        $this->info("─── {$title} " . str_repeat('─', 55 - strlen($title)));
    }

    private function step(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
    }

    private function detail(string $message): void
    {
        $this->line("    <fg=gray>{$message}</>");
    }

    private function showOptions(): void
    {
        $options = [];
        if ($this->option('lifecycle') === 'full') $options[] = 'full-lifecycle';
        if ($this->option('upload-doc')) $options[] = 'upload-doc';
        if ($this->option('with-context')) $options[] = 'with-context';
        if ($this->option('auto-settle')) $options[] = 'auto-settle';
        if ($this->option('detailed')) $options[] = 'detailed';

        if ($options) {
            $this->line("✓ Options: " . implode(', ', $options));
        }
    }

    private function showChecklist($envelope): void
    {
        $this->line("  Checklist:");
        foreach ($envelope->checklistItems as $item) {
            $icon = match ($item->status->value) {
                'accepted' => '<fg=green>✓</>',
                'rejected' => '<fg=red>✗</>',
                default => '<fg=yellow>○</>',
            };
            $required = $item->required ? '' : ' <fg=gray>(optional)</>';
            $this->line("    {$icon} {$item->label}{$required}");
        }
        $this->newLine();
    }

    private function showSignals($envelope, string $label): void
    {
        $this->line("  {$label}:");
        foreach ($envelope->signals as $signal) {
            $icon = $signal->value === 'true' ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("    {$icon} {$signal->key}: {$signal->value}");
        }
    }

    private function showStateProgress($envelope): void
    {
        $states = [
            'draft' => 'DRAFT',
            'in_progress' => 'IN_PROGRESS', 
            'ready_for_review' => 'READY_FOR_REVIEW',
            'ready_to_settle' => 'READY_TO_SETTLE',
            'locked' => 'LOCKED',
            'settled' => 'SETTLED',
        ];
        
        $current = $envelope->status->value;
        $stateKeys = array_keys($states);
        $currentIndex = array_search($current, $stateKeys);
        
        $this->line("  State Flow:");
        $line = '    ';
        foreach ($states as $key => $label) {
            $index = array_search($key, $stateKeys);
            
            if ($key === $current) {
                $line .= "<fg=green;options=bold>[{$label}]</> ";
            } elseif ($currentIndex !== false && $index < $currentIndex) {
                $line .= "<fg=gray>{$label}</> → ";
            } else {
                $line .= "<fg=gray>{$label}</> → ";
            }
        }
        $line = rtrim($line, ' → ');
        $this->line($line);
        $this->newLine();
    }

    private function uploadTestDocument($envelope, EnvelopeService $envelopeService)
    {
        // Determine document type based on driver
        $docType = $envelope->driver_id === 'vendor.pay-by-face' ? 'FACE_PHOTO' : 'TEST_DOC';

        // Use favicon.png for visual confirmation during testing
        $sourcePath = public_path('favicon.png');
        if (!file_exists($sourcePath)) {
            // Fallback to base64 fixture if favicon doesn't exist
            $testImagePath = base_path('tests/Fixtures/test-selfie.txt');
            if (!file_exists($testImagePath)) {
                throw new \Exception("No test image found (checked favicon.png and test-selfie.txt)");
            }
            $base64Content = file_get_contents($testImagePath);
            $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Content));
            $tempPath = sys_get_temp_dir() . '/test-envelope-doc-' . uniqid() . '.png';
            file_put_contents($tempPath, $imageData);
        } else {
            // Copy favicon to temp location
            $tempPath = sys_get_temp_dir() . '/test-envelope-doc-' . uniqid() . '.png';
            copy($sourcePath, $tempPath);
        }

        // Create UploadedFile
        $file = new UploadedFile(
            $tempPath,
            'test-document.png',
            'image/png',
            null,
            true // test mode
        );

        return $envelopeService->uploadAttachment($envelope, $docType, $file, Auth::user());
    }
}
