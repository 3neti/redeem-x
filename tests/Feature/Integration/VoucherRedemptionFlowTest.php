<?php

declare(strict_types=1);

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Notification::fake();
    Http::fake();
});

/**
 * Helper function to create a test voucher
 */
function createTestVoucher(User $user, array $fields = []): Voucher
{
    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['inputs'] = ['fields' => $fields];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)->create();

    // Verify the facade returns the correct model type
    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher)->toBeInstanceOf(LBHurtado\Voucher\Models\Voucher::class);

    // Mark as processed since queue is faked and HandleGeneratedVouchers won't run
    // In production, this is done by the post-generation pipeline
    $voucher->processed = true;
    $voucher->save();

    return $voucher;
}

test('complete redemption flow with all plugins', function () {
    // Given: A voucher requiring all input fields + signature (OTP excluded - requires cache setup)
    $user = User::factory()->create();
    $voucher = createTestVoucher($user, [
        'email', 'name', 'address', 'birth_date', 'gross_monthly_income',
        'location', 'reference_code', 'signature',
    ]);

    // Step 1: Start redemption (direct access to wallet page)
    get("/redeem/{$voucher->code}/wallet")
        ->assertInertia(fn ($page) => $page->component('redeem/Wallet'));

    // Step 2: Submit wallet info (API endpoint)
    postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '+639171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertOk();

    // Step 3: Submit inputs plugin (API endpoint)
    postJson('/api/v1/redeem/plugin', [
        'code' => $voucher->code,
        'plugin' => 'inputs',
        'data' => [
            'email' => 'test@example.com',
            'name' => 'John Doe',
            'address' => '123 Main St',
            'birth_date' => '1990-01-01',
            'gross_monthly_income' => '50000',
            'location' => 'Manila',
            'reference_code' => 'REF123',
        ],
    ])->assertOk();

    // Step 4: Submit signature plugin (API endpoint)
    postJson('/api/v1/redeem/plugin', [
        'code' => $voucher->code,
        'plugin' => 'signature',
        'data' => [
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANS',
        ],
    ])->assertOk();

    // Step 5: Finalize and confirm (API endpoint)
    postJson('/api/v1/redeem/confirm', [
        'code' => $voucher->code,
    ])->assertOk();

    // Verify: Voucher is redeemed
    $voucher->refresh();
    $voucher->load('redeemers');
    expect($voucher->redeemed_at)->not->toBeNull();
    expect($voucher->redeemers)->toHaveCount(1);
    $redeemer = $voucher->redeemers->first();
    expect($redeemer->metadata['redemption']['inputs'] ?? [])->toHaveKeys([
        'email',
        'name',
        'address',
        'birth_date',
        'gross_monthly_income',
        'location',
        'reference_code',
        'signature',
    ]);
});

