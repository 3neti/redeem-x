<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use LBHurtado\SettlementEnvelope\Enums\EnvelopeStatus;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Settlement Envelope Lifecycle Test Command (v1.1)
 *
 * This command serves as an interactive contract test for the envelope package.
 * It's designed to be demo-friendly, CI-safe, and policy-correct.
 *
 * @example php artisan test:envelope --scenario=full --upload-doc --auto-review --auto-settle
 * @example php artisan test:envelope --voucher=ABC123 --scenario=evidence
 * @example php artisan test:envelope --scenario=lock --user=admin@example.com
 */
class TestEnvelopeCommand extends Command
{
    protected $signature = 'test:envelope
        {--voucher= : Existing voucher code (generates fresh if not specified)}
        {--user=lester@hurtado.ph : User email to authenticate as}
        {--type=settlement : Voucher type (redeemable|payable|settlement)}
        {--driver=simple.envelope : Driver ID}
        {--driver-version=1.0.0 : Driver version}
        {--scenario=draft : Scenario: draft|evidence|signals|lock|settle|full}
        {--upload-doc : Upload test document(s)}
        {--auto-review : Auto-accept uploaded documents}
        {--with-context : Attach context metadata}
        {--auto-settle : Attempt lock + settle once gates pass}
        {--doc-path= : Optional real file path for upload}
        {--detailed : Verbose output}';

    protected $description = 'Test settlement envelope lifecycle with a voucher (v1.1 - CI-safe, actor-aware)';

    private EnvelopeService $envelopeService;
    private ?User $actor = null;
    private ?Voucher $voucher = null;
    private ?Envelope $envelope = null;

