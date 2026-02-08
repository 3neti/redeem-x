<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    // Enable disbursement for these tests
    Config::set('voucher-pipeline.post-redemption', [
        \LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash::class,
        \App\Pipelines\RedeemedVoucher\PersistInputs::class,
        \LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash::class,
        \App\Pipelines\RedeemedVoucher\SendFeedbacks::class,
    ]);
});

test('voucher is not marked as redeemed when gateway returns false', function () {
    // Arrange: Create mock gateway that returns false (simulating gateway failure)
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);

    $mockGateway->shouldReceive('getRailFee')
        ->andReturn(1000); // ₱10 fee

    $mockGateway->shouldReceive('disburse')
        ->once()
        ->andReturn(false); // Gateway failure

    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09173011987', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'bank_account' => 'GCASH:09173011987',
    ]);

    // Act: Attempt redemption - should throw exception
    expect(fn () => RedeemVoucher::run($contact, $voucher->code))
        ->toThrow(RuntimeException::class, 'Disbursement failed: Gateway error');

    // Assert: Voucher should NOT be marked as redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->toBeNull();
    expect($voucher->status)->not->toBe('redeemed');
    expect($voucher->metadata)->not->toHaveKey('disbursement');
});

test('voucher is marked as redeemed when disbursement succeeds', function () {
    // Arrange: Create mock gateway that succeeds
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);

    $mockGateway->shouldReceive('getRailFee')
        ->andReturn(1000);

    $mockGateway->shouldReceive('disburse')
        ->once()
        ->andReturn(
            \LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData::from([
                'transaction_id' => 'TXN-SUCCESS-123',
                'uuid' => 'uuid-success-456',
                'status' => 'pending',
            ])
        );

    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'SUCC', [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'SUCC',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09173011987', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'bank_account' => 'GCASH:09173011987',
    ]);

    // Act: Redeem voucher - should succeed
    $result = RedeemVoucher::run($contact, $voucher->code);

    // Assert: Should be marked as redeemed with disbursement metadata
    expect($result)->toBeTrue();

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect($voucher->metadata)->toHaveKey('disbursement');
    expect($voucher->metadata['disbursement']['transaction_id'])->toBe('TXN-SUCCESS-123');
    expect($voucher->metadata['disbursement']['gateway'])->toBe('netbank');
});
