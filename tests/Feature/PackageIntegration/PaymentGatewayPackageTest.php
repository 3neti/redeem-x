<?php

use App\Models\User;
use LBHurtado\PaymentGateway\Models\Merchant;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payment gateway package is loaded and autoloaded', function () {
    expect(class_exists(Merchant::class))->toBeTrue()
        ->and(interface_exists(PaymentGatewayInterface::class))->toBeTrue()
        ->and(enum_exists(SettlementRail::class))->toBeTrue()
        ->and(trait_exists(\LBHurtado\PaymentGateway\Traits\HasMerchant::class))->toBeTrue();
});

test('merchant model can be instantiated', function () {
    $merchant = new Merchant();
    
    expect($merchant)->toBeInstanceOf(Merchant::class);
});

test('merchant model can be created', function () {
    $merchant = Merchant::create([
        'code' => 'GROCERY01',
        'name' => 'Test Grocery Store',
        'city' => 'Manila',
    ]);
    
    expect($merchant->exists)->toBeTrue()
        ->and($merchant->code)->toBe('GROCERY01')
        ->and($merchant->name)->toBe('Test Grocery Store')
        ->and($merchant->city)->toBe('Manila');
});

test('merchant model has fillable properties', function () {
    $merchant = new Merchant();
    
    expect($merchant->getFillable())->toBe(['code', 'name', 'city']);
});

test('settlement rail enum has instapay and pesonet', function () {
    expect(SettlementRail::INSTAPAY)->toBeInstanceOf(SettlementRail::class)
        ->and(SettlementRail::PESONET)->toBeInstanceOf(SettlementRail::class)
        ->and(SettlementRail::INSTAPAY->value)->toBe('INSTAPAY')
        ->and(SettlementRail::PESONET->value)->toBe('PESONET');
});

test('user has merchant trait', function () {
    $user = User::factory()->create();
    
    expect(method_exists($user, 'merchant'))->toBeTrue()
        ->and(method_exists($user, 'setMerchant'))->toBeTrue();
});

test('user can be associated with merchant', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'code' => 'GROCERY01',
        'name' => 'Test Grocery',
        'city' => 'Manila',
    ]);
    
    $user->setMerchant($merchant);
    
    expect($user->merchant()->exists())->toBeTrue()
        ->and($user->merchant->id)->toBe($merchant->id);
});

test('user can set merchant via attribute', function () {
    $user = User::factory()->create();
    $merchant = Merchant::create([
        'code' => 'GROCERY02',
        'name' => 'Another Grocery',
        'city' => 'Quezon City',
    ]);
    
    $user->merchant = $merchant;
    
    expect($user->merchant->id)->toBe($merchant->id);
});

test('user can have only one merchant', function () {
    $user = User::factory()->create();
    
    $merchant1 = Merchant::create(['code' => 'M1', 'name' => 'Merchant 1', 'city' => 'Manila']);
    $merchant2 = Merchant::create(['code' => 'M2', 'name' => 'Merchant 2', 'city' => 'Cebu']);
    
    $user->setMerchant($merchant1);
    expect($user->merchant->id)->toBe($merchant1->id);
    
    // Setting a new merchant should replace the old one
    $user->setMerchant($merchant2);
    expect($user->merchant->id)->toBe($merchant2->id)
        ->and($user->merchant()->count())->toBe(1);
});

test('payment gateway interface defines required methods', function () {
    $reflection = new ReflectionClass(PaymentGatewayInterface::class);
    $methods = $reflection->getMethods();
    $methodNames = array_map(fn($m) => $m->getName(), $methods);
    
    expect($methodNames)->toContain('generate', 'confirmDeposit', 'disburse', 'confirmDisbursement');
});

test('merchants table exists in database', function () {
    expect(\Schema::hasTable('merchants'))->toBeTrue();
});

test('merchants table has required columns', function () {
    $columns = \Schema::getColumnListing('merchants');
    
    expect(in_array('id', $columns))->toBeTrue()
        ->and(in_array('code', $columns))->toBeTrue()
        ->and(in_array('name', $columns))->toBeTrue()
        ->and(in_array('city', $columns))->toBeTrue();
});

test('merchant_user pivot table exists', function () {
    expect(\Schema::hasTable('merchant_user'))->toBeTrue();
});

test('merchant_user table has required columns', function () {
    $columns = \Schema::getColumnListing('merchant_user');
    
    expect(in_array('merchant_id', $columns))->toBeTrue()
        ->and(in_array('user_id', $columns))->toBeTrue();
});
