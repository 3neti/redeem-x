<?php

use App\Models\InstructionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Seed instruction items
    InstructionItem::create([
        'name' => 'Cash Amount',
        'index' => 'cash.amount',
        'type' => 'cash',
        'price' => 2000,
    ]);

    InstructionItem::create([
        'name' => 'Email',
        'index' => 'feedback.email',
        'type' => 'feedback',
        'price' => 100,
    ]);

    InstructionItem::create([
        'name' => 'Mobile',
        'index' => 'feedback.mobile',
        'type' => 'feedback',
        'price' => 180,
    ]);
});

test('api calculates charges for valid instructions', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/calculate-charges', [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => 'test@example.com',
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'breakdown' => [
                '*' => [
                    'index',
                    'label',
                    'value',
                    'price',
                    'currency',
                ],
            ],
            'total',
        ]);

    $data = $response->json();
    expect($data['total'])->toBe(2100) // 2000 (cash.amount) + 100 (email)
        ->and(count($data['breakdown']))->toBe(2);
});

test('api requires authentication', function () {
    $response = $this->postJson('/api/v1/calculate-charges', [
        'cash' => ['amount' => 100.0],
    ]);

    $response->assertUnauthorized();
});

test('api validates required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/calculate-charges', []);

    $response->assertStatus(422)
        ->assertJson([
            'error' => 'Failed to calculate charges',
        ]);
});

test('api calculates charges with multiple feedback channels', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/calculate-charges', [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => 'test@example.com',
            'mobile' => '09171234567',
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
    ]);

    $response->assertOk();

    $data = $response->json();
    expect($data['total'])->toBe(2280) // 2000 + 100 + 180
        ->and(count($data['breakdown']))->toBe(3);
});