test('complete redemption flow with minimal fields', function () {
    // Given: A voucher requiring only wallet info (no plugins)
    $user = User::factory()->create();
    $voucher = createTestVoucher($user);

    // Step 1: Start redemption (direct access to wallet page)
    get("/redeem/{$voucher->code}/wallet")
        ->assertInertia(fn ($page) => $page->component('redeem/Wallet'));

    // Step 2: Submit wallet info (API endpoint)
    postJson('/api/v1/redeem/wallet', [
        'code' => $voucher->code,
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertOk();

    // Step 3: Finalize and confirm (API endpoint)
    postJson('/api/v1/redeem/confirm', [
        'code' => $voucher->code,
    ])->assertOk();

    // Verify: Voucher is redeemed
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
});

test('redemption flow with only email field', function () {
    // Given: A voucher requiring only EMAIL
    $user = User::factory()->create();
    $voucher = createTestVoucher($user, ['email']);

    // Wallet step
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertRedirect("/redeem/{$voucher->code}/inputs");

    // Inputs plugin (only EMAIL should be validated)
    post("/redeem/{$voucher->code}/inputs", [
        'email' => 'test@example.com',
    ])->assertRedirect("/redeem/{$voucher->code}/finalize");

    // Confirm
    post("/redeem/{$voucher->code}/confirm")
        ->assertRedirect("/redeem/{$voucher->code}/success");

    $voucher = $voucher->fresh(['redeemers']);
    expect($voucher->redeemers)->toHaveCount(1);
    $redeemer = $voucher->redeemers->first();
    $redeemedInputs = $redeemer->metadata['redemption']['inputs'] ?? [];
    expect($redeemedInputs)->toHaveKey('email');
    expect($redeemedInputs['email'])->toBe('test@example.com');
});

test('redemption flow validates required fields only', function () {
    // Given: Voucher requires NAME and EMAIL
    $user = User::factory()->create();
    $voucher = createTestVoucher($user, ['name', 'email']);

    // Wallet step
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertRedirect();

    // Inputs plugin with missing NAME (should fail)
    post("/redeem/{$voucher->code}/inputs", [
        'email' => 'test@example.com',
    ])->assertSessionHasErrors(['name']);

    // With all required fields (should pass)
    post("/redeem/{$voucher->code}/inputs", [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'address' => '123 Main St', // Extra field (should be ignored gracefully)
    ])->assertRedirect("/redeem/{$voucher->code}/finalize");
});

test('redemption flow prevents double redemption', function () {
    // Given: A voucher already redeemed
    $user = User::factory()->create();
    $voucher = createTestVoucher($user);

    // Mark it as redeemed
    $voucher->redeemed_at = now();
    $voucher->save();

    // Attempt to start redemption
    get("/redeem?code={$voucher->code}")
        ->assertRedirect('/redeem')
        ->assertSessionHas('error');
});

test('redemption flow validates expired vouchers', function () {
    // Given: An expired voucher
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withExpireTimeIn(\Carbon\CarbonInterval::seconds(1))
        ->withOwner($user)->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);

    // Wait for expiration
    sleep(2);

    // Attempt wallet submission
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHasErrors(['code']);
});

test('redemption flow validates wrong secret', function () {
    // Given: A valid voucher
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'correct_secret',
    ])->withOwner($user)->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);

    // Attempt with wrong secret
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'wrong_secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHasErrors(['secret']);
});

test('redemption flow validates mobile number format', function () {
    $user = User::factory()->create();
    $voucher = createTestVoucher($user);

    // Invalid Philippine number
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '12345',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHasErrors(['mobile']);

    // Valid formats
    $validNumbers = ['+639171234567', '639171234567', '09171234567'];

    foreach ($validNumbers as $number) {
        post("/redeem/{$voucher->code}/wallet", [
            'mobile' => $number,
            'country' => 'PH',
            'secret' => 'test-secret',
            'bank_code' => 'GCASH',
            'account_number' => '09171234567',
        ])->assertSessionDoesntHaveErrors(['mobile']);
    }
});

test('redemption flow session data persists across steps', function () {
    // Given: Voucher with multiple plugins
    $user = User::factory()->create();
    $voucher = createTestVoucher($user, ['email', 'signature']);

    // Step 1: Submit wallet
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '+639171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHas("redeem.{$voucher->code}.mobile", '+639171234567')
        ->assertSessionHas("redeem.{$voucher->code}.wallet", 'GCASH')
        ->assertSessionHas("redeem.{$voucher->code}.account_number", '09171234567');

    // Step 2: Submit inputs
    post("/redeem/{$voucher->code}/inputs", [
        'email' => 'test@example.com',
    ])->assertRedirect();

    // Verify inputs were stored
    expect(session("redeem.{$voucher->code}.inputs"))->toBeArray()
        ->and(session("redeem.{$voucher->code}.inputs.email"))->toBe('test@example.com');

    // Step 3: Submit signature
    post("/redeem/{$voucher->code}/signature", [
        'signature' => 'data:image/png;base64,test',
    ])->assertRedirect();

    // Verify signature was stored (plugin stores whole validated array)
    expect(session("redeem.{$voucher->code}.signature"))->toBeArray()
        ->toHaveKey('signature');

    // All session data should still be present at finalize
    get("/redeem/{$voucher->code}/finalize")
        ->assertSessionHas("redeem.{$voucher->code}.mobile")
        ->assertSessionHas("redeem.{$voucher->code}.inputs")
        ->assertSessionHas("redeem.{$voucher->code}.signature");
});

