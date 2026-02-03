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
    
    // Test with AnonymousNotifiable (used in actual redemption flow)
    $anonymousChannels = $notification->via(\Illuminate\Support\Facades\Notification::route('mail', 'test@example.com'));
    expect($anonymousChannels)->toContain('mail')
        ->and($anonymousChannels)->toContain('engage_spark');
    
    // Test with User model (database logging)
    $userChannels = $notification->via($user);
    expect($userChannels)->toContain('database');
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
    
    // BaseNotification uses standardized structure
    expect($arrayData)->toHaveKeys(['type', 'timestamp', 'data', 'audit'])
        ->and($arrayData['type'])->toBe('voucher_redeemed')
        ->and($arrayData['data'])->toHaveKey('code')
        ->and($arrayData['data'])->toHaveKey('mobile')
        ->and($arrayData['data'])->toHaveKey('amount')
        ->and($arrayData['data'])->toHaveKey('currency')
        ->and($arrayData['data']['code'])->toBe($voucher->code)
        ->and($arrayData['data']['amount'])->toBe(100.0)
        ->and($arrayData['data']['currency'])->toBe('PHP')
        ->and($arrayData['audit'])->toHaveKey('voucher_code')
        ->and($arrayData['audit']['voucher_code'])->toBe($voucher->code);
});
