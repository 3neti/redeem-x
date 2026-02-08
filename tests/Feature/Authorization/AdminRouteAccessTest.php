<?php

/**
 * Test 1: Current admin override behavior (baseline test - should pass).
 *
 * Verifies that the existing ADMIN_OVERRIDE_EMAILS mechanism works correctly.
 * This test validates current behavior before migrating to role-based access.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
});

test('admin override email can access pricing page', function () {
    // Set ADMIN_OVERRIDE_EMAILS in config
    config(['admin.override_emails' => ['lester@hurtado.ph']]);

    // Create user matching override email
    $user = User::factory()->create([
        'email' => 'lester@hurtado.ph',
        'name' => 'Lester Hurtado',
    ]);

    // Act as override user
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));

    // Should be able to access admin route
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/pricing/Index')
    );
});

test('admin override email can access balance monitoring', function () {
    config(['admin.override_emails' => ['admin@disburse.cash']]);

    $user = User::factory()->create([
        'email' => 'admin@disburse.cash',
        'name' => 'Admin User',
    ]);

    $response = $this->actingAs($user)
        ->get(route('balances.index'));

    $response->assertStatus(200);
});

test('regular user cannot access admin routes', function () {
    config(['admin.override_emails' => ['admin@example.com']]);

    $user = User::factory()->create([
        'email' => 'regular@example.com',
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));

    // Should be denied (403 forbidden)
    $response->assertStatus(403);
});

test('is_admin_override prop is shared correctly', function () {
    config(['admin.override_emails' => ['power@user.com']]);

    $user = User::factory()->create([
        'email' => 'power@user.com',
    ]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('auth.is_admin_override', true)
    );
});

test('multiple admin override emails work', function () {
    config(['admin.override_emails' => [
        'admin@example.com',
        'power@example.com',
    ]]);

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $power = User::factory()->create(['email' => 'power@example.com']);
    $regular = User::factory()->create(['email' => 'regular@example.com']);

    // Admin can access
    $this->actingAs($admin)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);

    // Power user can access
    $this->actingAs($power)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);

    // Regular user cannot access
    $this->actingAs($regular)
        ->get(route('admin.pricing.index'))
        ->assertStatus(403);
});
