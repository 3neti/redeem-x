<?php

/**
 * Test 5: User seeder verification (will fail initially, then pass after implementation).
 * 
 * Verifies that both admin@disburse.cash and lester@hurtado.ph have super-admin role.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('UserSeeder creates admin@disburse.cash with super-admin role', function () {
    // Run seeders
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    // Find admin user
    $admin = User::where('email', env('SYSTEM_USER_ID'))->first();
    
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('super-admin'))->toBeTrue();
    expect($admin->workos_id)->toBe('user_01K9V1DWFP0M2312PPCTHKPK9C');
});

test('UserSeeder creates lester@hurtado.ph with super-admin role', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    // Find lester user
    $lester = User::where('email', 'lester@hurtado.ph')->first();
    
    expect($lester)->not->toBeNull();
    expect($lester->hasRole('super-admin'))->toBeTrue();
    expect($lester->workos_id)->toBe('user_01K9H6FQS9S11T5S4MM55KA72S');
});

test('both users can access admin routes', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    $admin = User::where('email', env('SYSTEM_USER_ID'))->first();
    $lester = User::where('email', 'lester@hurtado.ph')->first();
    
    // Admin can access
    $this->actingAs($admin)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
    
    // Lester can access
    $this->actingAs($lester)
        ->get(route('admin.pricing.index'))
        ->assertStatus(200);
});

test('users have correct workos_id mapping', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    $admin = User::where('email', env('SYSTEM_USER_ID'))->first();
    $lester = User::where('email', 'lester@hurtado.ph')->first();
    
    // Verify WorkOS IDs match documentation
    expect($admin->workos_id)->toBe('user_01K9V1DWFP0M2312PPCTHKPK9C');
    expect($lester->workos_id)->toBe('user_01K9H6FQS9S11T5S4MM55KA72S');
});

test('seeder is idempotent and can be run multiple times', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    // Run again
    $this->seed([\Database\Seeders\UserSeeder::class]);
    
    // Should still have exactly one of each user
    $adminCount = User::where('email', env('SYSTEM_USER_ID'))->count();
    $lesterCount = User::where('email', 'lester@hurtado.ph')->count();
    
    expect($adminCount)->toBe(1);
    expect($lesterCount)->toBe(1);
});
