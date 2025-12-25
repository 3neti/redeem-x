<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
    $this->user->deposit(10000);

    // Create real token for authentication
    $token = $this->user->createToken('test-token');
    $this->withToken($token->plainTextToken);
});

describe('Profile Settings API', function () {
    it('returns user profile', function () {
        $response = $this->getJson('/api/v1/settings/profile');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profile' => [
                        'name',
                        'email',
                        'avatar',
                        'created_at',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.profile.name', 'John Doe')
            ->assertJsonPath('data.profile.email', 'john@example.com');
    });

    it('requires authentication for profile', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/settings/profile');

        $response->assertUnauthorized();
    });

    it('updates user profile', function () {
        $response = $this->patchJson('/api/v1/settings/profile', [
            'name' => 'Jane Doe',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Profile updated successfully.')
            ->assertJsonPath('data.profile.name', 'Jane Doe');

        expect($this->user->fresh()->name)->toBe('Jane Doe');
    });

    it('validates profile update data', function () {
        $response = $this->patchJson('/api/v1/settings/profile', [
            'name' => '', // Empty name
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates name max length', function () {
        $response = $this->patchJson('/api/v1/settings/profile', [
            'name' => str_repeat('a', 256), // Exceeds max 255
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('Wallet Configuration API', function () {
    it('returns wallet configuration', function () {
        $response = $this->getJson('/api/v1/settings/wallet');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet' => [
                        'default_settlement_rail',
                        'default_fee_strategy',
                        'auto_disburse',
                        'low_balance_threshold',
                        'low_balance_notifications',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('requires authentication for wallet config', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/settings/wallet');

        $response->assertUnauthorized();
    });

    it('updates wallet configuration', function () {
        $response = $this->patchJson('/api/v1/settings/wallet', [
            'default_settlement_rail' => 'instapay',
            'default_fee_strategy' => 'include',
            'auto_disburse' => true,
            'low_balance_threshold' => 5000,
            'low_balance_notifications' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Wallet configuration updated successfully.')
            ->assertJsonPath('data.wallet.default_settlement_rail', 'instapay')
            ->assertJsonPath('data.wallet.default_fee_strategy', 'include')
            ->assertJsonPath('data.wallet.low_balance_threshold', 5000);
    });

    it('validates settlement rail values', function () {
        $response = $this->patchJson('/api/v1/settings/wallet', [
            'default_settlement_rail' => 'invalid-rail',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_settlement_rail']);
    });

    it('validates fee strategy values', function () {
        $response = $this->patchJson('/api/v1/settings/wallet', [
            'default_fee_strategy' => 'invalid-strategy',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['default_fee_strategy']);
    });

    it('validates balance threshold is numeric', function () {
        $response = $this->patchJson('/api/v1/settings/wallet', [
            'low_balance_threshold' => 'not-a-number',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['low_balance_threshold']);
    });

    it('validates balance threshold is positive', function () {
        $response = $this->patchJson('/api/v1/settings/wallet', [
            'low_balance_threshold' => -1000,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['low_balance_threshold']);
    });
});

describe('User Preferences API', function () {
    it('returns user preferences', function () {
        $response = $this->getJson('/api/v1/settings/preferences');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'preferences' => [
                        'notifications',
                        'timezone',
                        'language',
                        'currency',
                        'date_format',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('requires authentication for preferences', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/settings/preferences');

        $response->assertUnauthorized();
    });

    it('updates user preferences', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'notifications' => [
                'email' => true,
                'sms' => false,
                'push' => true,
            ],
            'timezone' => 'Asia/Manila',
            'language' => 'en',
            'currency' => 'PHP',
            'date_format' => 'Y-m-d',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.message', 'Preferences updated successfully.')
            ->assertJsonPath('data.preferences.timezone', 'Asia/Manila')
            ->assertJsonPath('data.preferences.currency', 'PHP');
    });

    it('validates timezone format', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'timezone' => 'Invalid/Timezone',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);
    });

    it('validates language format', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'language' => 'xxx', // Invalid language code
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    });

    it('validates currency format', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'currency' => 'INVALID',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    });

    it('validates date format structure', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'date_format' => 'not-a-valid-format',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_format']);
    });

    it('allows partial preference updates', function () {
        $response = $this->patchJson('/api/v1/settings/preferences', [
            'timezone' => 'UTC',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.preferences.timezone', 'UTC');
    });
});

describe('Rate Limiting', function () {
    it('enforces rate limiting on settings endpoints', function () {
        // Make 61 requests to trigger rate limit (60 req/min)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/v1/settings/profile');

            if ($i < 60) {
                $response->assertOk();
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    })->skip('Rate limiting test - runs slowly');
});
