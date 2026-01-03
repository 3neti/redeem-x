<?php

namespace LBHurtado\Merchant\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LBHurtado\Merchant\Rules\ValidVendorAlias;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed some reserved aliases for testing
    DB::table('reserved_vendor_aliases')->insert([
        ['alias' => 'ADMIN', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
        ['alias' => 'ROOT', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
        ['alias' => 'GCASH', 'reason' => 'EMI', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('passes validation for valid alias', function () {
    $rule = new ValidVendorAlias();
    $failed = false;
    
    $rule->validate('alias', 'VNDR1', function () use (&$failed) {
        $failed = true;
    });
    
    expect($failed)->toBeFalse();
});

test('passes validation for alias with digits', function () {
    $rule = new ValidVendorAlias();
    $failed = false;
    
    $rule->validate('alias', 'SHOP123', function () use (&$failed) {
        $failed = true;
    });
    
    expect($failed)->toBeFalse();
});

test('passes validation for minimum length alias', function () {
    $rule = new ValidVendorAlias();
    $failed = false;
    $minLength = config('merchant.alias.min_length', 3);
    
    // Create alias with exactly min_length characters
    $alias = str_repeat('A', $minLength);
    
    $rule->validate('alias', $alias, function () use (&$failed) {
        $failed = true;
    });
    
    expect($failed)->toBeFalse();
});

test('passes validation for maximum length alias', function () {
    $rule = new ValidVendorAlias();
    $failed = false;
    $maxLength = config('merchant.alias.max_length', 8);
    
    // Create alias with exactly max_length characters
    $alias = str_repeat('A', $maxLength);
    
    $rule->validate('alias', $alias, function () use (&$failed) {
        $failed = true;
    });
    
    expect($failed)->toBeFalse();
});

test('fails validation for alias starting with digit', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', '1SHOP', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('start with a letter');
});

test('fails validation for lowercase alias', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', 'shop', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('uppercase');
});

test('fails validation for alias with special characters', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', 'SHOP-1', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('uppercase letters and digits');
});

test('fails validation for alias with spaces', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', 'MY SHOP', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('uppercase letters and digits');
});

test('fails validation for too short alias', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    $minLength = config('merchant.alias.min_length', 3);
    
    // Create alias shorter than min_length
    $alias = str_repeat('A', $minLength - 1);
    
    $rule->validate('alias', $alias, function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain("{$minLength}");
});

test('fails validation for too long alias', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    $maxLength = config('merchant.alias.max_length', 8);
    
    // Create alias longer than max_length
    $alias = str_repeat('A', $maxLength + 1);
    
    $rule->validate('alias', $alias, function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain("{$maxLength}");
});

test('fails validation for reserved alias', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', 'ADMIN', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('reserved');
});

test('fails validation for reserved alias case insensitive', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    // Test with lowercase (will be normalized to uppercase)
    $rule->validate('alias', 'admin', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->not->toBeNull()
        ->toContain('reserved');
});

test('normalizes alias before validation', function () {
    $rule = new ValidVendorAlias();
    $failed = false;
    
    // Input with extra spaces and mixed case
    $rule->validate('alias', '  shop1  ', function () use (&$failed) {
        $failed = true;
    });
    
    // Should pass after normalization (trim + uppercase)
    expect($failed)->toBeFalse();
});

test('error message includes configured min and max length', function () {
    config(['merchant.alias.min_length' => 2]);
    config(['merchant.alias.max_length' => 10]);
    
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('alias', 'TOOLONGALIAS123', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->toContain('2-10 characters');
});

test('error message includes attribute name', function () {
    $rule = new ValidVendorAlias();
    $failMessage = null;
    
    $rule->validate('merchant_alias', '123', function ($message) use (&$failMessage) {
        $failMessage = $message;
    });
    
    expect($failMessage)
        ->toContain('merchant_alias');
});
