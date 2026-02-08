<?php

use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Cash\Enums\CashStatus;
use LBHurtado\Cash\Models\Cash;

uses(RefreshDatabase::class);

test('cash package is loaded and autoloaded', function () {
    expect(class_exists(Cash::class))->toBeTrue()
        ->and(enum_exists(CashStatus::class))->toBeTrue();
});

test('cash model can be instantiated', function () {
    $cash = new Cash;

    expect($cash)->toBeInstanceOf(Cash::class);
});

test('cash can be created with amount and currency', function () {
    $cash = Cash::create([
        'amount' => 100.50,
        'currency' => 'PHP',
    ]);

    expect($cash->exists)->toBeTrue()
        ->and($cash->amount)->toBeInstanceOf(Money::class)
        ->and($cash->amount->getAmount()->toFloat())->toBe(100.50)
        ->and($cash->currency)->toBe('PHP');
});

test('cash uses default currency when not specified', function () {
    $cash = Cash::create([
        'amount' => 50,
    ]);

    expect($cash->currency)->toBe('PHP'); // Default from Number::defaultCurrency()
});

test('cash can have metadata', function () {
    $cash = Cash::create([
        'amount' => 200,
        'meta' => ['source' => 'test', 'reference' => '12345'],
    ]);

    expect($cash->meta)->toBeInstanceOf(\Illuminate\Database\Eloquent\Casts\ArrayObject::class)
        ->and($cash->meta['source'])->toBe('test')
        ->and($cash->meta['reference'])->toBe('12345');
});

test('cash can have status enum value', function () {
    // Status enums exist and can be accessed
    expect(CashStatus::MINTED)->toBeInstanceOf(CashStatus::class)
        ->and(CashStatus::EXPIRED)->toBeInstanceOf(CashStatus::class)
        ->and(CashStatus::MINTED->value)->toBe('minted')
        ->and(CashStatus::EXPIRED->value)->toBe('expired');
});

test('cash can have secret and verify it', function () {
    $secret = 'test-secret-123';
    $cash = Cash::create([
        'amount' => 100,
        'secret' => $secret,
    ]);

    // Secret is hashed, so we verify instead of comparing directly
    expect($cash->secret)->not->toBe($secret)
        ->and($cash->verifySecret($secret))->toBeTrue()
        ->and($cash->verifySecret('wrong-secret'))->toBeFalse();
});

test('cash has wallet traits', function () {
    $cash = Cash::create(['amount' => 100]);

    // Cash implements wallet functionality
    expect(method_exists($cash, 'deposit'))->toBeTrue()
        ->and(method_exists($cash, 'withdraw'))->toBeTrue()
        ->and(method_exists($cash, 'canWithdraw'))->toBeTrue();
});

test('cash table exists in database', function () {
    expect(\Schema::hasTable('cash'))->toBeTrue();
});

test('cash has required columns', function () {
    $columns = \Schema::getColumnListing('cash');

    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('amount', $columns))->toBeTrue()
        ->and(in_array('currency', $columns))->toBeTrue()
        ->and(in_array('meta', $columns))->toBeTrue()
        ->and(in_array('secret', $columns))->toBeTrue();
});
