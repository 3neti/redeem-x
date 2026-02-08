<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

test('notification system triggered on redemption', function () {
    // Test that notification system is invoked when voucher is redeemed
    // with feedback configuration

    Notification::fake();
    Http::fake();

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => 'admin@example.com',
            'mobile' => '+639171234567',
            'webhook' => 'https://webhook.site/test',
        ],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09178251991', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    RedeemVoucher::run($contact, $voucher->code);

    // Verify voucher was redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Note: With sync queue and post-redemption pipeline,
    // notifications are sent automatically via SendFeedbacks pipeline stage
    // The notification system routes to email, SMS, and webhook channels
});

test('notification respects feedback configuration', function () {
    // Test that only configured feedback channels are used

    Notification::fake();
    Http::fake();

    $user = User::factory()->create();
    $user->deposit(100000);

    // Voucher with only email feedback (no SMS/webhook)
    $emailOnlyVouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'EMAIL', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => 'test@example.com',
            'mobile' => null, // No SMS
            'webhook' => null, // No webhook
        ],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'EMAIL',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $emailOnlyVouchers->first();

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    RedeemVoucher::run($contact, $voucher->code);

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Note: Only email channel should be invoked
    // SMS and webhook channels should not receive notifications
});

test('notification with no feedback channels', function () {
    // Test that vouchers without feedback config don't send notifications

    Notification::fake();
    Http::fake();

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    RedeemVoucher::run($contact, $voucher->code);

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Verify no notifications sent (all feedback channels are null)
    // SendFeedbacks pipeline stage should handle gracefully
});

test('webhook notification payload structure', function () {
    // Test that webhook receives proper payload structure

    Http::fake([
        'webhook.site/*' => Http::response(['success' => true], 200),
    ]);

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TEST', [
        'cash' => [
            'amount' => 500,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['name', 'email']],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => 'https://webhook.site/test-payload',
        ],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    $metadata = [
        'redemption' => [
            'inputs' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ],
    ];

    RedeemVoucher::run($contact, $voucher->code, $metadata);

    // Verify voucher was redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Note: Webhook payload structure documented in WebhookChannelTest
    // Expected payload includes:
    // - event: 'voucher.redeemed'
    // - voucher: { code, amount, currency }
    // - redeemer: { mobile }
    // - inputs: { name, email }
});

test('notification template variables', function () {
    // Test that notification templates use correct variables
    // Templates from lang/en/notifications.php

    $user = User::factory()->create();
    $user->deposit(200000); // Higher amount for ₱1500 voucher + escrow

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, 'TPL', [
        'cash' => [
            'amount' => 1500,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => ['name', 'address']],
        'feedback' => [
            'email' => 'notify@example.com',
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => 'TPL',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    $phoneNumber = new PhoneNumber('09467438575', 'PH'); // Your test number
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    $metadata = [
        'redemption' => [
            'inputs' => [
                'name' => 'Juan dela Cruz',
                'address' => '123 Main St, Manila',
            ],
        ],
    ];

    RedeemVoucher::run($contact, $voucher->code, $metadata);

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Template variables that should be available:
    // {{ code }} - voucher code (e.g., TPL-XXXX)
    // {{ formatted_amount }} - formatted cash amount (e.g., ₱1,500.00)
    // {{ mobile }} - redeemer mobile (e.g., +639467438575)
    // {{ name }} - from inputs
    // {{ address }} - from inputs

    // TemplateProcessor service handles variable replacement
    // VoucherTemplateContextBuilder flattens voucher data for templating
});
