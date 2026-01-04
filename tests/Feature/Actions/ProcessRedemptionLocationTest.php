<?php

use App\Actions\Voucher\ProcessRedemption;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Auth;

/**
 * Test that ProcessRedemption no longer has redundant location validation.
 * Location validation should be handled by LocationSpecification in the Unified Validation Gateway.
 */
describe('ProcessRedemption Location Validation', function () {
    beforeEach(function () {
        // Login a user for voucher generation
        $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();
        Auth::login($user);
        
        // Generate a test voucher with location validation using the actual action
        $attribs = [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
                'fee_strategy' => 'absorb',
            ],
            'inputs' => [
                'fields' => ['location'],
            ],
            'feedback' => [],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
            ],
            'validation' => [
                'location' => [
                    'required' => true,
                    'target_lat' => 14.5547,
                    'target_lng' => 121.0244,
                    'radius_meters' => 1000,
                    'on_failure' => 'block',
                ],
            ],
            'count' => 1,
            'ttl' => 'P30D',
        ];
        
        [$vouchers, $cashEntities] = GenerateVouchers::run($user, $attribs);
        $this->voucher = $vouchers->first();
        
        // Voucher should already be marked as processed after cash entity is created
        // Wait a moment for the cash entity to be created
        sleep(1);
        $this->voucher->refresh();
        
        $this->phoneNumber = new PhoneNumber('09171234567', 'PH');
    });
    
    it('accepts location data in flat format from form flow', function () {
        // Flat format (as returned by form flow)
        $inputs = [
            'latitude' => 14.5592,  // Within 1km radius
            'longitude' => 121.0244,
        ];
        
        $bankAccount = [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ];
        
        $action = new ProcessRedemption();
        
        // This should succeed - location validation is handled by LocationSpecification
        expect(fn() => $action->handle(
            $this->voucher,
            $this->phoneNumber,
            $inputs,
            $bankAccount
        ))->not->toThrow(RuntimeException::class);
        
        // Verify voucher was redeemed
        $this->voucher->refresh();
        expect($this->voucher->redeemed_at)->not->toBeNull();
    });
    
    it('accepts location data in nested format', function () {
        // Nested format (traditional format)
        $inputs = [
            'location' => [
                'latitude' => 14.5592,  // Within 1km radius
                'longitude' => 121.0244,
            ],
        ];
        
        $bankAccount = [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ];
        
        $action = new ProcessRedemption();
        
        // This should also succeed
        expect(fn() => $action->handle(
            $this->voucher,
            $this->phoneNumber,
            $inputs,
            $bankAccount
        ))->not->toThrow(RuntimeException::class);
        
        // Verify voucher was redeemed
        $this->voucher->refresh();
        expect($this->voucher->redeemed_at)->not->toBeNull();
    });
    
    it('validates location through LocationSpecification (outside radius)', function () {
        // Location outside the allowed radius
        $inputs = [
            'latitude' => 14.6547,  // ~11km away (outside 1km radius)
            'longitude' => 121.1244,
        ];
        
        $bankAccount = [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ];
        
        $action = new ProcessRedemption();
        
        // This should fail because LocationSpecification will reject it
        expect(fn() => $action->handle(
            $this->voucher,
            $this->phoneNumber,
            $inputs,
            $bankAccount
        ))->toThrow(Exception::class);
        
        // Verify voucher was NOT redeemed
        $this->voucher->refresh();
        expect($this->voucher->redeemed_at)->toBeNull();
    });
    
    it('validates location through LocationSpecification (missing data)', function () {
        // No location data provided
        $inputs = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
        
        $bankAccount = [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ];
        
        $action = new ProcessRedemption();
        
        // This should fail because LocationSpecification requires location
        expect(fn() => $action->handle(
            $this->voucher,
            $this->phoneNumber,
            $inputs,
            $bankAccount
        ))->toThrow(Exception::class);
        
        // Verify voucher was NOT redeemed
        $this->voucher->refresh();
        expect($this->voucher->redeemed_at)->toBeNull();
    });
    
    it('processes voucher without location validation when not configured', function () {
        // Generate voucher WITHOUT location validation
        $user = Auth::user();
        $attribs = [
            'cash' => [
                'amount' => 50,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
                'fee_strategy' => 'absorb',
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
            ],
            'count' => 1,
            'ttl' => 'P30D',
        ];
        
        [$vouchers, $cashEntities] = GenerateVouchers::run($user, $attribs);
        $voucherNoLocation = $vouchers->first();
        
        // Wait for cash entity to be created
        sleep(1);
        $voucherNoLocation->refresh();
        
        $inputs = [];
        $bankAccount = [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ];
        
        $action = new ProcessRedemption();
        
        // Should succeed without location data
        expect(fn() => $action->handle(
            $voucherNoLocation,
            $this->phoneNumber,
            $inputs,
            $bankAccount
        ))->not->toThrow(Exception::class);
        
        // Verify voucher was redeemed
        $voucherNoLocation->refresh();
        expect($voucherNoLocation->redeemed_at)->not->toBeNull();
    });
});
