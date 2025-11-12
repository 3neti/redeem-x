<?php

use App\Models\User;
use App\Notifications\SendFeedbacksNotification;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('notification can be created with voucher code', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
    ])->first();
    
    $notification = new SendFeedbacksNotification($voucher->code);
    
    expect($notification)->toBeInstanceOf(SendFeedbacksNotification::class);
});

test('notification returns correct channels', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
    ])->first();
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $channels = $notification->via(new \stdClass());
    
    expect($channels)->toContain('mail')
        ->and($channels)->toContain('engage_spark')
        ->and($channels)->toContain(\App\Notifications\Channels\WebhookChannel::class)
        ->and($channels)->toContain('database');
});

test('toMail returns MailMessage', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $mailMessage = $notification->toMail(new \stdClass());
    
    expect($mailMessage)->toBeInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class)
        ->and($mailMessage->subject)->toBe('Voucher Code Redeemed');
});

test('toEngageSpark returns EngageSparkMessage', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $smsMessage = $notification->toEngageSpark(new \stdClass());
    
    expect($smsMessage)->toBeInstanceOf(\LBHurtado\EngageSpark\EngageSparkMessage::class);
});

test('toWebhook returns correct structure', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $webhookData = $notification->toWebhook(['webhook' => 'https://webhook.site/test']);
    
    expect($webhookData)->toHaveKey('url')
        ->and($webhookData)->toHaveKey('payload')
        ->and($webhookData)->toHaveKey('headers')
        ->and($webhookData['payload'])->toHaveKey('event')
        ->and($webhookData['payload']['event'])->toBe('voucher.redeemed')
        ->and($webhookData['payload'])->toHaveKey('voucher')
        ->and($webhookData['payload'])->toHaveKey('redeemer');
});

test('toArray returns correct data structure', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'email' => 'lbhurtado@gmail.com',
            'mobile' => '09178251991',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
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
