<?php

declare(strict_types=1);

use LBHurtado\Voucher\Actions\GenerateVouchers;
use App\Models\User;
use App\Services\VoucherRedemptionService;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Add sufficient balance for voucher generation
    $this->user->deposit(10000); // ₱10,000
});

describe('Time Limit Validation - Integration Tests', function () {
    test('blocks redemption when limit_minutes configured but no timing data', function () {
        // Create voucher with 1 minute limit
        $voucher = createVoucherWithTimeLimit($this->user, 1);
        
        // Verify timing data is missing
        expect($voucher->timing)->toBeNull();
        expect($voucher->getRedemptionDuration())->toBeNull();
        
        // Try to redeem WITHOUT tracking timing
        $service = new VoucherRedemptionService();
        $context = new RedemptionContext(
            mobile: '09171234567',
            secret: null,
            vendorAlias: null,
            inputs: [],
            bankAccount: []
        );
        
        // Should throw exception
        expect(fn() => $service->validateRedemption($voucher, $context))
            ->toThrow(RedemptionException::class, 'Redemption time limit exceeded');
    });
    
    test('allows redemption when timing tracked within limit', function () {
        // Create voucher with 1 minute limit
        $voucher = createVoucherWithTimeLimit($this->user, 1);
        
        // Track start
        $voucher->trackRedemptionStart();
        $voucher->refresh();
        
        // Verify timing data exists
        expect($voucher->timing)->not->toBeNull();
        expect($voucher->timing->started_at)->not->toBeNull();
        
        // Simulate quick redemption (10 seconds)
        sleep(1); // Small delay for realistic test
        $voucher->trackRedemptionSubmit();
        $voucher->refresh();
        
        // Verify duration was calculated
        expect($voucher->getRedemptionDuration())->not->toBeNull();
        expect($voucher->getRedemptionDuration())->toBeLessThan(60); // Under 1 minute
        
        // Try to redeem
        $service = new VoucherRedemptionService();
        $context = new RedemptionContext(
            mobile: '09171234567',
            secret: null,
            vendorAlias: null,
            inputs: [],
            bankAccount: []
        );
        
        // Should NOT throw exception
        $service->validateRedemption($voucher, $context);
        
        expect(true)->toBeTrue(); // If we reach here, validation passed
    });
    
    test('blocks redemption when timing exceeds limit', function () {
        // Create voucher with 1 minute limit
        $voucher = createVoucherWithTimeLimit($this->user, 1);
        
        // Track start
        $voucher->trackRedemptionStart();
        $voucher->refresh();
        
        // Manually set started_at to 2 minutes ago to simulate slow redemption
        $timing = $voucher->timing;
        $startedAt = now()->subMinutes(2)->toIso8601String();
        $metadata = $voucher->metadata ?? [];
        $metadata['timing'] = [
            'clicked_at' => $timing->clicked_at,
            'started_at' => $startedAt,
            'submitted_at' => null,
            'duration_seconds' => null,
        ];
        $voucher->metadata = $metadata;
        $voucher->save();
        
        // Now track submit (will calculate duration)
        $voucher->trackRedemptionSubmit();
        $voucher->refresh();
        
        // Verify duration is over 1 minute
        expect($voucher->getRedemptionDuration())->toBeGreaterThan(60);
        
        // Try to redeem
        $service = new VoucherRedemptionService();
        $context = new RedemptionContext(
            mobile: '09171234567',
            secret: null,
            vendorAlias: null,
            inputs: [],
            bankAccount: []
        );
        
        // Should throw exception
        expect(fn() => $service->validateRedemption($voucher, $context))
            ->toThrow(RedemptionException::class, 'Redemption time limit exceeded');
    });
    
    test('allows redemption when no time limit configured', function () {
        // Create voucher WITHOUT time validation
        $voucher = createVoucherWithoutTimeValidation($this->user);
        
        // Try to redeem WITHOUT timing tracking
        $service = new VoucherRedemptionService();
        $context = new RedemptionContext(
            mobile: '09171234567',
            secret: null,
            vendorAlias: null,
            inputs: [],
            bankAccount: []
        );
        
        // Should NOT throw exception (no time validation)
        $service->validateRedemption($voucher, $context);
        
        expect(true)->toBeTrue(); // If we reach here, validation passed
    });
    
    test('validates via RedemptionGuard in VoucherRedemptionService', function () {
        // This test verifies the entire validation flow:
        // 1. VoucherRedemptionService creates RedemptionGuard
        // 2. RedemptionGuard calls TimeLimitSpecification
        // 3. TimeLimitSpecification checks timing data
        
        $voucher = createVoucherWithTimeLimit($this->user, 1);
        
        $service = new VoucherRedemptionService();
        $context = new RedemptionContext(
            mobile: '09171234567',
            secret: null,
            vendorAlias: null,
            inputs: [],
            bankAccount: []
        );
        
        // Should fail at validation layer (not ProcessRedemption)
        try {
            $service->validateRedemption($voucher, $context);
            $this->fail('Expected RedemptionException to be thrown');
        } catch (RedemptionException $e) {
            expect($e->getMessage())->toContain('Redemption time limit exceeded');
        }
    });
});

// Helper functions
function createVoucherWithTimeLimit(User $user, int $limitMinutes): Voucher
{
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(1),
        'validation' => [
            'time' => [
                'limit_minutes' => $limitMinutes,
                'track_duration' => true,
            ],
        ],
    ]);

    auth()->setUser($user);
    $vouchers = GenerateVouchers::run($instructions);
    
    $voucher = $vouchers->first();
    // Mark as processed since queue is faked
    $voucher->processed = true;
    $voucher->save();
    
    return $voucher;
}

function createVoucherWithoutTimeValidation(User $user): Voucher
{
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'NOTIME',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(1),
        'validation' => null, // No validation
    ]);

    auth()->setUser($user);
    $vouchers = GenerateVouchers::run($instructions);
    
    $voucher = $vouchers->first();
    // Mark as processed since queue is faked
    $voucher->processed_on = now();
    $voucher->save();
    
    return $voucher;
}
