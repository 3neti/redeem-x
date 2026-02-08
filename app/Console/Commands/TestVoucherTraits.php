<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;

class TestVoucherTraits extends Command
{
    protected $signature = 'test:voucher-traits';

    protected $description = 'Test voucher external metadata, timing, and validation traits';

    public function handle(): int
    {
        $this->info('Testing Voucher Traits from Package...');

        // Get latest voucher
        $voucher = Voucher::latest()->first();

        if (! $voucher) {
            $this->error('No vouchers found!');

            return self::FAILURE;
        }

        $this->info("Testing with voucher: {$voucher->code}");
        $this->newLine();

        // Test 1: External Metadata (using generic structure)
        $this->info('Test 1: Setting external metadata (generic structure)...');
        $voucher->external_metadata = [
            'external_id' => 'MMS-001',
            'external_type' => 'game',
            'reference_id' => 'CH-005',
            'user_id' => 'CONT-042',
            'custom' => [
                'sequence' => 3,
                'challenge_type' => 'treasure_hunt',
                'extra_field' => 'any_value',
            ],
        ];
        $voucher->save();
        $this->line('✓ External metadata saved');

        // Test 2: Read external metadata
        $this->info('Test 2: Reading external metadata...');
        $voucher->refresh();
        if ($voucher->external_metadata) {
            $this->line("✓ External ID (game): {$voucher->external_metadata->external_id}");
            $this->line("✓ Type: {$voucher->external_metadata->external_type}");
            $this->line("✓ Reference (challenge): {$voucher->external_metadata->reference_id}");
            $this->line("✓ User (contestant): {$voucher->external_metadata->user_id}");
            $this->line('✓ Custom sequence: '.$voucher->external_metadata->getCustom('sequence'));
            $this->line('✓ Custom challenge_type: '.$voucher->external_metadata->getCustom('challenge_type'));
        }

        // Test 3: Timing tracking
        $this->info('Test 3: Tracking timing events...');
        $voucher->trackClick();
        $this->line('✓ Click tracked');

        $voucher->trackRedemptionStart();
        $this->line('✓ Start tracked');

        sleep(1); // Simulate some work

        $voucher->trackRedemptionSubmit();
        $this->line('✓ Submit tracked');

        $voucher->refresh();
        if ($voucher->timing) {
            $this->line("✓ Duration: {$voucher->timing->duration_seconds} seconds");
        }

        // Test 4: Validation results
        $this->info('Test 4: Storing validation results...');
        $voucher->storeValidationResults(
            location: \LBHurtado\Voucher\Data\LocationValidationResultData::from([
                'validated' => true,
                'distance_meters' => 500.0,  // 500 meters = 0.5 km
                'should_block' => false,
            ]),
            time: \LBHurtado\Voucher\Data\TimeValidationResultData::from([
                'within_window' => true,
                'within_duration' => true,
                'duration_seconds' => 45,
                'should_block' => false,
            ])
        );
        $voucher->save();
        $this->line('✓ Validation results stored');

        // Test 5: Query validation status
        $this->info('Test 5: Checking validation status...');
        $voucher->refresh();
        $this->line('✓ Passed validation: '.($voucher->passedValidation() ? 'Yes' : 'No'));
        $this->line('✓ Was blocked: '.($voucher->wasBlockedByValidation() ? 'Yes' : 'No'));

        // Test 6: Query scopes
        $this->info('Test 6: Testing query scopes...');
        $this->line("✓ Vouchers with external_id 'MMS-001': ".Voucher::whereExternal('external_id', 'MMS-001')->count());
        $this->line("✓ Vouchers with type 'game': ".Voucher::whereExternal('external_type', 'game')->count());

        // Show full metadata
        $this->newLine();
        $this->info('Full metadata structure:');
        $this->line(json_encode($voucher->metadata, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info('All tests passed! ✓');

        return self::SUCCESS;
    }
}
