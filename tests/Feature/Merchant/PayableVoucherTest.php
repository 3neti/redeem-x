<?php

use App\Actions\Payment\PayWithVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LBHurtado\Merchant\Actions\AssignVendorAlias;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create two merchants with aliases
    $this->merchant1 = User::factory()->create(['name' => 'Vendor 1']);
    $this->merchant2 = User::factory()->create(['name' => 'Vendor 2']);

    // Give them wallet balance
    $this->merchant1->depositFloat(1000);
    $this->merchant2->depositFloat(1000);

    // Assign aliases
    $this->alias1 = AssignVendorAlias::run($this->merchant1->id, 'VNDR1', null);
    $this->alias2 = AssignVendorAlias::run($this->merchant2->id, 'VNDR2', null);
});

test('correct merchant can redeem payable voucher', function () {
    // Create voucher payable to merchant1
    $voucher = createPayableVoucher(
        amount: 50,
        payableAliasId: $this->alias1->id,
        owner: $this->merchant1
    );

    $balanceBefore = $this->merchant1->fresh()->balanceFloat;

    // Merchant1 should be able to redeem
    $result = PayWithVoucher::run($this->merchant1, $voucher->code);

    $balanceAfter = $this->merchant1->fresh()->balanceFloat;

    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(50.0);
    expect($balanceAfter)->toBe($balanceBefore + 50.0);

    // Voucher should be marked as redeemed
    expect($voucher->fresh()->redeemed_at)->not->toBeNull();
});

test('wrong merchant cannot redeem payable voucher', function () {
    // Create voucher payable to merchant1
    $voucher = createPayableVoucher(
        amount: 50,
        payableAliasId: $this->alias1->id,
        owner: $this->merchant1
    );

    // Merchant2 tries to redeem
    PayWithVoucher::run($this->merchant2, $voucher->code);
})->throws(\RuntimeException::class, 'payable to VNDR1');

test('merchant without alias cannot redeem payable voucher', function () {
    $merchantNoAlias = User::factory()->create();
    $merchantNoAlias->depositFloat(1000);

    $voucher = createPayableVoucher(
        amount: 50,
        payableAliasId: $this->alias1->id,
        owner: $this->merchant1
    );

    // Merchant without alias tries to redeem
    PayWithVoucher::run($merchantNoAlias, $voucher->code);
})->throws(\RuntimeException::class, 'active vendor alias');

test('any merchant can redeem voucher without payable restriction', function () {
    // Create voucher WITHOUT payable restriction
    $voucher = createPayableVoucher(
        amount: 30,
        payableAliasId: null, // No restriction
        owner: $this->merchant1
    );

    // Both merchants should be able to redeem
    $result = PayWithVoucher::run($this->merchant2, $voucher->code);

    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(30.0);
});

test('merchant with revoked alias cannot redeem', function () {
    // Revoke merchant1's alias
    $this->alias1->update(['status' => 'revoked']);

    $voucher = createPayableVoucher(
        amount: 50,
        payableAliasId: $this->alias1->id,
        owner: $this->merchant1
    );

    // Merchant1 tries to redeem with revoked alias
    PayWithVoucher::run($this->merchant1, $voucher->code);
})->throws(\RuntimeException::class, 'active vendor alias');

test('payable validation happens before voucher redemption', function () {
    // Create voucher payable to merchant1
    $voucher = createPayableVoucher(
        amount: 50,
        payableAliasId: $this->alias1->id,
        owner: $this->merchant1
    );

    // Merchant2 tries to redeem
    try {
        PayWithVoucher::run($this->merchant2, $voucher->code);
        $this->fail('Should have thrown exception');
    } catch (\RuntimeException $e) {
        // Voucher should NOT be redeemed
        expect($voucher->fresh()->redeemed_at)->toBeNull();
    }
});

/**
 * Helper to create a payable voucher
 */
function createPayableVoucher(float $amount, ?int $payableAliasId, User $owner): Voucher
{
    // Create voucher directly
    $voucher = Voucher::create([
        'code' => 'TEST-'.strtoupper(Str::random(6)),
        'owner_type' => User::class,
        'owner_id' => $owner->id,
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'validation' => [
                        'payable' => $payableAliasId,
                    ],
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'starts_at' => now(),
        'expires_at' => now()->addYear(),
        'processed_on' => now(),
    ]);

    return $voucher;
}
