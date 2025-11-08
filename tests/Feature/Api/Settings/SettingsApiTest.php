<?php

declare(strict_types=1);

use App\Models\User;
use App\Settings\VoucherSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);
});

// Profile API Tests
test('authenticated user can get profile', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/settings/profile');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'profile' => [
                    'name',
                    'email',
                    'avatar',
                    'created_at',
                ],
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'profile' => [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ],
        ]);
});

test('authenticated user can update profile', function () {
    Sanctum::actingAs($this->user);

    $response = $this->patchJson('/api/v1/settings/profile', [
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Profile updated successfully.',
                'profile' => [
                    'name' => 'Updated Name',
                ],
            ],
        ]);

    expect($this->user->fresh()->name)->toBe('Updated Name');
});

test('profile update validates required name', function () {
    Sanctum::actingAs($this->user);

    $response = $this->patchJson('/api/v1/settings/profile', [
        'name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

// Wallet API Tests
test('authenticated user can get wallet config', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/settings/wallet');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'wallet' => [
                    'balance',
                    'currency',
                ],
                'recent_transactions',
            ],
            'meta',
        ]);

    expect((float) $response->json('data.wallet.balance'))->toBe(10000.0);
    expect($response->json('data.wallet.currency'))->toBe('PHP');
});

// Preferences API Tests
test('authenticated user can get preferences', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/settings/preferences');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'preferences' => [
                    'default_amount',
                    'default_expiry_days',
                    'default_rider_url',
                    'default_success_message',
                ],
            ],
            'meta',
        ]);
});

test('authenticated user can update preferences', function () {
    Sanctum::actingAs($this->user);

    $response = $this->patchJson('/api/v1/settings/preferences', [
        'default_amount' => 500,
        'default_expiry_days' => 30,
        'default_rider_url' => 'https://example.com/rider',
        'default_success_message' => 'Custom success message',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'message' => 'Preferences updated successfully.',
                'preferences' => [
                    'default_amount' => 500,
                    'default_expiry_days' => 30,
                ],
            ],
        ]);

    $settings = app(VoucherSettings::class);
    expect($settings->default_amount)->toBe(500);
    expect($settings->default_expiry_days)->toBe(30);
});

test('preferences update validates required fields', function () {
    Sanctum::actingAs($this->user);

    $response = $this->patchJson('/api/v1/settings/preferences', [
        'default_amount' => -1, // Invalid
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['default_amount']);
});

test('unauthenticated user cannot access settings api', function () {
    $response = $this->getJson('/api/v1/settings/profile');
    $response->assertStatus(401);

    $response = $this->getJson('/api/v1/settings/wallet');
    $response->assertStatus(401);

    $response = $this->getJson('/api/v1/settings/preferences');
    $response->assertStatus(401);
});
