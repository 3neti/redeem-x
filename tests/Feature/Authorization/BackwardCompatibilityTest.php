<?php

/**
 * Test 3: Backward compatibility (will fail initially, then pass after implementation).
 *
 * Verifies that BOTH .env override AND role-based access work simultaneously.
 * This ensures no breaking changes during migration.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
});

test('admin override still works after role system is implemented', function () {
    config(['admin.override_emails' => ['override@example.com']]);

    $user = User::factory()->create([
        'email' => 'override@example.com',
    ]);

    // User has NO role, but should still access via override
    expect($user->roles)->toHaveCount(0);

    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));

    $response->assertStatus(200);
});

test('role-based access works alongside override emails', function () {
    config(['admin.override_emails' => ['override@example.com']]);

    // User with role
    $roleUser = User::factory()->create(['email' => 'role@example.com']);
    $roleUser->assignRole('super-admin');

    // User with override
    $overrideUser = User::factory()->create(['email' => 'override@example.com']);

    // Both can access
    $this->actingAs($roleUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);

    $this->actingAs($overrideUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
});

test('role takes precedence when user has both', function () {
    config(['admin.override_emails' => ['both@example.com']]);

    $user = User::factory()->create(['email' => 'both@example.com']);
    $user->assignRole('super-admin');

    // Has both override email AND role
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));

    $response->assertStatus(200);

    // Middleware should check role first (more explicit)
    expect($user->hasRole('super-admin'))->toBeTrue();
});

test('is_admin_override prop remains true for override users', function () {
    config(['admin.override_emails' => ['legacy@example.com']]);

    $user = User::factory()->create(['email' => 'legacy@example.com']);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->where('auth.is_admin_override', true)
    );
});

test('removing override email does not affect role-based access', function () {
    // Start with both
    config(['admin.override_emails' => ['admin@example.com']]);

    $user = User::factory()->create(['email' => 'admin@example.com']);
    $user->assignRole('super-admin');

    // Can access
    $this->actingAs($user)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);

    // Remove from override emails
    config(['admin.override_emails' => []]);

    // Still can access via role
    $this->actingAs($user)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
});

test('user loses access when both override and role are removed', function () {
    config(['admin.override_emails' => ['temp@example.com']]);

    $user = User::factory()->create(['email' => 'temp@example.com']);
    $user->assignRole('super-admin');

    // Remove override
    config(['admin.override_emails' => []]);

    // Remove role
    $user->removeRole('super-admin');

    // Now blocked
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));

    $response->assertStatus(403);
});
