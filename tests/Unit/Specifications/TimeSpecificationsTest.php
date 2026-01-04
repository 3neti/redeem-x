<?php

use Carbon\Carbon;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Specifications\TimeLimitSpecification;
use LBHurtado\Voucher\Specifications\TimeWindowSpecification;

describe('TimeWindowSpecification', function () {
    beforeEach(function () {
        $this->spec = new TimeWindowSpecification();
    });
    
    it('passes when no time validation is configured', function () {
        $voucher = createVoucherWithoutTimeValidation();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when time validation is incomplete', function () {
        $voucher = createVoucherWithIncompleteTimeWindow();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when current time is within window', function () {
        Carbon::setTestNow('2025-01-15 14:00:00');
        
        $voucher = createVoucherWithTimeWindow(
            startTime: '2025-01-15 10:00:00',
            endTime: '2025-01-15 18:00:00'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
    
    it('fails when current time is before window', function () {
        Carbon::setTestNow('2025-01-15 08:00:00');
        
        $voucher = createVoucherWithTimeWindow(
            startTime: '2025-01-15 10:00:00',
            endTime: '2025-01-15 18:00:00'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('fails when current time is after window', function () {
        Carbon::setTestNow('2025-01-15 20:00:00');
        
        $voucher = createVoucherWithTimeWindow(
            startTime: '2025-01-15 10:00:00',
            endTime: '2025-01-15 18:00:00'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('passes at exact start time', function () {
        Carbon::setTestNow('2025-01-15 10:00:00');
        
        $voucher = createVoucherWithTimeWindow(
            startTime: '2025-01-15 10:00:00',
            endTime: '2025-01-15 18:00:00'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
    
    it('passes at exact end time', function () {
        Carbon::setTestNow('2025-01-15 18:00:00');
        
        $voucher = createVoucherWithTimeWindow(
            startTime: '2025-01-15 10:00:00',
            endTime: '2025-01-15 18:00:00'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
});

describe('TimeLimitSpecification', function () {
    beforeEach(function () {
        $this->spec = new TimeLimitSpecification();
    });
    
    it('passes when no time validation is configured', function () {
        $voucher = createVoucherWithoutTimeValidation();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when duration is not configured', function () {
        $voucher = createVoucherWithoutDuration();
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
    });
    
    it('passes when voucher is within duration limit (hours)', function () {
        Carbon::setTestNow('2025-01-15 10:00:00');
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '24h'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
    
    it('fails when voucher exceeds duration limit (hours)', function () {
        Carbon::setTestNow('2025-01-16 10:00:00'); // 26 hours later
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '24h'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('handles duration in minutes', function () {
        Carbon::setTestNow('2025-01-15 08:29:00');
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '30m'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        // Test expiration
        Carbon::setTestNow('2025-01-15 08:31:00');
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('handles duration in days', function () {
        Carbon::setTestNow('2025-01-17 10:00:00'); // 2 days later
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '7d'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        // Test expiration
        Carbon::setTestNow('2025-01-23 10:00:00'); // 8 days later
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('handles duration in seconds', function () {
        Carbon::setTestNow('2025-01-15 08:00:30');
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '60s'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        // Test expiration
        Carbon::setTestNow('2025-01-15 08:01:01');
        expect($this->spec->passes($voucher, $context))->toBeFalse();
        
        Carbon::setTestNow();
    });
    
    it('handles duration without unit (assumes seconds)', function () {
        Carbon::setTestNow('2025-01-15 08:00:30');
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '3600' // 1 hour in seconds
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
    
    it('passes at exact expiration time', function () {
        Carbon::setTestNow('2025-01-16 08:00:00'); // Exactly 24 hours later
        
        $voucher = createVoucherWithDuration(
            createdAt: '2025-01-15 08:00:00',
            duration: '24h'
        );
        $context = new RedemptionContext(mobile: '09171234567', inputs: []);
        
        expect($this->spec->passes($voucher, $context))->toBeTrue();
        
        Carbon::setTestNow();
    });
});

// Helper functions
function createVoucherWithoutTimeValidation(): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [],
        ],
        'created_at' => '2025-01-15 08:00:00',
    ];
}

function createVoucherWithIncompleteTimeWindow(): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'time' => (object) [
                    'start_time' => '2025-01-15 10:00:00',
                    // Missing end_time
                ],
            ],
        ],
        'created_at' => '2025-01-15 08:00:00',
    ];
}

function createVoucherWithTimeWindow(string $startTime, string $endTime): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'time' => (object) [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ],
            ],
        ],
        'created_at' => '2025-01-15 08:00:00',
    ];
}

function createVoucherWithoutDuration(): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'time' => (object) [],
            ],
        ],
        'created_at' => '2025-01-15 08:00:00',
    ];
}

function createVoucherWithDuration(string $createdAt, string $duration): object
{
    return (object) [
        'instructions' => (object) [
            'validation' => (object) [
                'time' => (object) [
                    'duration' => $duration,
                ],
            ],
        ],
        'created_at' => $createdAt,
    ];
}
