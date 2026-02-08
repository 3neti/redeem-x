<?php

use LBHurtado\Merchant\Services\VendorAliasService;

test('normalizes alias to uppercase', function () {
    $service = new VendorAliasService;

    expect($service->normalize('gcash'))->toBe('GCASH');
    expect($service->normalize('  maya  '))->toBe('MAYA');
    expect($service->normalize('BDO'))->toBe('BDO');
    expect($service->normalize('sm123'))->toBe('SM123');
});

test('validates alias format - valid aliases', function () {
    $service = new VendorAliasService;

    // Valid: 3-8 chars, starts with letter, uppercase letters/digits only
    expect($service->validate('ABC'))->toBeTrue();
    expect($service->validate('GCASH'))->toBeTrue();
    expect($service->validate('SM12345'))->toBeTrue();
    expect($service->validate('A'))->toBeFalse(); // Too short
    expect($service->validate('AB'))->toBeFalse(); // Too short
});

test('validates alias format - invalid aliases', function () {
    $service = new VendorAliasService;

    // Too short
    expect($service->validate('AB'))->toBeFalse();

    // Too long
    expect($service->validate('TOOLONGALIAS'))->toBeFalse();
    expect($service->validate('ABCDEFGHI'))->toBeFalse();

    // Starts with number
    expect($service->validate('123ABC'))->toBeFalse();
    expect($service->validate('1GCASH'))->toBeFalse();

    // Special characters
    expect($service->validate('ABC-DEF'))->toBeFalse();
    expect($service->validate('ABC_DEF'))->toBeFalse();
    expect($service->validate('ABC DEF'))->toBeFalse();
    expect($service->validate('ABC@DEF'))->toBeFalse();
});

test('rejects non-ascii characters', function () {
    $service = new VendorAliasService;

    expect($service->validate('GCAŚH'))->toBeFalse(); // Polish S with acute
    expect($service->validate('МАУ'))->toBeFalse(); // Cyrillic
    expect($service->validate('GCA$H'))->toBeFalse(); // Dollar sign
});

test('validates must be uppercase for validation', function () {
    $service = new VendorAliasService;

    // Validation expects uppercase (after normalization)
    expect($service->validate('GCASH'))->toBeTrue();
    expect($service->validate('gcash'))->toBeFalse(); // lowercase fails
    expect($service->validate('GCash'))->toBeFalse(); // mixed case fails
});
