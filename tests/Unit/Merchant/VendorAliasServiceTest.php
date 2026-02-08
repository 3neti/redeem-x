<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LBHurtado\Merchant\Services\VendorAliasService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new VendorAliasService;
});

test('normalizes alias to uppercase', function () {
    expect($this->service->normalize('gcash'))->toBe('GCASH');
    expect($this->service->normalize('  maya  '))->toBe('MAYA');
    expect($this->service->normalize('BDO'))->toBe('BDO');
    expect($this->service->normalize('sm123'))->toBe('SM123');
});

test('validates correct alias format', function () {
    // Valid: 3-8 chars, starts with letter
    expect($this->service->validate('ABC'))->toBeTrue();
    expect($this->service->validate('GCASH'))->toBeTrue();
    expect($this->service->validate('SM12345'))->toBeTrue();
    expect($this->service->validate('A'))->toBeFalse(); // Too short
    expect($this->service->validate('AB'))->toBeFalse(); // Too short
});

test('rejects invalid alias format', function () {
    // Too short
    expect($this->service->validate('AB'))->toBeFalse();

    // Too long
    expect($this->service->validate('TOOLONGALIAS'))->toBeFalse();
    expect($this->service->validate('ABCDEFGHI'))->toBeFalse();

    // Starts with number
    expect($this->service->validate('123ABC'))->toBeFalse();
    expect($this->service->validate('1GCASH'))->toBeFalse();

    // Special characters
    expect($this->service->validate('ABC-DEF'))->toBeFalse();
    expect($this->service->validate('ABC_DEF'))->toBeFalse();
    expect($this->service->validate('ABC DEF'))->toBeFalse();
});

test('rejects non-ascii characters', function () {
    expect($this->service->validate('GCAŚH'))->toBeFalse(); // Polish S
    expect($this->service->validate('МАУ'))->toBeFalse(); // Cyrillic
    expect($this->service->validate('GCA$H'))->toBeFalse();
});

test('validation requires uppercase', function () {
    expect($this->service->validate('GCASH'))->toBeTrue();
    expect($this->service->validate('gcash'))->toBeFalse();
    expect($this->service->validate('GCash'))->toBeFalse();
});

test('detects reserved aliases', function () {
    DB::table('reserved_vendor_aliases')->insert([
        ['alias' => 'ADMIN', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
        ['alias' => 'GCASH', 'reason' => 'EMI', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect($this->service->isReserved('ADMIN'))->toBeTrue();
    expect($this->service->isReserved('GCASH'))->toBeTrue();
    expect($this->service->isReserved('MYSHOP'))->toBeFalse();
});

test('reserved check is case insensitive', function () {
    DB::table('reserved_vendor_aliases')->insert([
        ['alias' => 'ADMIN', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect($this->service->isReserved('admin'))->toBeTrue();
    expect($this->service->isReserved('Admin'))->toBeTrue();
    expect($this->service->isReserved('ADMIN'))->toBeTrue();
});
