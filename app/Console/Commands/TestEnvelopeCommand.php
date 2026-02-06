<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

class TestEnvelopeCommand extends Command
{
    protected $signature = 'test:envelope
        {--voucher= : Voucher code (generates fresh test voucher if not specified)}
        {--user=lester@hurtado.ph : User email for voucher ownership}
        {--driver=simple.envelope : Driver ID}
        {--driver-version=1.0.0 : Driver version}';

    protected $description = 'Test settlement envelope lifecycle with a voucher';

    public function handle(EnvelopeService $envelopeService): int
    {
        $this->info('Testing Settlement Envelope Package...');
        $this->newLine();

        // Step 1: Get existing voucher or generate fresh test voucher
        $voucherCode = $this->option('voucher');
        if ($voucherCode) {
            $voucher = Voucher::where('code', $voucherCode)->first();
            if (!$voucher) {
                $this->error("Voucher not found: {$voucherCode}");
                return self::FAILURE;
            }
            $this->info("Using existing voucher: {$voucher->code}");
        } else {
            // Authenticate as specified user for voucher ownership
            $userEmail = $this->option('user');
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                $this->error("User not found: {$userEmail}");
                return self::FAILURE;
            }
            Auth::login($user);
            $this->info("Authenticated as: {$user->email}");

            $this->info('Generating fresh test voucher...');
            $instructions = VoucherInstructionsData::generateFromScratch();
            $vouchers = GenerateVouchers::run($instructions);
            $voucher = $vouchers->first();
            $this->line("✓ Generated voucher: {$voucher->code} (owner: {$user->email})");
        }

        $driverId = $this->option('driver');
        $driverVersion = $this->option('driver-version');
        $this->info("Driver: {$driverId}@{$driverVersion}");
        $this->newLine();

        // Step 2: Create envelope
        $this->info('Step 1: Creating envelope...');
        try {
            $envelope = $voucher->createEnvelope(
                driverId: $driverId,
                driverVersion: $driverVersion,
                initialPayload: ['name' => 'Test User']
            );
            $this->line("✓ Envelope created: {$envelope->reference_code}");
            $this->line("  Status: {$envelope->status->value}");
            $this->line("  Payload version: {$envelope->payload_version}");
        } catch (\Exception $e) {
            $this->error("✗ Failed to create envelope: {$e->getMessage()}");
            return self::FAILURE;
        }
        $this->newLine();

        // Step 3: Show checklist items
        $this->info('Step 2: Checklist items from driver:');
        foreach ($envelope->checklistItems as $item) {
            $status = $item->status->value;
            $required = $item->required ? '(required)' : '(optional)';
            $this->line("  [{$status}] {$item->label} {$required}");
        }
        $this->newLine();

        // Step 4: Update payload
        $this->info('Step 3: Updating payload...');
        try {
            $envelope = $envelopeService->updatePayload($envelope, [
                'reference_code' => $voucher->code,
                'amount' => 1000,
                'notes' => 'Test envelope created via artisan command',
            ]);
            $this->line("✓ Payload updated");
            $this->line("  New version: {$envelope->payload_version}");
            $this->line("  Payload: " . json_encode($envelope->payload));
        } catch (\Exception $e) {
            $this->error("✗ Failed to update payload: {$e->getMessage()}");
            return self::FAILURE;
        }
        $this->newLine();

        // Step 5: Show signals
        $this->info('Step 4: Current signals:');
        foreach ($envelope->signals as $signal) {
            $value = $signal->value === 'true' ? '✓ true' : '✗ false';
            $this->line("  {$signal->key}: {$value}");
        }
        $this->newLine();

        // Step 6: Set signal
        $this->info('Step 5: Setting approved signal to true...');
        try {
            $envelopeService->setSignal($envelope, 'approved', true);
            $envelope->refresh();
            $this->line("✓ Signal set: approved = true");
        } catch (\Exception $e) {
            $this->error("✗ Failed to set signal: {$e->getMessage()}");
            return self::FAILURE;
        }
        $this->newLine();

        // Step 7: Re-evaluate and check gates
        $this->info('Step 6: Gate states (after signal):');
        $gates = $envelopeService->computeGates($envelope);
        foreach ($gates as $gate => $value) {
            $status = $value ? '✓' : '✗';
            $this->line("  {$status} {$gate}: " . ($value ? 'true' : 'false'));
        }
        $this->newLine();

        // Step 8: Show audit log
        $this->info('Step 7: Audit log:');
        $envelope->load('auditLogs');
        foreach ($envelope->auditLogs as $log) {
            $this->line("  [{$log->created_at->format('H:i:s')}] {$log->action}");
        }
        $this->newLine();

        // Summary
        $this->info('Summary:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Reference Code', $envelope->reference_code],
                ['Driver', "{$envelope->driver_id}@{$envelope->driver_version}"],
                ['Status', $envelope->status->value],
                ['Payload Version', $envelope->payload_version],
                ['Settleable', ($gates['settleable'] ?? false) ? 'Yes' : 'No'],
                ['Checklist Items', $envelope->checklistItems->count()],
                ['Audit Entries', $envelope->auditLogs->count()],
            ]
        );

        $this->newLine();
        $this->info('All tests completed! ✓');

        return self::SUCCESS;
    }
}
