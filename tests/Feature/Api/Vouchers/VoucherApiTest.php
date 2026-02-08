<?php

declare(strict_types=1);

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake events to avoid queue serialization issues in tests
    Event::fake();

    // Create user with wallet balance
    $this->user = User::factory()->create();
    $this->user->depositFloat(10000); // Add PHP 10,000 balance
});

test('unauthenticated user cannot access voucher api', function () {
    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ]);

    $response->assertStatus(401);
});

test('authenticated user can generate vouchers', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 5,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'count',
                'vouchers',
                'total_amount',
                'currency',
            ],
            'meta' => [
                'timestamp',
                'version',
            ],
        ])
        ->assertJson([
            'data' => [
                'count' => 5,
                'total_amount' => 500,
                'currency' => 'PHP',
            ],
        ]);

    // Verify vouchers were created in database
    expect($this->user->vouchers()->count())->toBe(5);
});

test('generation fails with insufficient balance', function () {
    // Create user with low balance
    $poorUser = User::factory()->create();
    $poorUser->depositFloat(50);

    Sanctum::actingAs($poorUser);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Insufficient wallet balance to generate vouchers.',
        ]);
});

test('generation validates required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount', 'count']);
});

test('generation validates count maximum', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/vouchers', [
        'amount' => 100,
        'count' => 1001, // Over limit
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['count']);
});

test('authenticated user can list vouchers', function () {
    Sanctum::actingAs($this->user);

    // Create some vouchers using Vouchers facade
    for ($i = 0; $i < 10; $i++) {
        Vouchers::withOwner($this->user)->create();
    }

    $response = $this->getJson('/api/v1/vouchers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'code',
                        'status',
                        'amount',
                        'currency',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ],
        ]);

    expect($response->json('data.pagination.total'))->toBe(10);
});

test('list can filter by status', function () {
    Sanctum::actingAs($this->user);

    // Create active voucher
    Vouchers::withOwner($this->user)
        ->withExpireTimeIn(\Carbon\CarbonInterval::days(7))
        ->create();

    // Create redeemed voucher
    $redeemedVoucher = Vouchers::withOwner($this->user)->create();
    $redeemedVoucher->redeemed_at = now();
    $redeemedVoucher->save();

    // Filter for redeemed only
    $response = $this->getJson('/api/v1/vouchers?status=redeemed');

    $response->assertStatus(200);
    expect($response->json('data.pagination.total'))->toBe(1);
});

test('list can search by code', function () {
    Sanctum::actingAs($this->user);

    Vouchers::withOwner($this->user)
        ->withPrefix('TEST')
        ->withMask('****')
        ->create();

    // Create other vouchers
    for ($i = 0; $i < 5; $i++) {
        Vouchers::withOwner($this->user)->create();
    }

    $response = $this->getJson('/api/v1/vouchers?search=TEST');

    $response->assertStatus(200);
    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.data.0.code'))->toContain('TEST');
});

test('authenticated user can show their voucher', function () {
    Sanctum::actingAs($this->user);

    $voucher = Vouchers::withOwner($this->user)->create();

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'voucher' => [
                    'code',
                    'status',
                    'amount',
                    'currency',
                ],
                'redemption_count',
            ],
        ]);
});

test('user cannot show another users voucher', function () {
    $otherUser = User::factory()->create();
    $voucher = Vouchers::withOwner($otherUser)->create();

    Sanctum::actingAs($this->user);

    $response = $this->getJson("/api/v1/vouchers/{$voucher->code}");

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You do not have permission to view this voucher.',
        ]);
});

test('authenticated user can cancel their voucher', function () {
    Sanctum::actingAs($this->user);

    $voucher = Vouchers::withOwner($this->user)->create();

    $response = $this->deleteJson("/api/v1/vouchers/{$voucher->code}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Voucher cancelled successfully.',
                'code' => $voucher->code,
            ],
        ]);

    // Verify voucher was deleted from database
    expect(Voucher::where('code', $voucher->code)->exists())->toBeFalse();
});

test('cannot cancel redeemed voucher', function () {
    Sanctum::actingAs($this->user);

    $voucher = Vouchers::withOwner($this->user)->create();
    $voucher->redeemed_at = now();
    $voucher->save();

    $response = $this->deleteJson("/api/v1/vouchers/{$voucher->code}");

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Cannot cancel a voucher that has already been redeemed.',
        ]);
});

test('user cannot cancel another users voucher', function () {
    $otherUser = User::factory()->create();
    $voucher = Vouchers::withOwner($otherUser)->create();

    Sanctum::actingAs($this->user);

    $response = $this->deleteJson("/api/v1/vouchers/{$voucher->code}");

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'You do not have permission to cancel this voucher.',
        ]);
});

test('api responses include meta information', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/vouchers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'meta' => [
                'timestamp',
                'version',
            ],
        ])
        ->assertJson([
            'meta' => [
                'version' => 'v1',
            ],
        ]);
});
