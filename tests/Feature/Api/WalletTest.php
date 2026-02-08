<?php

declare(strict_types=1);

use App\Models\TopUp;
use App\Models\User;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

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
// GET /api/v1/wallet/balance - Get Wallet Balance
// ============================================================================

test('authenticated user can get wallet balance', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Deposit some money (Bavix Wallet stores in cents)
    $this->user->deposit(10000); // ₱100.00

    $response = $this->getJson('/api/v1/wallet/balance');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'balance',
                'currency',
                'balance_cents',
            ],
            'meta' => [
                'timestamp',
                'version',
            ],
        ]);

    expect($response->json('data.balance'))->toBe('100.00');
    expect($response->json('data.currency'))->toBe('PHP');
    expect($response->json('data.balance_cents'))->toBe(10000);
});

test('wallet balance returns zero for new user', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/wallet/balance');

    $response->assertOk();
    expect($response->json('data.balance'))->toBe('0.00');
    expect($response->json('data.balance_cents'))->toBe(0);
});

test('unauthenticated user cannot access wallet balance', function () {
    $response = $this->getJson('/api/v1/wallet/balance');

    $response->assertUnauthorized();
});

// ============================================================================
// POST /api/v1/wallet/topup - Initiate Top-Up
// ============================================================================

test('authenticated user can initiate top-up', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Mock the payment gateway
    config(['payment-gateway.top_up.use_fake' => true]);

    $response = $this->postJson('/api/v1/wallet/topup', [
        'amount' => 500.00,
        'gateway' => 'netbank',
        'institution_code' => 'GCASH',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'reference_no',
                'redirect_url',
                'gateway',
                'amount',
                'currency',
                'institution_code',
            ],
            'meta',
        ]);

    expect($response->json('data.amount'))->toBe(500);
    expect($response->json('data.gateway'))->toBe('netbank');
    expect($response->json('data.institution_code'))->toBe('GCASH');

    // Verify top-up record created
    $this->assertDatabaseHas('top_ups', [
        'user_id' => $this->user->id,
        'amount' => '500.00',
        'gateway' => 'netbank',
        'payment_status' => 'PENDING',
        'institution_code' => 'GCASH',
    ]);
});

test('top-up requires amount', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/wallet/topup', [
        'gateway' => 'netbank',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('top-up validates minimum amount', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/wallet/topup', [
        'amount' => 0.50,
        'gateway' => 'netbank',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('top-up validates maximum amount', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/wallet/topup', [
        'amount' => 60000, // Above 50k limit
        'gateway' => 'netbank',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('top-up defaults to netbank gateway', function () {
    Sanctum::actingAs($this->user, ['*']);
    config(['payment-gateway.top_up.use_fake' => true]);

    $response = $this->postJson('/api/v1/wallet/topup', [
        'amount' => 100.00,
    ]);

    $response->assertOk();
    expect($response->json('data.gateway'))->toBe('netbank');
});

test('unauthenticated user cannot initiate top-up', function () {
    $response = $this->postJson('/api/v1/wallet/topup', [
        'amount' => 100.00,
    ]);

    $response->assertUnauthorized();
});

// ============================================================================
// GET /api/v1/wallet/topup - List Top-Ups
// ============================================================================

test('authenticated user can list all top-ups', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Create some top-ups
    TopUp::factory()->create([
        'user_id' => $this->user->id,
        'amount' => 100.00,
        'payment_status' => 'PAID',
    ]);

    TopUp::factory()->create([
        'user_id' => $this->user->id,
        'amount' => 200.00,
        'payment_status' => 'PENDING',
    ]);

    TopUp::factory()->create([
        'user_id' => $this->user->id,
        'amount' => 300.00,
        'payment_status' => 'FAILED',
    ]);

    $response = $this->getJson('/api/v1/wallet/topup');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'reference_no',
                    'gateway',
                    'amount',
                    'currency',
                    'payment_status',
                    'payment_id',
                    'institution_code',
                    'redirect_url',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'timestamp',
                'version',
                'count',
            ],
        ]);

    expect($response->json('meta.count'))->toBe(3);
});

test('user can filter top-ups by status', function () {
    Sanctum::actingAs($this->user, ['*']);

    TopUp::factory()->create(['user_id' => $this->user->id, 'payment_status' => 'PAID']);
    TopUp::factory()->create(['user_id' => $this->user->id, 'payment_status' => 'PENDING']);
    TopUp::factory()->create(['user_id' => $this->user->id, 'payment_status' => 'PENDING']);

    $response = $this->getJson('/api/v1/wallet/topup?status=pending');

    $response->assertOk();
    expect($response->json('meta.count'))->toBe(2);

    $statuses = collect($response->json('data'))->pluck('payment_status')->unique();
    expect($statuses->toArray())->toBe(['PENDING']);
});

test('invalid status filter returns error', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/wallet/topup?status=invalid');

    $response->assertStatus(422);
});

