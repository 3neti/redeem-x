<?php

use App\Actions\Payment\PayWithVoucher;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Merchant\Actions\AssignVendorAlias;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Models\Voucher;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create two merchants with aliases
    $this->merchant1 = User::factory()->create(['name' => 'Vendor 1']);
    $this->merchant2 = User::factory()->create(['name' => 'Vendor 2']);

    // Give them wallet balance (enough to fund vouchers)
    $this->merchant1->depositFloat(10000);
    $this->merchant2->depositFloat(10000);

    // Assign aliases
    $this->alias1 = AssignVendorAlias::run($this->merchant1->id, 'VNDR1', null);
    $this->alias2 = AssignVendorAlias::run($this->merchant2->id, 'VNDR2', null);
});

test('correct merchant can redeem payable voucher', function () {
    // Create voucher payable to VNDR1 (merchant1's alias)
    $voucher = createPayableVoucher(
        amount: 50,
        payableAlias: 'VNDR1',
        owner: $this->merchant1
    );

    $balanceBefore = $this->merchant1->fresh()->balanceFloat;

    // Merchant1 should be able to redeem
    $result = PayWithVoucher::run($this->merchant1, $voucher->code);

    $balanceAfter = $this->merchant1->fresh()->balanceFloat;

    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(50.0);
    expect((float) $balanceAfter)->toBe((float) $balanceBefore + 50.0);

    // Voucher should be marked as redeemed
    expect($voucher->fresh()->redeemed_at)->not->toBeNull();
});

test('wrong merchant cannot redeem payable voucher', function () {
    // Create voucher payable to VNDR1 (merchant1's alias)
    $voucher = createPayableVoucher(
        amount: 50,
        payableAlias: 'VNDR1',
        owner: $this->merchant1
    );

    // Merchant2 tries to redeem - should fail with RedemptionException
    PayWithVoucher::run($this->merchant2, $voucher->code);
})->throws(RedemptionException::class, 'payable to merchant');

test('merchant without alias cannot redeem payable voucher', function () {
    $merchantNoAlias = User::factory()->create();
    $merchantNoAlias->depositFloat(10000);

    $voucher = createPayableVoucher(
        amount: 50,
        payableAlias: 'VNDR1',
        owner: $this->merchant1
    );

    // Merchant without alias tries to redeem - fails payable check
    PayWithVoucher::run($merchantNoAlias, $voucher->code);
})->throws(RedemptionException::class, 'payable to merchant');

test('any merchant can redeem voucher without payable restriction', function () {
    // Create voucher WITHOUT payable restriction
    $voucher = createPayableVoucher(
        amount: 30,
        payableAlias: null, // No restriction
        owner: $this->merchant1
    );

    // Merchant2 should be able to redeem
    $result = PayWithVoucher::run($this->merchant2, $voucher->code);

    expect($result['success'])->toBeTrue();
    expect($result['amount'])->toBe(30.0);
});

test('merchant with revoked alias cannot redeem', function () {
    // Create voucher payable to VNDR1 first (before revoking)
    $voucher = createPayableVoucher(
        amount: 50,
        payableAlias: 'VNDR1',
        owner: $this->merchant1
    );

    // Revoke merchant1's alias after voucher is created
    $this->alias1->update(['status' => 'revoked']);

    // Merchant1 tries to redeem with revoked alias - primaryVendorAlias returns null
    PayWithVoucher::run($this->merchant1, $voucher->code);
})->throws(RedemptionException::class, 'payable to merchant');

test('payable validation happens before voucher redemption', function () {
    // Create voucher payable to VNDR1
    $voucher = createPayableVoucher(
        amount: 50,
        payableAlias: 'VNDR1',
        owner: $this->merchant1
    );

    // Merchant2 tries to redeem
    try {
        PayWithVoucher::run($this->merchant2, $voucher->code);
        $this->fail('Should have thrown exception');
    } catch (RedemptionException $e) {
        // Voucher should NOT be redeemed
        expect($voucher->fresh()->redeemed_at)->toBeNull();
    }
});

/**
 * Helper to create a payable voucher using GenerateVouchers (creates Cash entity).
 */
function createPayableVoucher(float $amount, ?string $payableAlias, User $owner): Voucher
{
    $instructions = [
        'cash' => [
            'amount' => $amount,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => $payableAlias,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ];

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($owner, 1, 'PAY', $instructions);

    return $vouchers->first();
}
