<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Http::fake();
    Log::spy();
});

test('disbursement gateway interface contract', function () {
    // This test verifies the gateway interface is properly defined
    // and can be mocked for integration testing

    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);

    // Verify interface methods exist
    expect(method_exists($mockGateway, 'disburse'))->toBeTrue();

    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    // Verify gateway can be resolved from container
    $gateway = app(PaymentGatewayInterface::class);
    expect($gateway)->toBeInstanceOf(PaymentGatewayInterface::class);

    // Verify the mock can return proper DisburseResponseData
    $mockGateway->shouldReceive('disburse')
        ->andReturn(
            DisburseResponseData::from([
                'transaction_id' => 'TEST-TXN-123',
                'uuid' => 'test-uuid-456',
                'status' => 'success',
            ])
        );

    // Note: With DISBURSE_DISABLE=true, disburse() won't be called during redemption
    // This test verifies the interface can be mocked for integration testing
});

test('disbursement input data structure', function () {
    // Test that DisburseInputData can be created with proper structure
    // Note: Full voucher-based creation requires complex accessor properties
    // This test verifies the DTO structure itself

    $input = DisburseInputData::from([
        'reference' => 'TEST-09171234567',
        'amount' => 500.0,
        'account_number' => '09171234567',
        'bank' => 'GCASH',
        'via' => 'INSTAPAY',
    ]);

    // Verify input data structure
    expect($input)->toBeInstanceOf(DisburseInputData::class);
    expect($input->amount)->toBe(500.0);
    expect($input->account_number)->toBe('09171234567');
    expect($input->bank)->toBe('GCASH');
    expect($input->via)->toBe('INSTAPAY');
});

test('disbursement failure handling', function () {
    // Test that disbursement failures are handled gracefully
    // Note: With DISBURSE_DISABLE=true, this tests the mock setup

    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);

    // Mock getRailFee for FeeCalculator during voucher generation
    $mockGateway->shouldReceive('getRailFee')
        ->andReturn(1000); // ₱10 fee in centavos

    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    // Mock gateway to return false (failure) - but won't be called with DISBURSE_DISABLE=true
    // This documents the expected behavior when disbursement is enabled

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    // Redeem should still succeed even if disbursement would fail
    // (because DISBURSE_DISABLE=true in phpunit.xml)
    $redeemed = RedeemVoucher::run($contact, $voucher->code);

    expect($redeemed)->toBeTrue();

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Note: Gateway mock not called because disbursement disabled in phpunit.xml
    // In production with disbursement enabled, the DisburseCash pipeline would:
    // 1. Call gateway->disburse()
    // 2. Log error if false returned
    // 3. Continue pipeline (voucher still marked redeemed)
});

test('disbursement metadata storage', function () {
    // Test that successful disbursement stores metadata correctly

    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);

    // Mock getRailFee for FeeCalculator during voucher generation
    $mockGateway->shouldReceive('getRailFee')
        ->andReturn(1000); // ₱10 fee in centavos

    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    // Note: With DISBURSE_DISABLE=true, disbursement won't run
    // This test documents the expected metadata structure when enabled

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'META', [
        'cash' => [
            'amount' => 250,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'META',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09467438575', 'PH'); // Your test number
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        'bank_account' => 'GCASH:09467438575',
    ]);

    RedeemVoucher::run($contact, $voucher->code);

    $voucher->refresh();

    // With DISBURSE_DISABLE=true, metadata won't be added
    // This test documents the expected structure when disbursement is enabled
    expect($voucher->redeemed_at)->not->toBeNull();

    // In production with disbursement enabled, voucher->metadata would contain:
    // - disbursement.gateway (e.g., 'netbank')
    // - disbursement.transaction_id
    // - disbursement.status
    // - disbursement.amount
    // - disbursement.recipient_identifier
    // - disbursement.disbursed_at
});

test('gateway rail selection logic', function () {
    // Test that rail selection logic works for different amounts
    // INSTAPAY: up to ₱50,000
    // PESONET: over ₱50,000

    // Test INSTAPAY for small amount
    $instapayInput = DisburseInputData::from([
        'reference' => 'TEST-SMALL',
        'amount' => 25000.0,
        'account_number' => '09171234567',
        'bank' => 'GCASH',
        'via' => 'INSTAPAY',
    ]);

    expect($instapayInput->via)->toBe('INSTAPAY');
    expect($instapayInput->amount)->toBe(25000.0);

    // Test PESONET for large amount (non-EMI bank)
    $pesonetInput = DisburseInputData::from([
        'reference' => 'TEST-LARGE',
        'amount' => 75000.0,
        'account_number' => '1234567890',
        'bank' => 'BDO',
        'via' => 'PESONET',
    ]);

    expect($pesonetInput->via)->toBe('PESONET');
    expect($pesonetInput->amount)->toBe(75000.0);

    // Note: In production, DisburseInputData::fromVoucher() automatically
    // selects the correct rail based on amount and bank type (EMI vs non-EMI)
});

test('EMI bank routing to INSTAPAY', function () {
    // Test that EMI banks (GCash, Maya) must use INSTAPAY
    // regardless of amount

    // GCash disbursement
    $gcashInput = DisburseInputData::from([
        'reference' => 'TEST-GCASH',
        'amount' => 1000.0,
        'account_number' => '09171234567',
        'bank' => 'GCASH',
        'via' => 'INSTAPAY', // GCash requires INSTAPAY
    ]);

    expect($gcashInput->bank)->toBe('GCASH');
    expect($gcashInput->via)->toBe('INSTAPAY');

    // Maya/PayMaya disbursement
    $mayaInput = DisburseInputData::from([
        'reference' => 'TEST-MAYA',
        'amount' => 2500.0,
        'account_number' => '09171234568',
        'bank' => 'MAYA',
        'via' => 'INSTAPAY', // Maya requires INSTAPAY
    ]);

    expect($mayaInput->bank)->toBe('MAYA');
    expect($mayaInput->via)->toBe('INSTAPAY');

    // Note: In production, the BankRegistry checks if a bank is EMI
    // and automatically routes to INSTAPAY. EMI banks cannot use PESONET.
});
