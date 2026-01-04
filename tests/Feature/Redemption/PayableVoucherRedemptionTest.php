<?php

use App\Actions\Payment\PayWithVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Merchant\Models\VendorAlias;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users with vendor aliases
    $this->userBB = User::factory()->create(['email' => 'bb@test.com']);
    $this->userOther = User::factory()->create(['email' => 'other@test.com']);
    
    // Create vendor aliases
    $this->aliasBB = VendorAlias::factory()->create([
        'owner_user_id' => $this->userBB->id,
        'alias' => 'BB',
        'status' => 'active',
    ]);
    
    $this->aliasOther = VendorAlias::factory()->create([
        'owner_user_id' => $this->userOther->id,
        'alias' => 'OTHER',
        'status' => 'active',
    ]);
});

it('allows authenticated user with correct vendor alias to redeem B2B voucher', function () {
    // Generate B2B voucher with payable: "BB"
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 100,
            currency: 'PHP',
            validation: new CashValidationRulesData(
                secret: null,
                mobile: null,
                payable: 'BB', // B2B voucher
                country: null,
                location: null,
                radius: null
            )
        )
    );
    
    $action = app(GenerateVouchers::class);
    $vouchers = $action->handle(
        user: $this->userBB,
        instructions: $instructions,
        count: 1
    );
    
    $voucher = $vouchers->first();
    
    // Act: Try to redeem with correct user
    $payAction = new PayWithVoucher();
    $result = $payAction->handle($this->userBB, $voucher->code);
    
    // Assert
    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(100.0);
});

it('blocks authenticated user with wrong vendor alias from redeeming B2B voucher', function () {
    // Generate B2B voucher with payable: "BB"
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 100,
            currency: 'PHP',
            validation: new CashValidationRulesData(
                secret: null,
                mobile: null,
                payable: 'BB', // B2B voucher  
                country: null,
                location: null,
                radius: null
            )
        )
    );
    
    $action = app(GenerateVouchers::class);
    $vouchers = $action->handle(
        user: $this->userBB,
        instructions: $instructions,
        count: 1
    );
    
    $voucher = $vouchers->first();
    
    // Act & Assert: Try to redeem with wrong user
    $payAction = new PayWithVoucher();
    
    expect(fn() => $payAction->handle($this->userOther, $voucher->code))
        ->toThrow(RedemptionException::class, 'payable to merchant "BB"');
});

it('allows standard voucher with secret to be redeemed with correct secret', function () {
    // This will be implemented after secret validation is wired up in web flow
    expect(true)->toBeTrue();
})->skip('Waiting for web flow integration');
