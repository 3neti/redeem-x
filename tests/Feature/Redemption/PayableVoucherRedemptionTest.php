<?php

use App\Actions\Payment\PayWithVoucher;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Merchant\Models\VendorAlias;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create users with vendor aliases and fund their wallets
    $this->userBB = User::factory()->create(['email' => 'bb@test.com']);
    $this->userOther = User::factory()->create(['email' => 'other@test.com']);
    $this->userBB->depositFloat(10000);
    $this->userOther->depositFloat(10000);

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
    // Generate B2B voucher with payable: "BB" using VoucherTestHelper
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->userBB, 1, 'B2B', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => 'BB',
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'B2B',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    $voucher = $vouchers->first();

    // Act: Try to redeem with correct user
    $result = PayWithVoucher::run($this->userBB, $voucher->code);

    // Assert
    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(100.0);
});

it('blocks authenticated user with wrong vendor alias from redeeming B2B voucher', function () {
    // Generate B2B voucher with payable: "BB" using VoucherTestHelper
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->userBB, 1, 'B2B', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => 'BB',
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'B2B',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    $voucher = $vouchers->first();

    // Act & Assert: Try to redeem with wrong user
    expect(fn () => PayWithVoucher::run($this->userOther, $voucher->code))
        ->toThrow(RedemptionException::class, 'payable to merchant');
});

it('allows standard voucher with secret to be redeemed with correct secret', function () {
    // This will be implemented after secret validation is wired up in web flow
    expect(true)->toBeTrue();
})->skip('Waiting for web flow integration');
