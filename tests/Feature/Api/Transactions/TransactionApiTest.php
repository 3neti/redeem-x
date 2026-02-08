<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Models\Voucher;
use Tests\Helpers\VoucherTestHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake events to avoid queue serialization, but allow voucher events
    Event::fake();

    // Set contact config for bank_account
    config(['contact.default.country' => 'PH']);
    config(['contact.default.bank_code' => 'GXCHPHM2XXX']);

    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);

    // Create a contact for redemptions
    $this->contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
});

// List Transactions Tests
test('authenticated user can list transactions', function () {
    Sanctum::actingAs($this->user);

    // Create some redeemed vouchers (transactions)
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 5);
    $vouchers->each(function ($voucher) {
        RedeemVoucher::run($this->contact, $voucher->code);
    });

    $response = $this->getJson('/api/v1/transactions');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
                'filters',
            ],
            'meta',
        ]);

    expect($response->json('data.pagination.total'))->toBe(5);
});

test('transactions list can filter by date range', function () {
    Sanctum::actingAs($this->user);

    // Create vouchers with different redemption dates
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 2);

    $oldVoucher = $vouchers[0];
    RedeemVoucher::run($this->contact, $oldVoucher->code);
    $oldVoucher->redeemed_at = now()->subDays(10);
    $oldVoucher->save();

    $recentVoucher = $vouchers[1];
    RedeemVoucher::run($this->contact, $recentVoucher->code);
    $recentVoucher->redeemed_at = now()->subDays(2);
    $recentVoucher->save();

    // Filter for recent transactions only
    $response = $this->getJson('/api/v1/transactions?date_from='.now()->subDays(5)->format('Y-m-d'));

    $response->assertStatus(200);
    expect($response->json('data.pagination.total'))->toBe(1);
});

test('transactions list can search by code', function () {
    Sanctum::actingAs($this->user);

    // Create voucher with TEST prefix
    $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1, 'TEST')->first();
    RedeemVoucher::run($this->contact, $voucher->code);

    // Create other transactions
    $otherVouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
    $otherVouchers->each(function ($v) {
        RedeemVoucher::run($this->contact, $v->code);
    });

    $response = $this->getJson('/api/v1/transactions?search=TEST');

    $response->assertStatus(200);
    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.data.0.code'))->toContain('TEST');
});

test('transactions list supports pagination', function () {
    Sanctum::actingAs($this->user);

    // Create 25 transactions
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 25);
    $vouchers->each(function ($voucher) {
        RedeemVoucher::run($this->contact, $voucher->code);
    });

    $response = $this->getJson('/api/v1/transactions?per_page=10');

    $response->assertStatus(200);
    expect($response->json('data.pagination.per_page'))->toBe(10);
    expect($response->json('data.pagination.total'))->toBe(25);
    expect($response->json('data.pagination.last_page'))->toBe(3);
});

// Show Transaction Tests
test('authenticated user can show transaction details', function () {
    Sanctum::actingAs($this->user);

    $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)->first();
    $redeemed = RedeemVoucher::run($this->contact, $voucher->code);
    expect($redeemed)->toBeTrue('Voucher redemption should succeed');

    // Verify voucher is actually redeemed in DB
    $freshVoucher = Voucher::where('code', $voucher->code)->first();
    expect($freshVoucher->redeemed_at)->not->toBeNull('Voucher should have redeemed_at timestamp');

    $response = $this->getJson("/api/v1/transactions/{$voucher->code}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'transaction' => [
                    'code',
                    'status',
                    'redeemed_at',
                ],
                'redemption_count',
            ],
            'meta',
        ]);
});

test('cannot show unredeemed voucher as transaction', function () {
    Sanctum::actingAs($this->user);

    $voucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)->first();
    // Not redeemed

    $response = $this->getJson("/api/v1/transactions/{$voucher->code}");

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Voucher has not been redeemed yet.',
        ]);
});

// Transaction Stats Tests
test('authenticated user can get transaction stats', function () {
    Sanctum::actingAs($this->user);

    // Create transactions
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 10);
    $vouchers->each(function ($voucher) {
        RedeemVoucher::run($this->contact, $voucher->code);
    });

    // Create today's transaction
    $todayVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)->first();
    RedeemVoucher::run($this->contact, $todayVoucher->code);

    $response = $this->getJson('/api/v1/transactions/stats');

    $response->assertStatus(200)
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
            'meta',
        ]);

    expect($response->json('data.stats.total'))->toBeGreaterThanOrEqual(10);
    expect($response->json('data.stats.currency'))->toBe('PHP');
});

test('transaction stats can filter by date range', function () {
    Sanctum::actingAs($this->user);

    // Old transaction
    $oldVoucher = VoucherTestHelper::createVouchersWithInstructions($this->user, 1)->first();
    RedeemVoucher::run($this->contact, $oldVoucher->code);
    $oldVoucher->redeemed_at = now()->subDays(30);
    $oldVoucher->save();

    // Recent transactions
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 5);
    $vouchers->each(function ($voucher) {
        RedeemVoucher::run($this->contact, $voucher->code);
        $voucher->redeemed_at = now()->subDays(2);
        $voucher->save();
    });

    $response = $this->getJson('/api/v1/transactions/stats?date_from='.now()->subDays(7)->format('Y-m-d'));

    $response->assertStatus(200);
    expect($response->json('data.stats.total'))->toBe(5);
});

// Export Transactions Tests
test('authenticated user can export transactions as csv', function () {
    Sanctum::actingAs($this->user);

    // Create some transactions
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 3);
    $vouchers->each(function ($voucher) {
        RedeemVoucher::run($this->contact, $voucher->code);
    });

    $response = $this->getJson('/api/v1/transactions/export');

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('transactions-');
});

test('export respects date filters', function () {
    Sanctum::actingAs($this->user);

    // Create old transaction
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($this->user, 2);

    $oldVoucher = $vouchers[0];
    RedeemVoucher::run($this->contact, $oldVoucher->code);
    $oldVoucher->redeemed_at = now()->subDays(30);
    $oldVoucher->save();

    // Create recent transaction
    $recentVoucher = $vouchers[1];
    RedeemVoucher::run($this->contact, $recentVoucher->code);

    $response = $this->getJson('/api/v1/transactions/export?date_from='.now()->subDays(7)->format('Y-m-d'));

    $response->assertStatus(200);
    $content = $response->streamedContent();

    // Should only include recent voucher
    expect($content)->toContain($recentVoucher->code);
    expect($content)->not->toContain($oldVoucher->code);
});

// Authorization Tests
test('unauthenticated user cannot access transactions api', function () {
    $response = $this->getJson('/api/v1/transactions');
    $response->assertStatus(401);

    $response = $this->getJson('/api/v1/transactions/stats');
    $response->assertStatus(401);

    $response = $this->getJson('/api/v1/transactions/export');
    $response->assertStatus(401);
});
