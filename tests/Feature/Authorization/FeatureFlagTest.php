<?php

/**
 * Test 4: Pennant feature flags (will fail initially, then pass after implementation).
 * 
 * Verifies that Laravel Pennant feature flags work correctly for authorization.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class]);
});

test('advanced-pricing-mode feature flag works', function () {
    $user = User::factory()->create();
    
    // Initially disabled
    expect(Feature::for($user)->value('advanced-pricing-mode'))->toBeFalse();
    
    // Activate feature
    Feature::for($user)->activate('advanced-pricing-mode');
    
    // Now enabled
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeTrue();
});

test('super-admins have advanced-pricing-mode enabled by default', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    // Super-admin should have advanced mode enabled
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeTrue();
});

test('regular users do not have advanced-pricing-mode', function () {
    $user = User::factory()->create();
    
    // Regular user should not have advanced mode
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeFalse();
});

test('feature flags are persisted to database', function () {
    $user = User::factory()->create();
    
    Feature::for($user)->activate('advanced-pricing-mode');
    
    // Check database (Pennant uses pipe separator in scope)
    $this->assertDatabaseHas('features', [
        'scope' => 'App\\Models\\User|'.$user->id,
        'name' => 'advanced-pricing-mode',
        'value' => 'true',
    ]);
});

test('feature flags can be deactivated', function () {
    $user = User::factory()->create();
    
    Feature::for($user)->activate('advanced-pricing-mode');
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeTrue();
    
    Feature::for($user)->deactivate('advanced-pricing-mode');
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeFalse();
});

test('multiple feature flags can be managed independently', function () {
    $user = User::factory()->create();
    
    Feature::for($user)->activate('advanced-pricing-mode');
    Feature::for($user)->activate('beta-features');
    
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeTrue();
    expect(Feature::for($user)->active('beta-features'))->toBeTrue();
    
    Feature::for($user)->deactivate('advanced-pricing-mode');
    
    expect(Feature::for($user)->active('advanced-pricing-mode'))->toBeFalse();
    expect(Feature::for($user)->active('beta-features'))->toBeTrue();
});
