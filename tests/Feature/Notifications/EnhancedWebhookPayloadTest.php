<?php

use App\Notifications\Channels\WebhookChannel;
use App\Notifications\SendFeedbacksNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use Tests\Helpers\VoucherTestHelper;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('webhook payload includes external metadata', function () {
    Http::fake();
    
    $user = User::factory()->create();
    $user->deposit(10000); // Add funds to wallet
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null]],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => 'https://webhook.site/test'],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);
    
    $voucher = $vouchers->first();
    
    // Set external metadata (QuestPay scenario)
    $voucher->external_metadata = [
        'external_id' => 'quest-123',
        'external_type' => 'questpay',
        'reference_id' => 'quest-ref-456',
        'user_id' => 'player-789',
        'custom' => ['level' => 10, 'mission' => 'complete-tutorial'],
    ];
    $voucher->save();
    
    RedeemVoucher::run($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) {
        $body = $request->data();
        return isset($body['external']) &&
               $body['external']['id'] === 'quest-123' &&
               $body['external']['type'] === 'questpay' &&
               $body['external']['reference_id'] === 'quest-ref-456' &&
               $body['external']['user_id'] === 'player-789' &&
               $body['external']['custom']['level'] === 10 &&
               $body['external']['custom']['mission'] === 'complete-tutorial';
    });
});

test('webhook payload includes timing data', function () {
    Http::fake();
    
    $user = User::factory()->create();
    $user->deposit(10000);
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null]],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => 'https://webhook.site/test'],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);
    
    $voucher = $vouchers->first();
    
    // Track timing
    $voucher->trackClick();
    sleep(1);
    $voucher->trackRedemptionStart();
    sleep(1);
    $voucher->trackRedemptionSubmit();
    
    RedeemVoucher::run($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) {
        $body = $request->data();
        // Just check that timing section exists with the expected fields
        return isset($body['timing']) &&
               isset($body['timing']['clicked_at']) &&
               isset($body['timing']['started_at']) &&
               isset($body['timing']['submitted_at']) &&
               isset($body['timing']['duration_seconds']);
    });
});

test('webhook payload includes validation results', function () {
    Http::fake();
    
    $user = User::factory()->create();
    $user->deposit(10000);
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1);
    $voucher = $vouchers->first();
    
    // Store validation results
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 45.5,
        'should_block' => false,
    ]);
    
    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);
    
    $voucher->storeValidationResults($location, $time);
    $voucher->save();
    
    RedeemVoucher::run($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) {
        $body = $request->data();
        return isset($body['validation']) &&
               $body['validation']['passed'] === true &&
               $body['validation']['blocked'] === false &&
               $body['validation']['location']['validated'] === true &&
               $body['validation']['location']['distance_meters'] === 45.5 &&
               $body['validation']['time']['within_window'] === true &&
               $body['validation']['time']['duration_seconds'] === 120;
    });
});

test('webhook includes event header', function () {
    Http::fake();
    
    $user = User::factory()->create();
    $user->deposit(10000);
    $contact = Contact::factory()->create(['mobile' => '09178251991']);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1);
    $voucher = $vouchers->first();
    
    RedeemVoucher::run($contact, $voucher->code);
    
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];
    
    $channel = new WebhookChannel();
    $channel->send($notifiable, $notification);
    
    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Webhook-Event', 'voucher.redeemed');
    });
});