test('redemption flow clears session after successful completion', function () {
    // Given: Minimal voucher
    $user = User::factory()->create();
    $voucher = createTestVoucher($user);

    // Complete flow
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHas("redeem.{$voucher->code}.mobile");

    post("/redeem/{$voucher->code}/confirm");

    // Session should be marked as redeemed
    get("/redeem/{$voucher->code}/success")
        ->assertSessionHas("redeem.{$voucher->code}.redeemed", true);
});

test('cannot redeem voucher that starts in the future', function () {
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)
        ->withStartTime(now()->addDays(7))
        ->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->starts_at->isFuture())->toBeTrue();

    // Attempt to start redemption - should fail
    get("/redeem?code={$voucher->code}")
        ->assertRedirect('/redeem')
        ->assertSessionHas('error');
});

test('cannot redeem expired voucher', function () {
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)
        ->withExpireTime(now()->subDay()) // Expired yesterday
        ->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->expires_at->isPast())->toBeTrue();

    // Attempt to start redemption - should fail
    get("/redeem?code={$voucher->code}")
        ->assertRedirect('/redeem')
        ->assertSessionHas('error');
});

test('expired voucher returns error when submitting wallet info', function () {
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)
        ->withExpireTime(now()->subDay())
        ->create();

    // Attempt wallet submission on expired voucher
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ])->assertSessionHasErrors(['code']);
});

test('success page displays custom rider message', function () {
    $user = User::factory()->create();

    $customMessage = 'Thank you for your redemption! Your payment will be processed within 24 hours.';

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['rider'] = [
        'message' => $customMessage,
    ];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);

    // Complete redemption
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    post("/redeem/{$voucher->code}/confirm");

    // Check success page has custom message
    get("/redeem/{$voucher->code}/success")
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Success')
            ->has('voucher')
            ->where('voucher.instructions.rider.message', $customMessage)
        );
});

test('success page redirects to custom rider URL when configured', function () {
    $user = User::factory()->create();

    $customUrl = 'https://example.com/custom-thank-you';

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $base['rider'] = [
        'message' => 'Thank you!',
        'url' => $customUrl,
    ];
    $instructions = VoucherInstructionsData::from($base);

    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->instructions->rider->url)->toBe($customUrl);

    // Complete redemption
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    post("/redeem/{$voucher->code}/confirm");

    // Success page should pass redirect URL to frontend
    get("/redeem/{$voucher->code}/success")
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Success')
            ->where('voucher.instructions.rider.url', $customUrl)
        );
});

test('success page uses default configuration when no rider specified', function () {
    $user = User::factory()->create();

    // No rider configuration
    $voucher = createTestVoucher($user);

    // Complete redemption
    post("/redeem/{$voucher->code}/wallet", [
        'mobile' => '09171234567',
        'country' => 'PH',
        'secret' => 'test-secret',
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    post("/redeem/{$voucher->code}/confirm");

    // Success page should render with default config
    get("/redeem/{$voucher->code}/success")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('redeem/Success')
            ->has('voucher')
        );
});

test('voucher with extended expiry remains valid', function () {
    $user = User::factory()->create();

    $base = VoucherInstructionsData::generateFromScratch()->toArray();
    $instructions = VoucherInstructionsData::from($base);

    // Create voucher with 90 days expiry
    $voucher = Vouchers::withMetadata([
        'instructions' => $instructions->toCleanArray(),
        'secret' => 'test-secret',
    ])->withOwner($user)
        ->withExpireTimeIn(\Carbon\CarbonInterval::days(90))
        ->create();

    expect($voucher)->toBeInstanceOf(Voucher::class);
    expect($voucher->expires_at)->not->toBeNull();
    expect($voucher->expires_at->isFuture())->toBeTrue();
    expect(round(abs($voucher->expires_at->diffInDays(now()))))->toBe(90.0);

    // Should be able to start redemption
    get("/redeem?code={$voucher->code}")
        ->assertRedirect("/redeem/{$voucher->code}/wallet");
});
