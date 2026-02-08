<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();

    config(['contact.default.country' => 'PH']);
    config(['contact.default.bank_code' => 'GXCHPHM2XXX']);

    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);
});

// =========================
// ValidateRedemptionCode
// =========================

test('can validate redeemable voucher code', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/validate', [
        'code' => $voucher->code,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'voucher',
                'can_redeem',
                'required_validation',
                'required_inputs',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'can_redeem' => true,
                'required_inputs' => [],
            ],
        ]);
});

test('can validate voucher with secret requirement', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => '1234',
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/validate', [
        'code' => $voucher->code,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'can_redeem' => true,
                'required_validation' => [
                    'secret' => true,
                ],
            ],
        ]);
});

test('validates voucher code is required', function () {
    $response = $this->postJson('/api/v1/redeem/validate', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('returns error for invalid voucher code', function () {
    $response = $this->postJson('/api/v1/redeem/validate', [
        'code' => 'INVALID',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Invalid voucher code.',
        ]);
});

test('returns cannot redeem for expired voucher', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::seconds(1), // Expires in 1 second
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    // Wait for expiration
    sleep(2);

    $response = $this->postJson('/api/v1/redeem/validate', [
        'code' => $voucher->code,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'can_redeem' => false,
            ],
        ]);
});

test('returns cannot redeem for already redeemed voucher', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    // Redeem the voucher
    $contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);

    \LBHurtado\Voucher\Actions\RedeemVoucher::run($contact, $voucher->code);

    $response = $this->postJson('/api/v1/redeem/validate', [
        'code' => $voucher->code,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'can_redeem' => false,
            ],
        ]);
});

// =========================
// SubmitWallet (Redemption)
// =========================

test('can redeem voucher with mobile number', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'message',
                'voucher',
                'rider',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'message' => 'Voucher redeemed successfully!',
            ],
        ]);

    // Verify voucher was redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
});

test('can redeem voucher with secret', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => '1234',
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => 'Thank you!', 'url' => 'https://example.com'],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => '1234',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Voucher redeemed successfully!',
                'rider' => [
                    'message' => 'Thank you!',
                    'url' => 'https://example.com',
                ],
            ],
        ]);
});

test('cannot redeem voucher with wrong secret', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => '1234',
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'wrong',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Invalid secret code.',
        ]);
});

test('can redeem voucher with inputs', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['email', 'name'],
        ],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::hours(12),
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
        'inputs' => [
            'email' => 'test@example.com',
            'name' => 'John Doe',
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Voucher redeemed successfully!',
            ],
        ]);
});

test('validates mobile is required for redemption', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mobile']);
});

test('validates mobile format for redemption', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => 'invalid',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['mobile']);
});

test('cannot redeem invalid voucher code', function () {
    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => 'INVALID',
        'mobile' => '09171234567',
    ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Invalid voucher code.',
        ]);
});

test('cannot redeem already redeemed voucher', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    // First redemption
    $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);

    // Second redemption attempt
    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09181234567',
        'country' => 'PH',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'This voucher has already been redeemed.',
        ]);
});

test('cannot redeem expired voucher', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => CarbonInterval::seconds(1),
    ]);

    auth()->login($this->user);
    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    // Wait for expiration
    sleep(2);

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'This voucher has expired.',
        ]);
});

test('can redeem with bank account details', function () {
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1);
    $voucher = $vouchers->first();

    $response = $this->postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_code' => 'BPIPH',
        'account_number' => '1234567890',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Voucher redeemed successfully!',
            ],
        ]);
});
