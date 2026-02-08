<?php

use App\Models\User;
use App\Notifications\Channels\WebhookChannel;
use App\Notifications\SendFeedbacksNotification;
use Illuminate\Support\Facades\Http;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use Tests\Helpers\VoucherTestHelper;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('webhook payload includes all enhanced features together', function () {
    Http::fake();

    $user = User::factory()->create();
    $user->deposit(10000);
    $contact = Contact::factory()->create(['mobile' => '09178251991']);

    // Create voucher with inputs
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => ['amount' => 100, 'currency' => 'PHP', 'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null]],
        'inputs' => [
            'fields' => ['name', 'email', 'location'], // Just enum string values
        ],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => 'https://webhook.site/test'],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    // 1. Set external metadata (QuestPay scenario)
    $voucher->external_metadata = [
        'external_id' => 'quest-123',
        'external_type' => 'questpay',
        'reference_id' => 'quest-ref-456',
        'user_id' => 'player-789',
        'custom' => ['level' => 10, 'mission' => 'complete-tutorial'],
    ];
    $voucher->save();

    // 2. Track timing events
    $voucher->trackClick();
    sleep(1);
    $voucher->trackRedemptionStart();
    sleep(1);
    $voucher->trackRedemptionSubmit();

    // 3. Store validation results
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

    // 4. Add input data
    $voucher->forceSetInput('name', 'John Doe');
    $voucher->forceSetInput('email', 'john@example.com');
    $voucher->forceSetInput('location', json_encode([
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'accuracy' => 10,
        'altitude' => 15.5,
        'address' => [
            'formatted' => 'Manila, Philippines',
        ],
        'snapshot' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
    ]));

    // Redeem voucher
    RedeemVoucher::run($contact, $voucher->code);

    // Send notification
    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];

    $channel = new WebhookChannel;
    $channel->send($notifiable, $notification);

    // Verify complete payload structure
    Http::assertSent(function ($request) {
        $body = $request->data();

        // Check external metadata
        $hasExternal = isset($body['external']) &&
                      $body['external']['id'] === 'quest-123' &&
                      $body['external']['type'] === 'questpay' &&
                      $body['external']['reference_id'] === 'quest-ref-456' &&
                      $body['external']['user_id'] === 'player-789' &&
                      $body['external']['custom']['level'] === 10;

        // Check timing data
        $hasTiming = isset($body['timing']) &&
                    isset($body['timing']['clicked_at']) &&
                    isset($body['timing']['started_at']) &&
                    isset($body['timing']['submitted_at']) &&
                    isset($body['timing']['duration_seconds']);

        // Check validation results
        $hasValidation = isset($body['validation']) &&
                        $body['validation']['passed'] === true &&
                        $body['validation']['blocked'] === false &&
                        $body['validation']['location']['validated'] === true &&
                        $body['validation']['location']['distance_meters'] === 45.5 &&
                        $body['validation']['time']['within_window'] === true &&
                        $body['validation']['time']['duration_seconds'] === 120;

        // Check collected inputs
        $hasInputs = isset($body['inputs']) &&
                    $body['inputs']['name'] === 'John Doe' &&
                    $body['inputs']['email'] === 'john@example.com' &&
                    isset($body['inputs']['location']) &&
                    $body['inputs']['location']['latitude'] === 14.5995 &&
                    $body['inputs']['location']['longitude'] === 120.9842 &&
                    $body['inputs']['location']['formatted_address'] === 'Manila, Philippines' &&
                    $body['inputs']['location']['has_snapshot'] === true;

        // Check core voucher data
        $hasCore = $body['event'] === 'voucher.redeemed' &&
                  isset($body['voucher']['code']) &&
                  $body['voucher']['amount'] === 100.0 &&
                  $body['voucher']['currency'] === 'PHP' &&
                  $body['redeemer']['mobile'] === '09178251991';

        // Check headers
        $hasHeaders = $request->hasHeader('X-Webhook-Event', 'voucher.redeemed') &&
                     $request->hasHeader('Content-Type', 'application/json') &&
                     $request->hasHeader('User-Agent', 'Redeem-X/1.0');

        return $hasExternal && $hasTiming && $hasValidation && $hasInputs && $hasCore && $hasHeaders;
    });
});

test('webhook payload handles optional fields gracefully', function () {
    Http::fake();

    $user = User::factory()->create();
    $user->deposit(10000);
    $contact = Contact::factory()->create(['mobile' => '09178251991']);

    // Create voucher without any optional features
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

    // Redeem without setting any optional data
    RedeemVoucher::run($contact, $voucher->code);

    $notification = new SendFeedbacksNotification($voucher->code);
    $notifiable = ['webhook' => 'https://webhook.site/test'];

    $channel = new WebhookChannel;
    $channel->send($notifiable, $notification);

    // Verify payload works without optional fields
    Http::assertSent(function ($request) {
        $body = $request->data();

        // Core data should still be present
        $hasCore = $body['event'] === 'voucher.redeemed' &&
                  isset($body['voucher']['code']) &&
                  $body['voucher']['amount'] === 100.0;

        // Optional fields should not be present or be empty
        $noExternal = ! isset($body['external']);
        $noTiming = ! isset($body['timing']);
        $noValidation = ! isset($body['validation']);
        $emptyInputs = empty($body['inputs']) || $body['inputs'] === [];

        return $hasCore && $noExternal && $noTiming && ($noValidation || $emptyInputs);
    });
});