test('top-up list only shows user own records', function () {
    Sanctum::actingAs($this->user, ['*']);

    $otherUser = User::factory()->create();

    TopUp::factory()->create(['user_id' => $this->user->id]);
    TopUp::factory()->create(['user_id' => $this->user->id]);
    TopUp::factory()->create(['user_id' => $otherUser->id]); // Should not appear

    $response = $this->getJson('/api/v1/wallet/topup');

    $response->assertOk();
    expect($response->json('meta.count'))->toBe(2);
});

test('top-ups are listed in descending order by creation date', function () {
    Sanctum::actingAs($this->user, ['*']);

    $oldest = TopUp::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(2),
    ]);

    $newest = TopUp::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/wallet/topup');

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($newest->id);
    expect($response->json('data.1.id'))->toBe($oldest->id);
});

// ============================================================================
// GET /api/v1/wallet/topup/{referenceNo} - Get Top-Up Status
// ============================================================================

test('authenticated user can get top-up status by reference', function () {
    Sanctum::actingAs($this->user, ['*']);

    $topUp = TopUp::factory()->create([
        'user_id' => $this->user->id,
        'reference_no' => 'TOPUP-ABC123',
        'amount' => 500.00,
        'payment_status' => 'PAID',
    ]);

    $response = $this->getJson('/api/v1/wallet/topup/TOPUP-ABC123');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'reference_no',
                'amount',
                'payment_status',
            ],
            'meta',
        ]);

    expect($response->json('data.id'))->toBe($topUp->id);
    expect($response->json('data.reference_no'))->toBe('TOPUP-ABC123');
    expect($response->json('data.payment_status'))->toBe('PAID');
});

test('cannot get top-up status for non-existent reference', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/wallet/topup/INVALID-REF');

    $response->assertNotFound();
});

test('cannot get top-up status for another user top-up', function () {
    Sanctum::actingAs($this->user, ['*']);

    $otherUser = User::factory()->create();
    TopUp::factory()->create([
        'user_id' => $otherUser->id,
        'reference_no' => 'TOPUP-OTHER',
    ]);

    $response = $this->getJson('/api/v1/wallet/topup/TOPUP-OTHER');

    $response->assertNotFound();
});

// ============================================================================
// GET /api/v1/wallet/transactions - List Wallet Transactions
// ============================================================================

test('authenticated user can list wallet transactions', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Create some transactions
    $this->user->deposit(10000); // ₱100.00
    $this->user->withdraw(2000);  // ₱20.00
    $this->user->deposit(5000);   // ₱50.00

    $response = $this->getJson('/api/v1/wallet/transactions');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'amount',
                    'confirmed',
                    'meta',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'timestamp',
                'version',
                'count',
            ],
        ]);

    expect($response->json('meta.count'))->toBe(3);
});

test('user can filter transactions by type', function () {
    Sanctum::actingAs($this->user, ['*']);

    $this->user->deposit(10000);
    $this->user->deposit(5000);
    $this->user->withdraw(2000);

    $response = $this->getJson('/api/v1/wallet/transactions?type=deposit');

    $response->assertOk();
    expect($response->json('meta.count'))->toBe(2);

    $types = collect($response->json('data'))->pluck('type')->unique();
    expect($types->toArray())->toBe(['deposit']);
});

test('invalid transaction type filter returns error', function () {
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->getJson('/api/v1/wallet/transactions?type=invalid');

    $response->assertStatus(422);
});

test('transactions are listed in descending order', function () {
    Sanctum::actingAs($this->user, ['*']);

    $this->user->deposit(1000);
    sleep(1);
    $this->user->deposit(2000);

    $response = $this->getJson('/api/v1/wallet/transactions');

    $response->assertOk();
    // Most recent transaction first
    expect($response->json('data.0.amount'))->toBe('20.00');
    expect($response->json('data.1.amount'))->toBe('10.00');
});

test('transactions only show user own records', function () {
    Sanctum::actingAs($this->user, ['*']);

    $otherUser = User::factory()->create();

    $this->user->deposit(1000);
    $this->user->deposit(2000);
    $otherUser->deposit(5000); // Should not appear

    $response = $this->getJson('/api/v1/wallet/transactions');

    $response->assertOk();
    expect($response->json('meta.count'))->toBe(2);
});

test('unauthenticated user cannot access transactions', function () {
    $response = $this->getJson('/api/v1/wallet/transactions');

    $response->assertUnauthorized();
});

// ============================================================================
// Rate Limiting Tests
// ============================================================================

test('wallet endpoints respect rate limiting', function () {
    Sanctum::actingAs($this->user, ['*']);

    // Attempt 61 requests (limit is 60 per minute)
    for ($i = 0; $i < 61; $i++) {
        $response = $this->getJson('/api/v1/wallet/balance');

        if ($i < 60) {
            $response->assertOk();
        } else {
            $response->assertStatus(429); // Too Many Requests
        }
    }
});
