<?php

declare(strict_types=1);

use App\Models\User;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    
    config(['contact.default.country' => 'PH']);
    config(['contact.default.bank_code' => 'GXCHPHM2XXX']);

    $this->user = User::factory()->create();
    $this->user->depositFloat(10000);
});

test('authenticated user can list vouchers via API with session', function () {
    // Create some vouchers
    for ($i = 0; $i < 5; $i++) {
        Vouchers::withOwner($this->user)->create();
    }

    // Authenticate via session (like browser)
    $this->actingAs($this->user);

    // Make API request
    $response = $this->getJson('/api/v1/vouchers');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'code',
                        'status',
                        'amount',
                        'currency',
                        'is_expired',
                        'is_redeemed',
                        'can_redeem',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ],
            'meta' => [
                'timestamp',
                'version',
            ],
        ]);

    expect($response->json('data.pagination.total'))->toBe(5);
});

test('api returns proper pagination', function () {
    // Create 20 vouchers
    for ($i = 0; $i < 20; $i++) {
        Vouchers::withOwner($this->user)->create();
    }

    $this->actingAs($this->user);

    // Request first page with 10 per page
    $response = $this->getJson('/api/v1/vouchers?per_page=10&page=1');

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data['data'])->toHaveCount(10);
    expect($data['pagination']['current_page'])->toBe(1);
    expect($data['pagination']['total'])->toBe(20);
    expect($data['pagination']['last_page'])->toBe(2);

    // Request second page
    $response2 = $this->getJson('/api/v1/vouchers?per_page=10&page=2');
    $response2->assertOk();
    
    $data2 = $response2->json('data');
    expect($data2['data'])->toHaveCount(10);
    expect($data2['pagination']['current_page'])->toBe(2);
});

test('api filters vouchers by status', function () {
    // Create vouchers with different statuses
    $activeVoucher = Vouchers::withOwner($this->user)->create();
    
    $expiredVoucher = Vouchers::withOwner($this->user)
        ->withExpireTimeIn(\Carbon\CarbonInterval::seconds(1))
        ->create();
    sleep(2); // Let it expire

    $redeemedVoucher = Vouchers::withOwner($this->user)->create();
    $contact = \LBHurtado\Contact\Models\Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
        'bank_account' => 'GXCHPHM2XXX:09171234567',
    ]);
    \LBHurtado\Voucher\Actions\RedeemVoucher::run($contact, $redeemedVoucher->code);

    $this->actingAs($this->user);

    // Filter for active
    $response = $this->getJson('/api/v1/vouchers?status=active');
    $response->assertOk();
    $vouchers = collect($response->json('data.data'));
    expect($vouchers->every(fn($v) => !$v['is_expired'] && !$v['is_redeemed']))->toBeTrue();

    // Filter for redeemed
    $response = $this->getJson('/api/v1/vouchers?status=redeemed');
    $response->assertOk();
    $vouchers = collect($response->json('data.data'));
    expect($vouchers->every(fn($v) => $v['is_redeemed']))->toBeTrue();

    // Filter for expired
    $response = $this->getJson('/api/v1/vouchers?status=expired');
    $response->assertOk();
    $vouchers = collect($response->json('data.data'));
    expect($vouchers->every(fn($v) => $v['is_expired'] && !$v['is_redeemed']))->toBeTrue();
});

test('api searches vouchers by code', function () {
    Vouchers::withOwner($this->user)
        ->withPrefix('TEST')
        ->withMask('****')
        ->create();

    // Create other vouchers
    for ($i = 0; $i < 5; $i++) {
        Vouchers::withOwner($this->user)->create();
    }

    $this->actingAs($this->user);

    $response = $this->getJson('/api/v1/vouchers?search=TEST');

    $response->assertOk();
    expect($response->json('data.pagination.total'))->toBe(1);
    expect($response->json('data.data.0.code'))->toContain('TEST');
});

test('unauthenticated request returns 401', function () {
    $response = $this->getJson('/api/v1/vouchers');
    $response->assertUnauthorized();
});

test('api returns consistent response format', function () {
    Vouchers::withOwner($this->user)->create();

    $this->actingAs($this->user);

    $response = $this->getJson('/api/v1/vouchers');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'timestamp',
                'version',
            ],
        ]);

    expect($response->json('meta.version'))->toBe('v1');
});
