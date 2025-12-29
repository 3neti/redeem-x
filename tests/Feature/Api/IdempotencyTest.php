<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Voucher\Models\Voucher;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    
    // Give user sufficient balance for testing
    $this->user->deposit(1000000); // ₱10,000 in centavos
});

test('it requires idempotency key for post requests', function () {
    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Idempotency-Key header is required for this request.',
        ]);
});

test('it rejects invalid idempotency key format', function () {
    // Too short (< 16 characters)
    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => 'short',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Invalid Idempotency-Key format.',
        ]);
});

test('it allows get requests without idempotency key', function () {
    $response = $this->getJson('/api/v1/vouchers');

    $response->assertStatus(200); // Should not require idempotency key
});

test('it generates vouchers with idempotency key', function () {
    $idempotencyKey = (string) Str::uuid();

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 2,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'count',
            'vouchers',
            'total_amount',
            'currency',
        ])
        ->assertJson([
            'count' => 2,
        ])
        ->assertHeader('X-Idempotent-Replay', 'false');

    // Verify idempotency key was stored in database
    $voucherCodes = $response->json('vouchers.*.code');
    expect($voucherCodes)->toHaveCount(2);

    foreach ($voucherCodes as $code) {
        $voucher = Voucher::where('code', $code)->first();
        expect($voucher)->not->toBeNull();
        expect($voucher->idempotency_key)->toBe($idempotencyKey);
        expect($voucher->idempotency_created_at)->not->toBeNull();
    }
});

test('it returns cached response for duplicate voucher generation', function () {
    $idempotencyKey = (string) Str::uuid();

    // First request
    $response1 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 2,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response1->assertStatus(201);
    $voucherCodes1 = $response1->json('vouchers.*.code');

    // Second request with same idempotency key
    $response2 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 2,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response2->assertStatus(201);
    $voucherCodes2 = $response2->json('vouchers.*.code');

    // Should return exact same vouchers
    expect($voucherCodes1)->toBe($voucherCodes2);
    
    // Should only have created 2 vouchers total, not 4
    expect(Voucher::count())->toBe(2);
});

test('it generates new vouchers with different idempotency key', function () {
    $idempotencyKey1 = (string) Str::uuid();
    $idempotencyKey2 = (string) Str::uuid();

    // First request
    $response1 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey1,
    ]);

    $response1->assertStatus(201);
    $voucher1Code = $response1->json('vouchers.0.code');

    // Second request with different idempotency key
    $response2 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey2,
    ]);

    $response2->assertStatus(201);
    $voucher2Code = $response2->json('vouchers.0.code');

    // Should have created different vouchers
    expect($voucher1Code)->not->toBe($voucher2Code);
    expect(Voucher::count())->toBe(2);
});

test('it caches idempotent responses for 24 hours', function () {
    $idempotencyKey = (string) Str::uuid();

    // First request
    $response1 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response1->assertStatus(201);

    // Verify cache exists
    $cacheKey = "idempotency:{$this->user->id}:{$idempotencyKey}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second request should hit cache
    $response2 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response2->assertStatus(201);
});

test('it scopes idempotency by user', function () {
    $idempotencyKey = (string) Str::uuid();
    
    // User 1 creates voucher
    $response1 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response1->assertStatus(201);
    $user1VoucherCode = $response1->json('vouchers.0.code');

    // User 2 uses same idempotency key
    $user2 = User::factory()->create();
    $user2->deposit(1000000);
    Sanctum::actingAs($user2);

    $response2 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response2->assertStatus(201);
    $user2VoucherCode = $response2->json('vouchers.0.code');

    // Different users should create different vouchers even with same key
    expect($user1VoucherCode)->not->toBe($user2VoucherCode);
    expect(Voucher::count())->toBe(2);
});

test('it only caches successful responses', function () {
    $idempotencyKey = (string) Str::uuid();

    // First request - insufficient balance (should fail)
    $poorUser = User::factory()->create();
    Sanctum::actingAs($poorUser);

    $response1 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response1->assertStatus(403); // Insufficient balance

    // Verify failed response was NOT cached
    $cacheKey = "idempotency:{$poorUser->id}:{$idempotencyKey}";
    expect(Cache::has($cacheKey))->toBeFalse();

    // Give user balance and retry with same key
    $poorUser->deposit(1000000);

    $response2 = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response2->assertStatus(201); // Should succeed now
    
    // Now it should be cached
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('it handles validation errors with idempotency', function () {
    $idempotencyKey = (string) Str::uuid();

    // Request with validation error
    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => -100, // Invalid amount
        'count' => 1,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(422); // Validation error

    // Validation errors should not be cached
    $cacheKey = "idempotency:{$this->user->id}:{$idempotencyKey}";
    expect(Cache::has($cacheKey))->toBeFalse();
});
