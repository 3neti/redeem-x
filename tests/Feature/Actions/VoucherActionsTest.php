<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\Voucher\{ValidateVoucherCode, ProcessRedemption};
use App\Actions\Payment\DisbursePayment;
use App\Actions\Notification\SendFeedback;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Contact\Models\Contact;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Propaganistas\LaravelPhone\PhoneNumber;

uses(RefreshDatabase::class);

test('ValidateVoucherCode validates existing voucher', function () {
    $user = \App\Models\User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $result = ValidateVoucherCode::run($voucher->code);
    
    expect($result)->toBeArray();
    expect($result['valid'])->toBeTrue();
    expect($result['voucher'])->toBeInstanceOf(Voucher::class);
    expect($result['voucher']->code)->toBe($voucher->code);
});

test('ValidateVoucherCode rejects non-existent voucher', function () {
    $result = ValidateVoucherCode::run('INVALID-CODE');
    
    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toBe('Voucher code not found.');
});

test('ValidateVoucherCode rejects expired voucher', function () {
    $user = \App\Models\User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->withExpireTime(now()->subDay()) // Expired yesterday
        ->create();
    
    $result = ValidateVoucherCode::run($voucher->code);
    
    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toBe('This voucher has expired.');
});

test('ValidateVoucherCode normalizes code case', function () {
    $user = \App\Models\User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    // Test with lowercase
    $result = ValidateVoucherCode::run(strtolower($voucher->code));
    
    expect($result['valid'])->toBeTrue();
});

test('ProcessRedemption action can be instantiated', function () {
    $action = new ProcessRedemption();
    
    expect($action)->toBeInstanceOf(ProcessRedemption::class);
});

test('DisbursePayment action logs payment info', function () {
    $user = \App\Models\User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $contact = Contact::factory()->create();
    $bankAccount = [
        'bank_code' => 'BDO',
        'account_number' => '1234567890',
    ];
    
    $result = DisbursePayment::run($voucher, $contact, $bankAccount);
    
    expect($result)->toBeTrue();
});

test('SendFeedback action handles no feedback configured', function () {
    $user = \App\Models\User::factory()->create();
    $instructions = VoucherInstructionsData::generateFromScratch();
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    $contact = Contact::factory()->create();
    
    // No feedback configured, should return false
    $result = SendFeedback::run($voucher, $contact);
    
    expect($result)->toBeFalse();
});

test('SendFeedback action handles webhook feedback', function () {
    $user = \App\Models\User::factory()->create();
    
    // Create instructions with webhook feedback
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['feedback'] = [
        'email' => null,
        'mobile' => null,
        'webhook' => 'https://httpbin.org/post', // Test webhook endpoint
    ];
    $instructions = VoucherInstructionsData::from($base);
    
    $voucher = Vouchers::withMetadata(['instructions' => $instructions->toCleanArray()])
        ->withOwner($user)
        ->create();
    
    // Mark as redeemed for realistic test
    $contact = Contact::factory()->create();
    Vouchers::redeem($voucher->code, $contact);
    
    $result = SendFeedback::run($voucher->fresh(), $contact);
    
    expect($result)->toBeTrue();
});
