<?php

declare(strict_types=1);

use App\Actions\Voucher\ProcessRedemption;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\TimeValidationData;
use LBHurtado\Voucher\Data\TimeWindowData;
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

describe('Time Window Validation', function () {
    test('allows redemption within time window', function () {
        // Create voucher with time window (09:00 to 17:00)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0)); // 12:00 PM
        
        $voucher = createVoucherWithTimeWindow(
            $this->user,
            '09:00',
            '17:00'
        );

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed (within window)
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
        
        // Verify validation results
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->not->toBeNull()
            ->time->not->toBeNull()
            ->time->within_window->toBeTrue()
            ->time->should_block->toBeFalse();
    });

    test('blocks redemption outside time window', function () {
        // Create voucher with time window (09:00 to 17:00)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 20, 0, 0)); // 8:00 PM (outside window)
        
        $voucher = createVoucherWithTimeWindow(
            $this->user,
            '09:00',
            '17:00'
        );

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Redemption is only allowed between');
        
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
    });

    test('handles cross-midnight time windows correctly', function () {
        // Create voucher with time window (22:00 to 02:00)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 23, 30, 0)); // 11:30 PM (within window)
        
        $voucher = createVoucherWithTimeWindow(
            $this->user,
            '22:00',
            '02:00'
        );

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        $validationResults = $voucher->getValidationResults();
        expect($validationResults->time->within_window)->toBeTrue();
    });

    test('handles cross-midnight time windows after midnight', function () {
        // Create voucher with time window (22:00 to 02:00)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 1, 30, 0)); // 1:30 AM (within window)
        
        $voucher = createVoucherWithTimeWindow(
            $this->user,
            '22:00',
            '02:00'
        );

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        $validationResults = $voucher->getValidationResults();
        expect($validationResults->time->within_window)->toBeTrue();
    });

    test('blocks cross-midnight redemption outside window', function () {
        // Create voucher with time window (22:00 to 02:00)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0)); // 12:00 PM (outside window)
        
        $voucher = createVoucherWithTimeWindow(
            $this->user,
            '22:00',
            '02:00'
        );

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class);
        
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
    });
});

describe('Duration Limit Validation', function () {
    test('allows redemption within duration limit', function () {
        // Create voucher with 10 minute duration limit
        $voucher = createVoucherWithDurationLimit($this->user, 10);

        // Track timing (5 minutes duration)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0));
        $voucher->trackRedemptionStart();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();
        
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 5, 0)); // 5 minutes later
        $voucher->trackRedemptionSubmit();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed (within 10 minute limit)
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        $validationResults = $voucher->getValidationResults();
        expect($validationResults)->not->toBeNull()
            ->time->not->toBeNull()
            ->time->within_duration->toBeTrue()
            ->time->should_block->toBeFalse();
    });

    test('blocks redemption exceeding duration limit', function () {
        // Create voucher with 10 minute duration limit
        $voucher = createVoucherWithDurationLimit($this->user, 10);

        // Track timing (15 minutes duration - exceeds limit)
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0));
        $voucher->trackRedemptionStart();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();
        
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 15, 0)); // 15 minutes later
        $voucher->trackRedemptionSubmit();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Redemption took too long');
        
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeFalse();
    });

    test('allows redemption when duration not tracked yet', function () {
        // Create voucher with duration limit but no timing tracked
        $voucher = createVoucherWithDurationLimit($this->user, 10);

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed (no duration to check)
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
    });
});

describe('Combined Time and Duration Validation', function () {
    test('requires both window and duration to pass', function () {
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0)); // 12:00 PM (within window)
        
        $voucher = createVoucherWithTimeWindowAndDuration(
            $this->user,
            '09:00',
            '17:00',
            10 // 10 minute limit
        );

        // Track timing (5 minutes duration)
        $voucher->trackRedemptionStart();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();
        
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 5, 0));
        $voucher->trackRedemptionSubmit();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed (both pass)
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        $validationResults = $voucher->getValidationResults();
        expect($validationResults->time->within_window)->toBeTrue();
        expect($validationResults->time->within_duration)->toBeTrue();
        expect($validationResults->time->should_block)->toBeFalse();
    });

    test('blocks when window fails even if duration passes', function () {
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 20, 0, 0)); // 8:00 PM (outside window)
        
        $voucher = createVoucherWithTimeWindowAndDuration(
            $this->user,
            '09:00',
            '17:00',
            10
        );

        // Track timing (5 minutes duration - within limit)
        $voucher->trackRedemptionStart();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();
        
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 20, 5, 0));
        $voucher->trackRedemptionSubmit();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception (window fails)
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Redemption is only allowed between');
    });

    test('blocks when duration fails even if window passes', function () {
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 0, 0)); // 12:00 PM (within window)
        
        $voucher = createVoucherWithTimeWindowAndDuration(
            $this->user,
            '09:00',
            '17:00',
            10
        );

        // Track timing (15 minutes duration - exceeds limit)
        $voucher->trackRedemptionStart();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();
        
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 12, 15, 0));
        $voucher->trackRedemptionSubmit();
        $voucher->processed = true; // Re-mark after save
        $voucher->save();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should throw exception (duration fails)
        expect(fn() => ProcessRedemption::run($voucher, $phoneNumber, $inputs))
            ->toThrow(RuntimeException::class, 'Redemption took too long');
    });
});

describe('No Time Validation', function () {
    test('allows redemption when no time validation configured', function () {
        // Set test time before creating voucher to ensure processed_on is valid
        Carbon::setTestNow(Carbon::create(2025, 1, 17, 2, 0, 0)); // Any time
        
        // Create voucher WITHOUT time validation
        $voucher = createVoucherWithoutTimeValidation($this->user);
        
        // Ensure voucher is marked as processed
        $voucher->refresh();
        expect($voucher->processed)->toBeTrue();

        $inputs = [];
        $phoneNumber = new PhoneNumber('09171234567', 'PH');

        // Should succeed
        $result = ProcessRedemption::run($voucher, $phoneNumber, $inputs);

        expect($result)->toBeTrue();
        
        $voucher->refresh();
        expect($voucher->isRedeemed())->toBeTrue();
    });
});

// Helper functions are defined in tests/Unit/Specifications/TimeSpecificationsTest.php
