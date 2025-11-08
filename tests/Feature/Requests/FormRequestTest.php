<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Requests\Voucher\VoucherInstructionDataRequest;
use App\Http\Requests\Redeem\{WalletFormRequest, PluginFormRequest};
use App\Validators\VoucherRedemptionValidator;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use FrittenKeeZ\Vouchers\Facades\Vouchers;

uses(RefreshDatabase::class);

test('VoucherInstructionDataRequest has correct rules', function () {
    $request = new VoucherInstructionDataRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray();
    expect($rules)->toHaveKey('cash.amount');
    expect($rules)->toHaveKey('inputs.fields');
    expect($rules)->toHaveKey('count');
});

test('VoucherInstructionDataRequest has custom messages', function () {
    $request = new VoucherInstructionDataRequest();
    $messages = $request->messages();
    
    expect($messages)->toBeArray();
    expect($messages)->toHaveKey('cash.amount.required');
    expect($messages)->toHaveKey('count.required');
});

test('VoucherInstructionDataRequest has custom attributes', function () {
    $request = new VoucherInstructionDataRequest();
    $attributes = $request->attributes();
    
    expect($attributes)->toBeArray();
    expect($attributes)->toHaveKey('cash.amount');
    expect($attributes['cash.amount'])->toBe('amount');
});

test('WalletFormRequest has correct rules', function () {
    $request = new WalletFormRequest();
    $rules = $request->rules();
    
    expect($rules)->toBeArray();
    expect($rules)->toHaveKey('mobile');
    expect($rules)->toHaveKey('country');
    expect($rules)->toHaveKey('bank_code');
    expect($rules)->toHaveKey('account_number');
    expect($rules)->toHaveKey('secret');
});

test('WalletFormRequest has custom messages', function () {
    $request = new WalletFormRequest();
    $messages = $request->messages();
    
    expect($messages)->toBeArray();
    expect($messages)->toHaveKey('mobile.required');
    expect($messages['mobile.required'])->toBe('Please enter your mobile number.');
});

test('PluginFormRequest has custom messages', function () {
    $request = new PluginFormRequest();
    $messages = $request->messages();
    
    expect($messages)->toBeArray();
    expect($messages)->toHaveKey('name.required');
    expect($messages)->toHaveKey('email.required');
    expect($messages)->toHaveKey('signature.required');
});

test('PluginFormRequest has custom attributes', function () {
    $request = new PluginFormRequest();
    $attributes = $request->attributes();
    
    expect($attributes)->toBeArray();
    expect($attributes)->toHaveKey('name');
    expect($attributes['name'])->toBe('full name');
});

test('VoucherRedemptionValidator can be instantiated', function () {
    $user = \App\Models\User::factory()->create();
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $validator = new VoucherRedemptionValidator($voucher);
    
    expect($validator)->toBeInstanceOf(VoucherRedemptionValidator::class);
    expect($validator->errors())->toBeInstanceOf(\Illuminate\Support\MessageBag::class);
});

test('VoucherRedemptionValidator validates mobile when not required', function () {
    $user = \App\Models\User::factory()->create();
    
    // Voucher without mobile validation
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $validator = new VoucherRedemptionValidator($voucher);
    $result = $validator->validateMobile('+639171234567');
    
    expect($result)->toBeTrue();
    expect($validator->passes())->toBeTrue();
});

test('VoucherRedemptionValidator validates secret when not required', function () {
    $user = \App\Models\User::factory()->create();
    
    // Voucher without secret
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $validator = new VoucherRedemptionValidator($voucher);
    $result = $validator->validateSecret('any-secret');
    
    expect($result)->toBeTrue();
    expect($validator->passes())->toBeTrue();
});

test('VoucherRedemptionValidator has helper methods', function () {
    $user = \App\Models\User::factory()->create();
    
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $validator = new VoucherRedemptionValidator($voucher);
    
    expect($validator->passes())->toBeTrue();
    expect($validator->fails())->toBeFalse();
    
    // Add error
    $validator->errors()->add('test', 'Test error');
    
    expect($validator->passes())->toBeFalse();
    expect($validator->fails())->toBeTrue();
});
