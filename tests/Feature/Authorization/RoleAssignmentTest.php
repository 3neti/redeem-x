<?php

/**
 * Test 2: Role-based access (will fail initially, then pass after implementation).
 * 
 * Verifies that users with proper roles can access admin routes.
 * This test will fail until we implement role-based authorization.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
});

test('super-admin role can access admin pricing', function () {
    $user = User::factory()->create([
        'email' => 'admin@disburse.cash',
        'name' => 'Admin User',
    ]);
    
    // Assign super-admin role
    $user->assignRole('super-admin');
    
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('admin/pricing/Index')
    );
});

test('admin role can access balance monitoring', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'name' => 'Admin',
    ]);
    
    // Assign admin role
    $user->assignRole('admin');
    
    $response = $this->actingAs($user)
        ->get(route('balances.index'));
    
    $response->assertStatus(200);
});

test('power-user role can edit pricing', function () {
    $user = User::factory()->create([
        'email' => 'power@example.com',
    ]);
    
    // Assign power-user role (should have manage pricing permission)
    $user->assignRole('power-user');
    
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));
    
    $response->assertStatus(200);
});

test('user without admin role cannot access admin routes', function () {
    $user = User::factory()->create([
        'email' => 'regular@example.com',
    ]);
    
    // No role assigned
    
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));
    
    $response->assertStatus(403);
});

test('user with basic-user role cannot access admin routes', function () {
    $user = User::factory()->create([
        'email' => 'basic@example.com',
    ]);
    
    $user->assignRole('basic-user');
    
    $response = $this->actingAs($user)
        ->get(route('admin.pricing.index'));
    
    $response->assertStatus(403);
});

test('roles have correct permissions', function () {
    $superAdmin = Role::findByName('super-admin');
    $admin = Role::findByName('admin');
    $powerUser = Role::findByName('power-user');
    
    // Super-admin has all permissions
    expect($superAdmin->hasPermissionTo('manage pricing'))->toBeTrue();
    expect($superAdmin->hasPermissionTo('view balance'))->toBeTrue();
    
    // Admin has balance viewing
    expect($admin->hasPermissionTo('view balance'))->toBeTrue();
    
    // Power-user has pricing management
    expect($powerUser->hasPermissionTo('manage pricing'))->toBeTrue();
});
