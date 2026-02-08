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

describe('List Transactions API', function () {
    it('returns paginated transactions', function () {
        // Create redeemed vouchers
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 5);
        foreach ($vouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/transactions');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'code',
                            'status',
                            'amount',
                            'redeemed_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                    'filters',
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.pagination.total', 5);
    });

    it('filters transactions by date range', function () {
        // Create vouchers on different dates
        $oldVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $oldVoucher->update(['redeemed_at' => now()->subDays(10)]);

        $newVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $newVoucher->update(['redeemed_at' => now()]);

        $response = $this->getJson('/api/v1/transactions?date_from='.now()->toDateString());

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    });

    it('filters transactions by search term', function () {
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
        foreach ($vouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $searchCode = substr($vouchers[0]->code, 0, 5);

        $response = $this->getJson("/api/v1/transactions?search={$searchCode}");

        $response->assertOk();
        expect($response->json('data.pagination.total'))->toBeGreaterThanOrEqual(1);
    });

    it('validates pagination parameters', function () {
        $response = $this->getJson('/api/v1/transactions?per_page=200'); // Exceeds max 100

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('validates date range', function () {
        $response = $this->getJson('/api/v1/transactions?date_from=2024-12-31&date_to=2024-01-01');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/transactions');

        $response->assertUnauthorized();
    });

    it('returns empty results when no transactions exist', function () {
        $response = $this->getJson('/api/v1/transactions');

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    });
});

describe('Transaction Statistics API', function () {
    it('returns transaction statistics', function () {
        // Create redeemed vouchers
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
        foreach ($vouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/transactions/stats');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total',
                        'total_amount',
                        'today',
                        'this_month',
                        'currency',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonPath('data.stats.currency', 'PHP');
    });

    it('filters stats by date range', function () {
        // Create old and new vouchers
        $oldVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $oldVoucher->update(['redeemed_at' => now()->subDays(10)]);

        $newVouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 2);
        foreach ($newVouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/transactions/stats?date_from='.now()->toDateString());

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.total', 2);
    });

    it('returns zero stats when no transactions exist', function () {
        $response = $this->getJson('/api/v1/transactions/stats');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.total', 0)
            ->assertJsonPath('data.stats.total_amount', 0)
            ->assertJsonPath('data.stats.today', 0);
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/transactions/stats');

        $response->assertUnauthorized();
    });
});

describe('Show Transaction API', function () {
    it('returns transaction details', function () {
        $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $voucher->update(['redeemed_at' => now()]);

        $response = $this->getJson("/api/v1/transactions/{$voucher->code}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'transaction' => [
                        'code',
                        'status',
                        'amount',
                        'redeemed_at',
                    ],
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJsonPath('data.transaction.code', $voucher->code);
    });

    it('returns 404 for non-existent transaction', function () {
        $response = $this->getJson('/api/v1/transactions/INVALID-CODE');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->withoutToken();
        $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];

        $response = $this->getJson("/api/v1/transactions/{$voucher->code}");

        $response->assertUnauthorized();
    });
});

describe('Export Transactions API', function () {
    it('exports transactions as CSV', function () {
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
        foreach ($vouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/transactions/export?format=csv');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('exports transactions with default CSV format', function () {
        $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 2);
        foreach ($vouchers as $voucher) {
            $voucher->update(['redeemed_at' => now()]);
        }

        $response = $this->getJson('/api/v1/transactions/export');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('exports filtered transactions as CSV', function () {
        $oldVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $oldVoucher->update(['redeemed_at' => now()->subDays(10)]);

        $newVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $newVoucher->update(['redeemed_at' => now()]);

        $response = $this->getJson('/api/v1/transactions/export?date_from='.now()->toDateString());

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->getJson('/api/v1/transactions/export');

        $response->assertUnauthorized();
    });
});

describe('Refresh Disbursement Status API', function () {
    it('returns 400 when transaction has no disbursement data', function () {
        $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)[0];
        $voucher->update(['redeemed_at' => now()]);

        $response = $this->postJson("/api/v1/transactions/{$voucher->code}/refresh-status");

        $response
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This transaction has no disbursement data',
            ]);
    });

    it('returns 404 for non-existent voucher', function () {
        $response = $this->postJson('/api/v1/transactions/INVALID-CODE/refresh-status');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $this->withoutToken();

        $response = $this->postJson('/api/v1/transactions/SOME-CODE/refresh-status');

        $response->assertUnauthorized();
    });
});
