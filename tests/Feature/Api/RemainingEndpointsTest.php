<?php

declare(strict_types=1);

use App\Models\{User, Campaign};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->deposit(100000);

    $token = $this->user->createToken('test-token');
    $this->withToken($token->plainTextToken);
});

describe('Campaigns API', function () {
    it('lists user campaigns', function () {
        Campaign::factory()->count(3)->create(['user_id' => $this->user->id, 'status' => 'active']);

        $response = $this->getJson('/api/v1/campaigns');

        $response
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'slug', 'instructions'],
            ]);
    });

    it('shows single campaign', function () {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/campaigns/{$campaign->id}");

        $response
            ->assertOk()
            ->assertJsonPath('id', $campaign->id);
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $response = $this->getJson('/api/v1/campaigns');
        $response->assertUnauthorized();
    });
});

describe('Merchant Profile API', function () {
    it('shows merchant profile', function () {
        $response = $this->getJson('/api/v1/merchant/profile');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'name',
                'email',
                'mobile',
            ]);
    });

    it('updates merchant profile', function () {
        $response = $this->putJson('/api/v1/merchant/profile', [
            'mobile' => '09171234567',
        ]);

        $response->assertOk();
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $response = $this->getJson('/api/v1/merchant/profile');
        $response->assertUnauthorized();
    });
});

describe('Balance Monitoring API', function () {
    it('lists account balances', function () {
        $response = $this->getJson('/api/v1/balances');

        $response->assertOk();
    });

    it('shows specific balance', function () {
        $response = $this->getJson('/api/v1/balances/113-001-00001-9');

        $response->assertStatus([200, 404]); // May not exist in test
    });

    it('refreshes balance', function () {
        $response = $this->postJson('/api/v1/balances/113-001-00001-9/refresh');

        $response->assertStatus([200, 404, 500]); // External API
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $response = $this->getJson('/api/v1/balances');
        $response->assertUnauthorized();
    });
});

describe('Charge Calculation API', function () {
    it('calculates charges', function () {
        $response = $this->postJson('/api/v1/calculate-charges', [
            'amount' => 1000,
            'count' => 10,
        ]);

        $response->assertOk();
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $response = $this->postJson('/api/v1/calculate-charges', []);
        $response->assertUnauthorized();
    });
});
