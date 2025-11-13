<?php

/**
 * Unit tests for SendFeedbacksNotification class.
 * 
 * These tests focus on:
 * - Notification class structure and methods
 * - Channel resolution logic
 * - Return types and data structures
 * - NOT content/template validation (see NotificationContentTest)
 */

use App\Models\User;
use App\Notifications\SendFeedbacksNotification;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Tests\Concerns\SetsUpRedemptionEnvironment;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class, SetsUpRedemptionEnvironment::class);

beforeEach(function () {
    Notification::fake();
    $this->setUpRedemptionEnvironment();
});

test('notification can be created with voucher code', function () {
    $user = $this->getSystemUser();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => []],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
            'webhook' => 'https://webhook.site/test',
        ],
        'inputs' => ['fields' => []], 'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    $generateAction = app(GenerateVouchers::class);
    $voucher = $generateAction->handle($instructions, $user)->first();
    
    $notification = new SendFeedbacksNotification($voucher->code);
    
    expect($notification)->toBeInstanceOf(SendFeedbacksNotification::class);
});

test('notification returns correct channels', function () {
    $user = $this->getSystemUser();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => []],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
            'webhook' => 'https://webhook.site/test',
        ],
        'inputs' => ['fields' => []], 'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    $generateAction = app(GenerateVouchers::class);
    $voucher = $generateAction->handle($instructions, $user)->first();
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $channels = $notification->via(new \stdClass());
    
    expect($channels)->toContain('mail')
        ->and($channels)->toContain('engage_spark')
        ->and($channels)->toContain('database');
});

test('toMail returns MailMessage with correct type', function () {
    $user = $this->getSystemUser();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => []],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'inputs' => ['fields' => []], 'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    $generateAction = app(GenerateVouchers::class);
    $voucher = $generateAction->handle($instructions, $user)->first();
    
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $mailMessage = $notification->toMail(new \stdClass());
    
    expect($mailMessage)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class)
        ->and($mailMessage->subject)->toBeString()
        ->and($mailMessage->introLines)->toBeArray()
        ->and($mailMessage->introLines)->not->toBeEmpty();
});

test('toEngageSpark returns EngageSparkMessage with content', function () {
    $user = $this->getSystemUser();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => []],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'inputs' => ['fields' => []], 'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    $generateAction = app(GenerateVouchers::class);
    $voucher = $generateAction->handle($instructions, $user)->first();
    
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $smsMessage = $notification->toEngageSpark(new \stdClass());
    
    expect($smsMessage)->toBeInstanceOf(\LBHurtado\EngageSpark\EngageSparkMessage::class)
        ->and($smsMessage->content)->toBeString()
        ->and($smsMessage->content)->not->toBeEmpty();
});

// Webhook test skipped - not implemented yet

test('toArray returns correct data structure', function () {
    $user = $this->getSystemUser();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $this->actingAs($user);
    
    $instructions = VoucherInstructionsData::from([
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => []],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'inputs' => ['fields' => []], 'rider' => [],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '####',
        'ttl' => 'PT24H',
    ]);
    
    $generateAction = app(GenerateVouchers::class);
    $voucher = $generateAction->handle($instructions, $user)->first();
    
    $redeemAction = app(RedeemVoucher::class);
    $redeemAction->handle($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $arrayData = $notification->toArray(new \stdClass());
    
    expect($arrayData)->toHaveKey('code')
        ->and($arrayData)->toHaveKey('mobile')
        ->and($arrayData)->toHaveKey('amount')
        ->and($arrayData)->toHaveKey('currency')
        ->and($arrayData['code'])->toBe($voucher->code)
        ->and($arrayData['amount'])->toBe(100.0)
        ->and($arrayData['currency'])->toBe('PHP');
});
