<?php

/**
 * Test 6: Migration smoke test (will fail initially, then pass after implementation).
 * 
 * End-to-end test that verifies the complete authorization migration works correctly.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
});

test('complete authorization flow works end-to-end', function () {
    // 1. Seeded users have correct roles
    $admin = User::where('email', env('SYSTEM_USER_ID'))->first();
    $lester = User::where('email', 'lester@hurtado.ph')->first();
    
    expect($admin->hasRole('super-admin'))->toBeTrue();
    expect($lester->hasRole('super-admin'))->toBeTrue();
    
    // 2. Role-based access works
    $this->actingAs($admin)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
    
    $this->actingAs($lester)
        ->get(route('balances.index'))
        ->assertStatus(200);
    
    // 3. Feature flags work
    expect(Feature::for($admin)->active('advanced-pricing-mode'))->toBeTrue();
    
    // 4. Permissions are correct
    expect($admin->can('manage pricing'))->toBeTrue();
    expect($lester->can('view balance'))->toBeTrue();
});

test('backward compatibility with override emails', function () {
    config(['admin.override_emails' => ['legacy@example.com']]);
    
    $legacyUser = User::factory()->create(['email' => 'legacy@example.com']);
    
    // Can access without role via override
    $this->actingAs($legacyUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
    
    // Shared data includes is_admin_override
    $this->actingAs($legacyUser)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.is_admin_override', true)
        );
});

test('gradual migration scenario', function () {
    // Scenario: Some users use override, some use roles
    config(['admin.override_emails' => ['override@example.com']]);
    
    $overrideUser = User::factory()->create(['email' => 'override@example.com']);
    $roleUser = User::factory()->create(['email' => 'role@example.com']);
    $roleUser->assignRole('super-admin');
    
    // Both can access
    $this->actingAs($overrideUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
    
    $this->actingAs($roleUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
    
    // Then migrate override user to role
    $overrideUser->assignRole('super-admin');
    config(['admin.override_emails' => []]);
    
    // Still works via role
    $this->actingAs($overrideUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
});

test('authorization denies access correctly', function () {
    $regularUser = User::factory()->create(['email' => 'regular@example.com']);
    
    // No role, no override
    $response = $this->actingAs($regularUser)
        ->get(route('admin.pricing.index'));
    
    $response->assertStatus(403);
});

test('permissions are enforced at route level', function () {
    $user = User::factory()->create();
    
    // Without permission
    $this->actingAs($user)
        ->get(route('admin.pricing.index'))
        ->assertStatus(403);
    
    // Grant permission via role
    $user->assignRole('super-admin');
    
    // Now allowed
    $this->actingAs($user)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
});

test('system handles edge cases gracefully', function () {
    // Edge case 1: User with role but not logged in
    $response = $this->get(route('admin.pricing.index'));
    $response->assertRedirect(); // Redirect to login
    
    // Edge case 2: User with deleted role
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $user->removeRole('super-admin');
    
    $this->actingAs($user)
        ->get(route('admin.pricing.index'))
        ->assertStatus(403);
    
    // Edge case 3: Empty override emails config
    config(['admin.override_emails' => []]);
    $overrideUser = User::factory()->create(['email' => 'admin@example.com']);
    
    $this->actingAs($overrideUser)
        ->get(route('admin.pricing.index'))
        ->assertStatus(403);
});
