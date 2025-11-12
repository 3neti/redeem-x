<?php

use App\Notifications\Channels\WebhookChannel;
use App\Notifications\SendFeedbacksNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use LBHurtado\Contact\Models\Contact;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('webhook channel sends POST request', function () {
    Http::fake([
        'webhook.site/*' => Http::response(['success' => true], 200),
    ]);
    
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) {
        return $request->url() === 'https://webhook.site/test' &&
               $request->method() === 'POST' &&
               $request->hasHeader('Content-Type', 'application/json') &&
               $request->hasHeader('User-Agent', 'Redeem-X/1.0');
    });
});

test('webhook channel handles missing URL gracefully', function () {
    Http::fake();
    
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = []; // No webhook URL
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertNothingSent();
});

test('webhook channel handles failed requests', function () {
    Http::fake([
        'webhook.site/*' => Http::response(['error' => 'Server error'], 500),
    ]);
    
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    
    // Should not throw exception
    expect(fn() => $channel->send($notifiable, $notification))->not->toThrow(Exception::class);
    
    Http::assertSent(function ($request) {
        return $request->url() === 'https://webhook.site/test';
    });
});

test('webhook payload contains correct voucher data', function () {
    Http::fake([
        'webhook.site/*' => Http::response(['success' => true], 200),
    ]);
    
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $voucher = $user->generateVouchers([
        'cash' => ['amount' => 100, 'currency' => 'PHP'],
        'feedback' => [
            'webhook' => 'https://webhook.site/test',
        ],
        'count' => 1,
        'prefix' => 'TEST',
    ])->first();
    
    $voucher->redeem($contact);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) use ($voucher) {
        $body = $request->data();
        return $body['event'] === 'voucher.redeemed' &&
               $body['voucher']['code'] === $voucher->code &&
               $body['voucher']['amount'] === 100.0 &&
               $body['voucher']['currency'] === 'PHP' &&
               $body['redeemer']['mobile'] === '09178251991';
    });
});
