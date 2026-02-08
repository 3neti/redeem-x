<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->deposit(100000);

    // Create real token for authentication
    $token = $this->user->createToken('test-token');
    $this->withToken($token->plainTextToken);
});

describe('Dashboard Statistics API', function () {
    it('returns dashboard statistics', function () {
        // Create some data
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 5);
        foreach ($vouchers->take(2) as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'wallet_balance',
                        'total_vouchers',
                        'redeemed_vouchers',
                        'pending_vouchers',
                        'total_transactions',
                        'currency',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.stats.currency', 'PHP');
    });

    it('returns zero stats for new user', function () {
        $newUser = User::factory()->create();
        $token = $newUser->createToken('test-token');
        $this->withToken($token->plainTextToken);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.total_vouchers', 0)
            ->assertJsonPath('data.stats.total_transactions', 0);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertUnauthorized();
    });
});

describe('Recent Activity API', function () {
    it('returns recent activity', function () {
        // Create some activity
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
        $vouchers[0]->update(['redeemed_at' => now()]);

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'activity' => [
                        '*' => [
                            'type',
                            'description',
                            'timestamp',
                        ],
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    });

    it('returns empty activity for new user', function () {
        $newUser = User::factory()->create();
        $token = $newUser->createToken('test-token');
        $this->withToken($token->plainTextToken);

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response
            ->assertOk()
            ->assertJsonPath('data.activity', []);
    });

    it('limits activity to recent items', function () {
        // Create many vouchers
        VoucherTestHelper::createVouchersWithInstructions($this->user, 25);

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response
            ->assertOk();

        $activityCount = count($response->json('data.activity'));
        expect($activityCount)->toBeLessThanOrEqual(20); // Should limit to recent 20
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response->assertUnauthorized();
    });
});
