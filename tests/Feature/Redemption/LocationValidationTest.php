<?php

declare(strict_types=1);

use App\Actions\Voucher\ProcessRedemption;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\LocationValidationData;
use LBHurtado\Voucher\Data\ValidationInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable queues to avoid serialization issues
    Queue::fake();
    
    // Create user with balance
    $this->user = User::factory()->create();
    $this->user->deposit(100000);
    
    // Set user as authenticated for GenerateVouchers
    auth()->setUser($this->user);
});

describe('Location Validation - Pass Mode', function () {
    test('allows redemption when user is within location radius', function () {
        // Create voucher with location validation (warn mode)
        $targetLat = 14.5547;
        $targetLon = 121.0244;
        
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            $targetLat,
            $targetLon,
            5.0, // 5 km radius
            'warn' // warn mode
        );

        // User location within radius (2 km away)
        $userLat = 14.5747;
        $userLon = 121.0244;

        $inputs = [
            'location' => [
                'latitude' => $userLat,
                'longitude' => $userLon,
            ],
        ];

        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        // Verify voucher is redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
        
        // Verify validation results stored
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->not->toBeNull()
            ->location->not->toBeNull()
            ->location->validated->toBeTrue()
            ->location->should_block->toBeFalse();
    });

    test('allows redemption in warn mode even when outside radius', function () {
        // Create voucher with location validation (warn mode)
        $targetLat = 14.5547;
        $targetLon = 121.0244;
        
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            $targetLat,
            $targetLon,
            5.0, // 5 km radius
            'warn' // warn mode - should allow redemption
        );

        // User location outside radius (10 km away)
        $userLat = 14.6547;
        $userLon = 121.0244;

        $inputs = [
            'location' => [
                'latitude' => $userLat,
                'longitude' => $userLon,
            ],
        ];

        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed even though outside radius (warn mode)
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        // Verify voucher is redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
        
        // Verify validation results show failure but no block
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->not->toBeNull()
            ->location->not->toBeNull()
            ->location->validated->toBeFalse()
            ->location->should_block->toBeFalse();
    });
});

describe('Location Validation - Block Mode', function () {
    test('blocks redemption when user is outside location radius in block mode', function () {
        // Create voucher with location validation (block mode)
        $targetLat = 14.5547;
        $targetLon = 121.0244;
        
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            $targetLat,
            $targetLon,
            5.0, // 5 km radius
            'block' // block mode - should prevent redemption
        );

        // User location outside radius (10 km away)
        $userLat = 14.6547;
        $userLon = 121.0244;

        $inputs = [
            'location' => [
                'latitude' => $userLat,
                'longitude' => $userLon,
            ],
        ];

        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'You must be within');
        
        // Verify voucher is NOT redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
        
        // Note: Validation results are not persisted when transaction rolls back
        // This is expected - the voucher state remains unchanged on blocked redemption
    });

    test('allows redemption in block mode when within radius', function () {
        // Create voucher with location validation (block mode)
        $targetLat = 14.5547;
        $targetLon = 121.0244;
        
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            $targetLat,
            $targetLon,
            5.0, // 5 km radius
            'block' // block mode
        );

        // User location within radius (2 km away)
        $userLat = 14.5747;
        $userLon = 121.0244;

        $inputs = [
            'location' => [
                'latitude' => $userLat,
                'longitude' => $userLon,
            ],
        ];

        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        // Verify voucher is redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
        
        // Verify validation results stored
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->not->toBeNull()
            ->location->not->toBeNull()
            ->location->validated->toBeTrue()
            ->location->should_block->toBeFalse();
    });
});

describe('Location Validation - No Validation', function () {
    test('allows redemption when no location validation configured', function () {
        // Create voucher WITHOUT location validation
        $voucher = createVoucherWithoutLocationValidation($this->user);

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed without location data
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        // Verify voucher is redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
        
        // No validation results should be stored
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->toBeNull();
    });
});

describe('Location Validation - Missing Data', function () {
    test('blocks redemption when location validation required but no location data provided', function () {
        // Create voucher with location validation
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            14.5547,
            121.0244,
            5.0,
            'warn'
        );

        // No location data in inputs
        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Location data is required');
        
        // Verify voucher is NOT redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
    });

    test('blocks redemption when location data has invalid format', function () {
        // Create voucher with location validation
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            14.5547,
            121.0244,
            5.0,
            'warn'
        );

        // Invalid location data (missing longitude)
        $inputs = [
            'location' => [
                'latitude' => 14.5547,
            ],
        ];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Invalid location data format');
        
        // Verify voucher is NOT redeemed
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
    });
});

describe('Location Validation - Distance Calculation', function () {
    test('calculates correct distance and stores in validation results', function () {
        // Create voucher at Manila coordinates
        $targetLat = 14.5995;
        $targetLon = 120.9842;
        
        $voucher = createVoucherWithLocationValidation(
            $this->user,
            $targetLat,
            $targetLon,
            10.0, // 10 km radius
            'warn'
        );

        // User location approximately 5 km away
        $userLat = 14.6500;
        $userLon = 120.9842;

        $inputs = [
            'location' => [
                'latitude' => $userLat,
                'longitude' => $userLon,
            ],
        ];

        $phoneNumber = new PhoneNumber('09171234567', 'PH');
        ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        // Verify distance is stored correctly
        $voucher->refresh();
        $validationResults = $voucher->getValidationResults();
        
        expect($validationResults->location->distance_meters)
            ->toBeGreaterThan(0)
            ->toBeLessThan(10000); // 10 km in meters
    });
});

// Helper functions
function createVoucherWithLocationValidation(
    User $user,
    float $latitude,
    float $longitude,
    float $maxDistanceKm,
    string $validationMode
): Voucher {
    $locationValidation = LocationValidationData::from([
        'required' => true,
        'target_lat' => $latitude,
        'target_lng' => $longitude,
        'radius_meters' => (int) ($maxDistanceKm * 1000), // Convert km to meters
        'on_failure' => $validationMode,
    ]);

    $validationInstruction = ValidationInstructionData::from([
        'location' => $locationValidation,
        'time' => null,
    ]);

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
        'prefix' => 'LOC',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
        'validation' => $validationInstruction,
    ]);

    auth()->setUser($user);
    $vouchers = GenerateVouchers::run($instructions);
    
    $voucher = $vouchers->first();
    // Mark as processed since queue is faked
    $voucher->processed = true;
    $voucher->save();
    
    return $voucher;
}

function createVoucherWithoutLocationValidation(User $user): Voucher
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
        'prefix' => 'NOV',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
        'validation' => null, // No validation
    ]);

    auth()->setUser($user);
    $vouchers = GenerateVouchers::run($instructions);
    
    $voucher = $vouchers->first();
    // Mark as processed since queue is faked
    $voucher->processed = true;
    $voucher->save();
    
    return $voucher;
}