    public function handle(EnvelopeService $envelopeService): int
    {
        $this->envelopeService = $envelopeService;

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  Settlement Envelope Lifecycle Test (v1.1)');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 1: AUTHENTICATION (Always authenticate, even with --voucher)
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('AUTHENTICATION');

        $userEmail = $this->option('user');
        $this->actor = User::where('email', $userEmail)->first();
        if (!$this->actor) {
            $this->error("User not found: {$userEmail}");
            return self::FAILURE;
        }
        Auth::login($this->actor);
        $this->step("Authenticated as: {$this->actor->email}");
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 2: VOUCHER SETUP
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('VOUCHER SETUP');

        $voucherCode = $this->option('voucher');
        if ($voucherCode) {
            $this->voucher = Voucher::where('code', $voucherCode)->first();
            if (!$this->voucher) {
                $this->error("Voucher not found: {$voucherCode}");
                return self::FAILURE;
            }
            $this->step("Using existing voucher: {$this->voucher->code}");
        } else {
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
            $this->voucher = $vouchers->first();
            $this->step("Generated voucher: {$this->voucher->code} (type: {$voucherType->value})");
        }

        $driverId = $this->option('driver');
        $driverVersion = $this->option('driver-version');
        $this->detail("Driver: {$driverId}@{$driverVersion}");
        $this->showOptions();
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PHASE 3: CREATE ENVELOPE
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('CREATE ENVELOPE');

        try {
            $this->envelope = $this->voucher->createEnvelope(
                driverId: $driverId,
                driverVersion: $driverVersion,
                initialPayload: ['name' => 'Test User']
            );
            $this->step("Envelope created: {$this->envelope->reference_code}");
            $this->detail("Status: {$this->envelope->status->value}");
            $this->detail("Payload version: {$this->envelope->payload_version}");
        } catch (\Exception $e) {
            $this->error("✗ Failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->showChecklist($this->envelope);

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO-BASED EXECUTION
        // ─────────────────────────────────────────────────────────────────────
        $scenario = $this->option('scenario');
        $this->detail("Running scenario: {$scenario}");
        $this->newLine();

        switch ($scenario) {
            case 'draft':
            default:
                // Just create envelope, no further actions
                // Envelope stays in DRAFT state, ready for UI testing
                $this->info('  ℹ Envelope created in DRAFT state (editable)');
                $this->info('  ℹ Use --scenario=evidence to add payload/documents');
                break;

            case 'evidence':
                $this->runEvidenceFlow();
                break;

            case 'signals':
                $this->runSignalsFlow();
                break;

            case 'lock':
                $this->runEvidenceFlow();
                $this->runSignalsFlow();
                $this->attemptLock();
                break;

            case 'settle':
                $this->runEvidenceFlow();
                $this->runSignalsFlow();
                $this->attemptAutoSettle();
                break;

            case 'full':
                $this->runEvidenceFlow();
                $this->runSignalsFlow();
                $this->showGates();
                $this->showStateMachine();
                if ($this->option('auto-settle')) {
                    $this->attemptAutoSettle();
                }
                break;
        }

        // ─────────────────────────────────────────────────────────────────────
        // AUDIT LOG
        // ─────────────────────────────────────────────────────────────────────
        $this->phase('AUDIT LOG');

        $this->envelope->load('auditLogs');
        foreach ($this->envelope->auditLogs->reverse() as $log) {
            $this->line("  [{$log->created_at->format('H:i:s')}] {$log->action}");
        }
        $this->newLine();

        // ─────────────────────────────────────────────────────────────────────
        // PAYLOAD VERSIONS (if verbose)
        // ─────────────────────────────────────────────────────────────────────
        if ($this->option('detailed')) {
            $this->phase('PAYLOAD VERSIONS');

            $this->envelope->load('payloadVersions');
            foreach ($this->envelope->payloadVersions as $version) {
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
        $this->showSummary();

        return self::SUCCESS;
    }

    // =========================================================================
    // SCENARIO FLOWS
    // =========================================================================

    private function runEvidenceFlow(): void
    {
        $this->phase('EVIDENCE COLLECTION');

        // Update payload
        try {
            $this->envelope = $this->envelopeService->updatePayload($this->envelope, [
                'reference_code' => $this->voucher->code,
                'amount' => 1000,
                'notes' => 'Test envelope via artisan command',
            ]);
            $this->step("Payload updated (v{$this->envelope->payload_version})");
            if ($this->option('detailed')) {
                $this->detail("Payload: " . json_encode($this->envelope->payload));
            }
        } catch (\Exception $e) {
            $this->error("✗ Payload update failed: {$e->getMessage()}");
            return;
        }

        // Upload document
        if ($this->option('upload-doc')) {
            try {
                $attachment = $this->uploadTestDocument();
                $this->step("Document uploaded: {$attachment->original_filename}");
                $this->detail("Type: {$attachment->doc_type}, Size: " . number_format($attachment->size) . " bytes");
                $this->detail("Review status: {$attachment->review_status}");

                // Auto-review if explicitly requested
                if ($this->option('auto-review')) {
                    $this->runAutoReview();
                }

                $this->envelope->refresh();
                $this->showChecklist($this->envelope);
            } catch (\Exception $e) {
                $this->warn("⚠ Document upload skipped: {$e->getMessage()}");
            }
        }

        // Update context
        if ($this->option('with-context')) {
            try {
                $this->envelope = $this->envelopeService->updateContext($this->envelope, [
                    'source' => 'test:envelope command',
                    'test_timestamp' => now()->toIso8601String(),
                    'environment' => app()->environment(),
                    'notes' => 'Automated test run',
                ]);
                $this->step("Context updated");
                if ($this->option('detailed')) {
                    $this->detail("Context: " . json_encode($this->envelope->context));
                }
            } catch (\Exception $e) {
                $this->warn("⚠ Context update failed: {$e->getMessage()}");
            }
        }
        $this->newLine();
    }

    private function runSignalsFlow(): void
    {
        $this->phase('SIGNALS');

        $this->showSignals($this->envelope, 'Before');

        try {
            $this->envelopeService->setSignal($this->envelope, 'approved', true);
            $this->envelope->refresh();
            $this->step("Signal set: approved = true");
        } catch (\Exception $e) {
            $this->error("✗ Signal set failed: {$e->getMessage()}");
            return;
        }

        $this->showSignals($this->envelope, 'After');
        $this->newLine();
    }

    private function runAutoReview(): void
    {
        $this->envelope->refresh();
        foreach ($this->envelope->attachments as $attachment) {
            if ($attachment->review_status === 'pending') {
                try {
                    $this->envelopeService->reviewAttachment($attachment, 'accepted', $this->actor);
                    $this->step("Auto-reviewed attachment: {$attachment->original_filename}");
                } catch (\Exception $e) {
                    $this->warn("⚠ Auto-review failed: {$e->getMessage()}");
                }
            }
        }
    }

    private function showGates(): void
    {
        $this->phase('GATES');

        $gates = $this->envelopeService->computeGates($this->envelope);
        foreach ($gates as $gate => $value) {
            $icon = $value ? '✓' : '✗';
            $this->line("  {$icon} {$gate}: " . ($value ? 'true' : 'false'));
        }
        $this->newLine();
    }

    private function showStateMachine(): void
    {
        $this->phase('STATE MACHINE');
        $this->showStateProgress($this->envelope);
    }

    private function attemptLock(): void
    {
        $this->phase('LOCK ATTEMPT');

        $this->envelope->refresh();

        if (!$this->envelope->status->canLock()) {
            $this->warn("✗ Cannot lock: current state is {$this->envelope->status->value}");
            $this->showBlockers();
            return;
        }

        try {
            $this->envelope = $this->envelopeService->lock($this->envelope, $this->actor);
            $this->step("Locked: {$this->envelope->status->value}");
            $this->detail("Locked at: {$this->envelope->locked_at}");
        } catch (\Exception $e) {
            $this->warn("⚠ Lock failed: {$e->getMessage()}");
        }
        $this->newLine();
    }

    private function attemptAutoSettle(): void
    {
        $this->phase('AUTO-SETTLEMENT');

        $this->envelope->refresh();

        // Check if settleable
        if (!$this->envelope->isSettleable()) {
            $this->warn("✗ Envelope not settleable");
            $this->showBlockers();
            return;
        }

        // Lock if in READY_TO_SETTLE
        if ($this->envelope->status === EnvelopeStatus::READY_TO_SETTLE) {
            try {
                $this->envelope = $this->envelopeService->lock($this->envelope, $this->actor);
                $this->step("Locked: ready_to_settle → locked");
                $this->detail("Locked at: {$this->envelope->locked_at}");
            } catch (\Exception $e) {
                $this->warn("⚠ Lock failed: {$e->getMessage()}");
                return;
            }
        }

        $this->envelope->refresh();

        // Settle if LOCKED
        if ($this->envelope->status === EnvelopeStatus::LOCKED) {
            try {
                $this->envelope = $this->envelopeService->settle($this->envelope, $this->actor);
                $this->step("Settled: locked → settled");
                $this->detail("Settled at: {$this->envelope->settled_at}");
            } catch (\Exception $e) {
                $this->warn("⚠ Settlement failed: {$e->getMessage()}");
            }
        } elseif ($this->envelope->status !== EnvelopeStatus::SETTLED) {
            $this->warn("✗ Cannot settle: envelope must be LOCKED (current: {$this->envelope->status->value})");
        }
        $this->newLine();
    }

    // =========================================================================
    // BLOCKER REPORTING
    // =========================================================================

    private function showBlockers(): void
    {
        $this->envelope->refresh();
        $required = $this->envelope->checklistItems->where('required', true);

        $missing = $required->filter(fn($item) => $item->status->value === 'missing');
        $pending = $required->filter(fn($item) => in_array($item->status->value, ['uploaded', 'needs_review', 'pending']));
        $rejected = $required->filter(fn($item) => $item->status->value === 'rejected');

        if ($missing->count()) {
            $this->detail("Missing: " . $missing->pluck('label')->join(', '));
        }

        if ($pending->count()) {
            $this->detail("Pending review: " . $pending->pluck('label')->join(', '));
        }

        if ($rejected->count()) {
            $this->detail("Rejected: " . $rejected->pluck('label')->join(', '));
        }

        // Check signals
        $falseSignals = $this->envelope->signals->filter(fn($s) => $s->value !== 'true');
        if ($falseSignals->count()) {
            $this->detail("Signals false: " . $falseSignals->pluck('key')->join(', '));
        }

        // Gates summary
        $gates = $this->envelopeService->computeGates($this->envelope);
        $failingGates = collect($gates)->filter(fn($v) => !$v)->keys();
        if ($failingGates->count()) {
            $this->detail("Failing gates: " . $failingGates->join(', '));
        }
        $this->newLine();
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    private function showSummary(): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  SUMMARY');
        $this->info('═══════════════════════════════════════════════════════════');

        $summaryData = [
            ['Actor', $this->actor->email],
            ['Reference Code', $this->envelope->reference_code],
            ['Driver', "{$this->envelope->driver_id}@{$this->envelope->driver_version}"],
            ['Status', $this->envelope->status->value],
            ['Payload Version', $this->envelope->payload_version],
            ['Settleable', $this->envelope->isSettleable() ? 'Yes' : 'No'],
            ['Checklist Items', $this->envelope->checklistItems->count()],
            ['Attachments', $this->envelope->attachments()->count()],
            ['Audit Entries', $this->envelope->auditLogs->count()],
        ];

        if ($this->envelope->context) {
            $summaryData[] = ['Context Keys', implode(', ', array_keys($this->envelope->context))];
        }

        if ($this->envelope->locked_at) {
            $summaryData[] = ['Locked At', $this->envelope->locked_at->format('Y-m-d H:i:s')];
        }

        if ($this->envelope->settled_at) {
            $summaryData[] = ['Settled At', $this->envelope->settled_at->format('Y-m-d H:i:s')];
        }

        $this->table(['Property', 'Value'], $summaryData);

        $this->newLine();
        $statusIcon = $this->envelope->status === EnvelopeStatus::SETTLED ? '✓✓✓' : '✓';
        $this->info("{$statusIcon} Test completed!");
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
        $options[] = "scenario={$this->option('scenario')}";
        if ($this->option('upload-doc')) $options[] = 'upload-doc';
        if ($this->option('auto-review')) $options[] = 'auto-review';
        if ($this->option('with-context')) $options[] = 'with-context';
        if ($this->option('auto-settle')) $options[] = 'auto-settle';
        if ($this->option('doc-path')) $options[] = "doc-path={$this->option('doc-path')}";
        if ($this->option('detailed')) $options[] = 'detailed';

        $this->detail("Options: " . implode(', ', $options));
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

    /**
     * Upload test document with CI-safe deterministic behavior.
     *
     * Priority:
     * 1. --doc-path option (real file)
     * 2. Fake image (CI-safe, deterministic)
     * 3. Fallback to favicon.png (for visual demos)
     */
    private function uploadTestDocument()
    {
        // Determine document type based on driver
        $docType = $this->envelope->driver_id === 'vendor.pay-by-face' ? 'FACE_PHOTO' : 'TEST_DOC';

        // Option 1: Use provided file path (for real file testing)
        if ($docPath = $this->option('doc-path')) {
            if (!file_exists($docPath)) {
                throw new \Exception("Provided doc-path does not exist: {$docPath}");
            }
            $file = new UploadedFile(
                $docPath,
                basename($docPath),
                mime_content_type($docPath),
                null,
                true // test mode
            );
            return $this->envelopeService->uploadAttachment(
                $this->envelope,
                $docType,
                $file,
                $this->actor
            );
        }

        // Option 2: Use fake image (CI-safe, deterministic)
        // This creates a valid PNG in memory without filesystem dependencies
        $file = UploadedFile::fake()->image('test-document.png', 300, 300);

        return $this->envelopeService->uploadAttachment(
            $this->envelope,
            $docType,
            $file,
            $this->actor
        );
    }
}
