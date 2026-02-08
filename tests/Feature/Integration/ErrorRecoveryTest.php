<?php

declare(strict_types=1);

use App\Models\User;
use Bavix\Wallet\Exceptions\InsufficientFunds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Http::fake();
});

test('insufficient wallet balance prevents voucher generation', function () {
    // Test that voucher generation fails when user has insufficient funds

    $user = User::factory()->create();
    $user->deposit(100); // Only ₱100

    // Attempt to generate ₱500 voucher (requires more for escrow)
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['cash']['amount'] = 500;
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    // Authenticate as user
    auth()->setUser($user);

    // Should throw InsufficientFunds exception
    expect(fn () => GenerateVouchers::run($instructions))
        ->toThrow(InsufficientFunds::class);
});

test('contact with null bank account handles gracefully', function () {
    // Test that contacts with null bank_account can still redeem
    // (uses default bank_account from Contact::booted())

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    // Create contact without bank_account initially
    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
        // bank_account will be set by booted() hook with default
    ]);

    // After creation, bank_account should be set by Contact::booted()
    $contact->refresh();
    expect($contact->bank_account)->not->toBeNull();

    // Redemption should succeed
    $redeemed = RedeemVoucher::run($contact, $voucher->code);
    expect($redeemed)->toBeTrue();

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
});

test('expired voucher during redemption fails', function () {
    // Test that attempting to redeem an expired voucher fails

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => 'PT2S', // 2 seconds
    ]);

    $voucher = $vouchers->first();

    // Wait for expiration
    sleep(3);

    $phoneNumber = new PhoneNumber('09171234567', 'PH');
    $contact = Contact::factory()->create([
        'mobile' => $phoneNumber->formatE164(),
        'country' => 'PH',
    ]);

    // Redemption should fail (returns false)
    $redeemed = RedeemVoucher::run($contact, $voucher->code);
    expect($redeemed)->toBeFalse();

    // Voucher should NOT be marked as redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->toBeNull();
});

test('network timeout does not block redemption', function () {
    // Test that webhook timeouts don't prevent voucher redemption

    // Mock webhook to timeout (no response)
    Http::fake([
        'webhook.site/*' => function () {
            // Simulate timeout by never responding
            sleep(1);

            return Http::response(['error' => 'timeout'], 408);
        },
    ]);

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
            'webhook' => 'https://webhook.site/timeout-test',
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

    // Redemption should still succeed despite webhook timeout
    $redeemed = RedeemVoucher::run($contact, $voucher->code);
    expect($redeemed)->toBeTrue();

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    // Note: Webhook failure should be logged but not block redemption
    // SendFeedbacks pipeline handles errors gracefully
});

test('double redemption prevention', function () {
    // Test that attempting to redeem an already-redeemed voucher fails

    $user = User::factory()->create();
    $user->deposit(100000);

    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['secret' => null, 'mobile' => null, 'country' => 'PH', 'location' => null, 'radius' => null],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => '',
        'mask' => '****',
        'ttl' => null,
    ]);

    $voucher = $vouchers->first();

    // First redemption
    $contact1 = Contact::factory()->create([
        'mobile' => '+639171234567',
        'country' => 'PH',
    ]);

    $firstRedemption = RedeemVoucher::run($contact1, $voucher->code);
    expect($firstRedemption)->toBeTrue();

    // Second redemption attempt (different contact)
    $contact2 = Contact::factory()->create([
        'mobile' => '+639171234568',
        'country' => 'PH',
    ]);

    $secondRedemption = RedeemVoucher::run($contact2, $voucher->code);
    expect($secondRedemption)->toBeFalse(); // Should fail

    // Verify only first contact is redeemer
    $voucher->refresh();
    expect($voucher->redeemers)->toHaveCount(1);
    expect($voucher->redeemers->first()->redeemer_id)->toBe($contact1->id);
});

test('invalid voucher code returns false', function () {
    // Test that redeeming with invalid code fails gracefully

    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'country' => 'PH',
    ]);

    // Attempt to redeem non-existent voucher
    $redeemed = RedeemVoucher::run($contact, 'INVALID-CODE-1234');

    // Should return false (not throw exception)
    expect($redeemed)->toBeFalse();
});

test('voucher with future start date cannot be redeemed', function () {
    // Test that vouchers with future start dates are not redeemable yet

    $user = User::factory()->create();
    $user->deposit(100000);

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['count'] = 1;
    $instructions = VoucherInstructionsData::from($base);

    // Create voucher that starts in the future
    auth()->setUser($user);

    $voucher = \FrittenKeeZ\Vouchers\Facades\Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
    ])->withOwner($user)
        ->withStartTime(now()->addDays(7)) // Starts 7 days from now
        ->create();

    expect($voucher->starts_at)->not->toBeNull();
    expect($voucher->starts_at->isFuture())->toBeTrue();

    // Attempt to redeem before start date
    $contact = Contact::factory()->create([
        'mobile' => '+639171234567',
        'country' => 'PH',
    ]);

    $redeemed = RedeemVoucher::run($contact, $voucher->code);
    expect($redeemed)->toBeFalse();

    $voucher->refresh();
    expect($voucher->redeemed_at)->toBeNull();
});
