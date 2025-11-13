<?php

use App\Services\VoucherTemplateContextBuilder;
use LBHurtado\Cash\Data\CashData;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Voucher\Data\ModelData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Spatie\LaravelData\DataCollection;

it('builds basic context from voucher data', function () {
    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context)->toHaveKeys([
        'code',
        'status',
        'created_at',
        'redeemed_at',
    ]);
    expect($context['code'])->toBe('TEST-123');
    expect($context['status'])->toBe('active');
});

it('handles voucher with contact information', function () {
    $contact = new ContactData(
        mobile: '+639171234567',
        country: 'PH',
        bank_account: 'GCASH:09171234567',
        bank_code: 'GXCHPHM2XXX',
        account_number: '09171234567',
        name: 'Juan dela Cruz',
    );

    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        contact: $contact,
        status: 'redeemed',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['mobile'])->toBe('+639171234567');
    expect($context['contact_name'])->toBe('Juan dela Cruz');
    expect($context['bank_account'])->toBe('GCASH:09171234567');
    expect($context['bank_code'])->toBe('GXCHPHM2XXX');
    expect($context['account_number'])->toBe('09171234567');
});

it('handles missing contact gracefully', function () {
    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        contact: null,
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['mobile'])->toBeNull();
    expect($context['contact_name'])->toBeNull();
});

it('flattens input fields into context', function () {
    $inputs = [
        new InputData('signature', 'data:image/png;base64,iVBORw0KG...'),
        new InputData('account_number', '09171234567'),
        new InputData('custom_field', 'custom_value'),
    ];

    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, $inputs),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['signature'])->toBe('data:image/png;base64,iVBORw0KG...');
    expect($context['account_number'])->toBe('09171234567');
    expect($context['custom_field'])->toBe('custom_value');
});

it('formats location input to formatted_address', function () {
    $locationJson = json_encode([
        'address' => [
            'formatted' => '123 Main St, Manila, Philippines',
        ],
    ]);

    $inputs = [
        new InputData('location', $locationJson),
    ];

    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, $inputs),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['formatted_address'])->toBe('123 Main St, Manila, Philippines');
    expect($context['location'])->toBe($locationJson);
});

it('handles missing location gracefully', function () {
    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['formatted_address'])->toBeNull();
});

it('handles malformed location json gracefully', function () {
    $inputs = [
        new InputData('location', 'invalid json'),
    ];

    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, $inputs),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['formatted_address'])->toBeNull();
});

it('includes owner information when available', function () {
    $owner = new ModelData(
        name: 'John Doe',
        email: 'john@example.com',
        mobile: '+639181234567',
    );

    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: $owner,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['owner_name'])->toBe('John Doe');
    expect($context['owner_email'])->toBe('john@example.com');
    expect($context['owner_mobile'])->toBe('+639181234567');
});

it('handles missing owner gracefully', function () {
    $voucher = new VoucherData(
        code: 'TEST-123',
        owner: null,
        created_at: now(),
        starts_at: null,
        expires_at: null,
        redeemed_at: null,
        processed_on: null,
        processed: false,
        instructions: null,
        inputs: new DataCollection(InputData::class, []),
        status: 'active',
    );

    $context = VoucherTemplateContextBuilder::build($voucher);

    expect($context['owner_name'])->toBeNull();
    expect($context['owner_email'])->toBeNull();
    expect($context['owner_mobile'])->toBeNull();
});

it('returns available variables list', function () {
    $variables = VoucherTemplateContextBuilder::getAvailableVariables();

    expect($variables)->toBeArray();
    expect($variables)->toHaveKeys([
        'code',
        'amount',
        'formatted_amount',
        'currency',
        'mobile',
        'formatted_address',
    ]);
});
