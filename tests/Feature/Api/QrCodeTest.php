<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Voucher\Models\Voucher;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    
    // Set mobile via HasChannels trait
    $this->user->setChannel('mobile', '09171234567');
});

// ============================================================================
// GET /api/v1/vouchers/{code}/qr - Generate Voucher QR Code
// ============================================================================

test('authenticated user can generate qr code for their voucher', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    // Create voucher BEFORE Sanctum::actingAs to avoid serialization issues
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'TEST');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set (VoucherPolicy checks this)
    $voucher->owner_id = $this->user->id;
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'qr_code',
                'redemption_url',
                'voucher_code',
                'amount',
                'currency',
                'expires_at',
                'is_redeemed',
                'is_expired',
            ],
            'meta' => [
                'timestamp',
                'version',
            ],
        ]);

    expect($response->json('data.voucher_code'))->toBe($voucher->code);
    expect($response->json('data.redemption_url'))->toContain("/redeem?code={$voucher->code}");
    expect($response->json('data.qr_code'))->toStartWith('data:');
});

test('qr code includes voucher details', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    // Create voucher first
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'DETAIL');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set and set expiration
    $voucher->owner_id = $this->user->id;
    $voucher->expires_at = now()->addDays(30);
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson("/api/v1/vouchers/VOUCHER-WITH-DETAILS/qr");

    $response->assertOk();
    
    expect($response->json('data.expires_at'))->not()->toBeNull();
    expect($response->json('data.is_redeemed'))->toBe(false);
    expect($response->json('data.is_expired'))->toBe(false);
});

test('cannot generate qr code for non-existent voucher', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/vouchers/NON-EXISTENT/qr');

    $response->assertNotFound();
});

test('cannot generate qr code for another users voucher', function () {
    $otherUser = User::factory()->create();
    $otherUser->deposit(10000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($otherUser, 1, 'OTHER');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set to other user
    $voucher->owner_id = $otherUser->id;
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");

    $response->assertForbidden();
});

test('unauthenticated user cannot generate qr code', function () {
    $this->user->deposit(10000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'UNAUTH');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set
    $voucher->owner_id = $this->user->id;
    $voucher->save();

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");

    // Unauthenticated requests return 401
    $response->assertUnauthorized();
});

test('qr code can be generated for redeemed voucher', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'REDEEM');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set and mark as redeemed
    $voucher->owner_id = $this->user->id;
    $voucher->redeemed_at = now();
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");

    $response->assertOk();
    expect($response->json('data.is_redeemed'))->toBe(true);
});

test('qr code can be generated for expired voucher', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'EXPIRE');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set and set as expired
    $voucher->owner_id = $this->user->id;
    $voucher->expires_at = now()->subDay();
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");

    $response->assertOk();
    expect($response->json('data.is_expired'))->toBe(true);
});

// ============================================================================
// GET /api/v1/wallet/generate-qr - Generate Wallet QR Code (existing endpoint)
// ============================================================================

test('authenticated user can generate wallet qr code', function () {
    Sanctum::actingAs($this->user, ['*']);
    
    // Ensure user has mobile number
    $this->user->setChannel('mobile', '09171234567');

    $response = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 100.00,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'qr_code',
                'account',
                'amount',
                'shareable_url',
                'merchant',
            ],
            'message',
        ]);

    expect($response->json('success'))->toBe(true);
    expect($response->json('data.amount'))->toBe(100);
});

test('wallet qr code requires mobile number', function () {
    Sanctum::actingAs($this->user, ['*']);
    
    // Remove mobile number
    $this->user->channels()->delete();

    $response = $this->postJson('/api/v1/wallet/generate-qr');

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Mobile number is required');
});

test('wallet qr code can be generated with dynamic amount', function () {
    Sanctum::actingAs($this->user, ['*']);
    $this->user->setChannel('mobile', '09171234567');

    $response = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 0, // Dynamic amount
    ]);

    $response->assertOk();
    $amount = $response->json('data.amount');
    expect($amount === null || $amount === 0)->toBeTrue();
});

test('wallet qr code validates amount format', function () {
    Sanctum::actingAs($this->user, ['*']);
    $this->user->setChannel('mobile', '09171234567');

    $response = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 'invalid',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('wallet qr code can be force regenerated', function () {
    Sanctum::actingAs($this->user, ['*']);
    $this->user->setChannel('mobile', '09171234567');

    // First generation
    $response1 = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 500,
    ]);

    $response1->assertOk();
    expect($response1->json('cached'))->toBe(false);

    // Second call should be cached
    $response2 = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 500,
    ]);

    $response2->assertOk();
    expect($response2->json('cached'))->toBe(true);

    // Force regenerate
    $response3 = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 500,
        'force' => true,
    ]);

    $response3->assertOk();
    expect($response3->json('cached'))->toBe(false);
});

test('unauthenticated user cannot generate wallet qr code', function () {
    $response = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 100,
    ]);

    $response->assertUnauthorized();
});

// ============================================================================
// Rate Limiting Tests
// ============================================================================

test('qr code endpoints respect rate limiting', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'RATE');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set
    $voucher->owner_id = $this->user->id;
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    // Attempt 61 requests (limit is 60 per minute)
    for ($i = 0; $i < 61; $i++) {
        $response = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");
        
        if ($i < 60) {
            $response->assertOk();
        } else {
            $response->assertStatus(429); // Too Many Requests
        }
    }
});

// ============================================================================
// Integration Tests
// ============================================================================

test('qr codes work in complete user flow', function () {
    // Fund wallet first
    $this->user->deposit(10000);
    
    // 1. Generate a voucher
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'FLOW');
    $voucher = $vouchers->first();
    
    // Ensure owner_id is set
    $voucher->owner_id = $this->user->id;
    $voucher->save();
    
    Sanctum::actingAs($this->user, ['*']);

    // 2. Generate QR code for the voucher
    $qrResponse = $this->getJson("/api/v1/vouchers/{$voucher->code}/qr");
    
    $qrResponse->assertOk();
    $redemptionUrl = $qrResponse->json('data.redemption_url');

    // 3. Verify redemption URL is valid
    expect($redemptionUrl)->toContain("redeem?code={$voucher->code}");

    // 4. Generate wallet QR code
    $this->user->setChannel('mobile', '09171234567');
    $walletQrResponse = $this->postJson('/api/v1/wallet/generate-qr', [
        'amount' => 1000,
    ]);

    $walletQrResponse->assertOk();
    expect($walletQrResponse->json('data.shareable_url'))->not()->toBeNull();
});
