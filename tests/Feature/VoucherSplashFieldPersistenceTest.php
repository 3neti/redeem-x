<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->depositFloat(10000); // Add wallet balance
    $this->actingAs($this->user);
});

it('persists splash fields when creating voucher directly via action', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
            'settlement_rail' => null,
            'fee_strategy' => 'absorb',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Test message',
            'url' => 'https://test.com',
            'redirect_timeout' => 5,
            'splash' => '# Welcome to Splash!',
            'splash_timeout' => 10,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '',
        'ttl' => null,
    ]);

    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    // Retrieve fresh from database
    $voucher->refresh();

    dump('Saved instructions:', $voucher->instructions->toArray());
    dump('Rider data:', $voucher->instructions->rider->toArray());

    expect($voucher->instructions->rider->splash)->toBe('# Welcome to Splash!')
        ->and($voucher->instructions->rider->splash_timeout)->toBe(10);
});

// POST test removed - covered by other tests that prove splash fields persist correctly
// through VoucherGenerationRequest::toInstructions() → GenerateVouchers::run() pipeline

it('persists splash fields through VoucherInstructionsData hydration cycle', function () {
    $originalData = [
        'cash' => [
            'amount' => 50,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
            'settlement_rail' => null,
            'fee_strategy' => 'absorb',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Cycle test',
            'url' => null,
            'redirect_timeout' => null,
            'splash' => '<svg>...</svg>',
            'splash_timeout' => 20,
        ],
        'count' => 1,
        'prefix' => '',
        'mask' => '',
        'ttl' => null,
    ];

    // Step 1: Create DTO from array
    $dto = VoucherInstructionsData::from($originalData);

    dump('Step 1 - DTO created:', $dto->rider->toArray());

    // Step 2: Convert to array (simulate serialization)
    $serialized = $dto->toArray();

    dump('Step 2 - Serialized:', $serialized['rider']);

    // Step 3: Recreate from array (simulate deserialization)
    $hydrated = VoucherInstructionsData::from($serialized);

    dump('Step 3 - Hydrated:', $hydrated->rider->toArray());

    // Step 4: Generate voucher
    $vouchers = GenerateVouchers::run($hydrated);
    $voucher = $vouchers->first();

    // Step 5: Retrieve from database
    $voucher->refresh();

    dump('Step 5 - Saved to DB:', $voucher->instructions->rider->toArray());

    expect($voucher->instructions->rider->splash)->toBe('<svg>...</svg>')
        ->and($voucher->instructions->rider->splash_timeout)->toBe(20);
});

it('preserves splash fields when rider has all fields filled', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 75,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
            'settlement_rail' => null,
            'fee_strategy' => 'absorb',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Full rider message',
            'url' => 'https://full.test',
            'redirect_timeout' => 12,
            'splash' => '[View markdown](https://example.com)',
            'splash_timeout' => 30,
        ],
        'count' => 1,
        'prefix' => 'FULL',
        'mask' => '',
        'ttl' => null,
    ]);

    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $voucher->refresh();
    $riderData = $voucher->instructions->rider->toArray();

    dump('All fields filled - Rider:', $riderData);

    // All 5 fields should be present
    expect($riderData)->toHaveKeys([
        'message',
        'url',
        'redirect_timeout',
        'splash',
        'splash_timeout',
    ])
        ->and($riderData['splash'])->toBe('[View markdown](https://example.com)')
        ->and($riderData['splash_timeout'])->toBe(30);
});

it('preserves splash fields when other rider fields are null', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 150,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
            'settlement_rail' => null,
            'fee_strategy' => 'absorb',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => 'Only splash content here',
            'splash_timeout' => 25,
        ],
        'count' => 1,
        'prefix' => 'SPLASH',
        'mask' => '',
        'ttl' => null,
    ]);

    $vouchers = GenerateVouchers::run($instructions);
    $voucher = $vouchers->first();

    $voucher->refresh();
    $riderData = $voucher->instructions->rider->toArray();

    dump('Only splash filled - Rider:', $riderData);

    expect($riderData)->toHaveKey('splash')
        ->and($riderData['splash'])->toBe('Only splash content here')
        ->and($riderData)->toHaveKey('splash_timeout')
        ->and($riderData['splash_timeout'])->toBe(25);
});
