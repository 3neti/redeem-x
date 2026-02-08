<?php

use App\Services\InputFieldMapper;

test('maps KYC fields correctly', function () {
    $mapper = new InputFieldMapper;

    $input = [
        'full_name' => 'HURTADO LESTER BIADORA',
        'date_of_birth' => '1970-04-21',
        'address' => '8 West Maya Drive, Philam Homes, QC',
    ];

    $mapped = $mapper->map($input);

    expect($mapped)->toHaveKey('name', 'HURTADO LESTER BIADORA')
        ->toHaveKey('birth_date', '1970-04-21')
        ->toHaveKey('address', '8 West Maya Drive, Philam Homes, QC')
        ->not->toHaveKey('full_name')
        ->not->toHaveKey('date_of_birth');
});

test('maps OTP fields correctly', function () {
    $mapper = new InputFieldMapper;

    $input = [
        'otp_code' => '123456',
    ];

    $mapped = $mapper->map($input);

    expect($mapped)->toHaveKey('otp', '123456')
        ->not->toHaveKey('otp_code');
});

test('preserves unmapped fields', function () {
    $mapper = new InputFieldMapper;

    $input = [
        'full_name' => 'John Doe',
        'custom_field' => 'custom value',
        'mobile' => '09171234567',
    ];

    $mapped = $mapper->map($input);

    expect($mapped)->toHaveKey('name', 'John Doe')
        ->toHaveKey('custom_field', 'custom value')
        ->toHaveKey('mobile', '09171234567');
});

test('handles empty inputs', function () {
    $mapper = new InputFieldMapper;

    $mapped = $mapper->map([]);

    expect($mapped)->toBe([]);
});

test('allows adding custom mappings at runtime', function () {
    $mapper = new InputFieldMapper;

    $mapper->addMapping('customer_name', 'name');

    $input = ['customer_name' => 'Jane Doe'];
    $mapped = $mapper->map($input);

    expect($mapped)->toHaveKey('name', 'Jane Doe')
        ->not->toHaveKey('customer_name');
});

test('returns all configured mappings', function () {
    $mapper = new InputFieldMapper;

    $mappings = $mapper->getMappings();

    expect($mappings)->toBeArray()
        ->toHaveKey('full_name', 'name')
        ->toHaveKey('date_of_birth', 'birth_date')
        ->toHaveKey('otp_code', 'otp');
});
