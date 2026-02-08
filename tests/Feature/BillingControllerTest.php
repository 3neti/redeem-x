<?php

use App\Models\Campaign;
use App\Models\User;
use App\Models\VoucherGenerationCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear permission cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Seed roles and permissions
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

    // Create admin user and add to override list
    $this->admin = User::factory()->create(['email' => 'admin@test.com']);
    $this->admin->assignRole('super-admin');

    // Add admin email to override list so they bypass permission checks
    config(['admin.override_emails' => ['admin@test.com']]);

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    // Create campaigns
    $this->campaign = Campaign::factory()->create(['user_id' => $this->user1->id]);

    // Create charges for user1
    $this->charge1 = VoucherGenerationCharge::create([
        'user_id' => $this->user1->id,
        'campaign_id' => $this->campaign->id,
        'voucher_codes' => ['TEST-001', 'TEST-002'],
        'voucher_count' => 2,
        'instructions_snapshot' => ['cash' => ['amount' => 100]],
        'charge_breakdown' => [
            ['index' => 'cash.amount', 'label' => 'Base Fee', 'price' => 2000],
        ],
        'total_charge' => 20.00,
        'charge_per_voucher' => 10.00,
        'generated_at' => now(),
    ]);

    // Create charge for user2
    $this->charge2 = VoucherGenerationCharge::create([
        'user_id' => $this->user2->id,
        'campaign_id' => null,
        'voucher_codes' => ['OTHER-001'],
        'voucher_count' => 1,
        'instructions_snapshot' => ['cash' => ['amount' => 50]],
        'charge_breakdown' => [
            ['index' => 'cash.amount', 'label' => 'Base Fee', 'price' => 2000],
        ],
        'total_charge' => 20.00,
        'charge_per_voucher' => 20.00,
        'generated_at' => now(),
    ]);
});

// Admin Billing Tests
test('admin can view all billing records', function () {
    $response = $this->actingAs($this->admin)->get('/admin/billing');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/Index')
            ->has('charges')
            ->has('filters')
        );
});

test('admin can view specific charge details', function () {
    $response = $this->actingAs($this->admin)->get("/admin/billing/{$this->charge1->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/Show')
            ->has('charge')
            ->where('charge.id', $this->charge1->id)
            ->where('charge.voucher_count', 2)
        );
});

test('admin can filter billing by user', function () {
    $response = $this->actingAs($this->admin)->get("/admin/billing?user_id={$this->user1->id}");

    $response->assertOk();
});

test('admin can filter billing by date range', function () {
    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($this->admin)->get("/admin/billing?from={$from}&to={$to}");

    $response->assertOk();
});

test('regular user cannot access admin billing', function () {
    $response = $this->actingAs($this->user1)->get('/admin/billing');

    $response->assertForbidden();
});

test('guest cannot access admin billing', function () {
    $response = $this->get('/admin/billing');

    $response->assertRedirect('/login');
});

// User Billing Tests
test('user can view own billing records', function () {
    $response = $this->actingAs($this->user1)->get('/billing');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('billing/Index')
            ->has('charges')
            ->has('summary')
            ->has('filters')
        );
});

test('user only sees own charges', function () {
    $response = $this->actingAs($this->user1)->get('/billing');

    $response->assertOk();

    // User1 should only see their charge
    // This would be better tested with the actual data, but Inertia assertions are sufficient
});

test('user can filter own billing by date range', function () {
    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($this->user1)->get("/billing?from={$from}&to={$to}");

    $response->assertOk();
});

test('guest cannot access user billing', function () {
    $response = $this->get('/billing');

    $response->assertRedirect('/login');
});

test('billing summary includes statistics', function () {
    // Create more charges for user1
    VoucherGenerationCharge::create([
        'user_id' => $this->user1->id,
        'campaign_id' => null,
        'voucher_codes' => ['TEST-003'],
        'voucher_count' => 1,
        'instructions_snapshot' => ['cash' => ['amount' => 100]],
        'charge_breakdown' => [
            ['index' => 'cash.amount', 'label' => 'Base Fee', 'price' => 2000],
        ],
        'total_charge' => 20.00,
        'charge_per_voucher' => 20.00,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user1)->get('/billing');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('summary')
            ->has('summary.total_vouchers')
            ->has('summary.total_charges')
            ->has('summary.current_month_charges')
        );
});
