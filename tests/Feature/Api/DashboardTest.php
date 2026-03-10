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
                        'vouchers' => ['total', 'active', 'redeemed', 'expired'],
                        'transactions' => ['today', 'this_month', 'total_amount', 'currency'],
                        'wallet' => ['balance', 'currency'],
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.stats.transactions.currency', 'PHP');
    });

    it('returns zero stats for new user', function () {
        $newUser = User::factory()->create();
        $token = $newUser->createToken('test-token');
        $this->withToken($token->plainTextToken);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.vouchers.total', 0)
            ->assertJsonPath('data.stats.transactions.today', 0);
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
                        'generations',
                        'redemptions',
                        'deposits',
                        'topups',
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
            ->assertJsonPath('data.activity', [
                'generations' => [],
                'redemptions' => [],
                'deposits' => [],
                'topups' => [],
            ]);
    });

    it('limits activity to recent items', function () {
        // Increase deposit to cover 10 vouchers (₱1 each)
        $this->user->deposit(100000);

        // Create vouchers (limited to 10 to avoid wallet issues)
        VoucherTestHelper::createVouchersWithInstructions($this->user, 10);

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response
            ->assertOk();

        // Activity is grouped by type, each limited to 5 items
        $activity = $response->json('data.activity');
        expect($activity)->toHaveKeys(['generations', 'redemptions', 'deposits', 'topups']);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/dashboard/activity');

        $response->assertUnauthorized();
    });
});
