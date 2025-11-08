<?php

use LBHurtado\MoneyIssuer\Facades\MoneyIssuer;
use LBHurtado\MoneyIssuer\Contracts\MoneyIssuerServiceInterface;
use LBHurtado\Voucher\Models\MoneyIssuer as MoneyIssuerModel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('money issuer package is loaded and autoloaded', function () {
    expect(interface_exists(MoneyIssuerServiceInterface::class))->toBeTrue()
        ->and(class_exists(MoneyIssuer::class))->toBeTrue()
        ->and(class_exists(MoneyIssuerModel::class))->toBeTrue();
});

test('money issuer model can be instantiated', function () {
    $moneyIssuer = new MoneyIssuerModel();
    
    expect($moneyIssuer)->toBeInstanceOf(MoneyIssuerModel::class);
});

test('money issuer model can be created', function () {
    $moneyIssuer = MoneyIssuerModel::create([
        'code' => 'NETBANK',
        'name' => 'NetBank',
    ]);
    
    expect($moneyIssuer->exists)->toBeTrue()
        ->and($moneyIssuer->code)->toBe('NETBANK')
        ->and($moneyIssuer->name)->toBe('NetBank');
});

test('money issuer model has fillable properties', function () {
    $moneyIssuer = new MoneyIssuerModel();
    
    expect($moneyIssuer->getFillable())->toBe(['code', 'name']);
});

test('money issuer interface defines required methods', function () {
    $reflection = new ReflectionClass(MoneyIssuerServiceInterface::class);
    $methods = $reflection->getMethods();
    $methodNames = array_map(fn($m) => $m->getName(), $methods);
    
    expect($methodNames)->toContain('checkBalance', 'deposit', 'withdraw', 'transfer');
});

test('money issuer facade is properly configured', function () {
    expect(MoneyIssuer::getFacadeRoot())->not->toBeNull();
});

test('money issuer model supports common EMI providers', function () {
    $netbank = MoneyIssuerModel::create(['code' => 'NETBANK', 'name' => 'NetBank']);
    $icash = MoneyIssuerModel::create(['code' => 'ICASH', 'name' => 'iCash']);
    
    expect(MoneyIssuerModel::where('code', 'NETBANK')->exists())->toBeTrue()
        ->and(MoneyIssuerModel::where('code', 'ICASH')->exists())->toBeTrue();
});

test('money issuers table exists in database', function () {
    expect(\Schema::hasTable('money_issuers'))->toBeTrue();
});

test('money issuers table has required columns', function () {
    $columns = \Schema::getColumnListing('money_issuers');
    
    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('code', $columns))->toBeTrue()
        ->and(in_array('name', $columns))->toBeTrue();
});
