# TDD Strategy: Pennant + Spatie Migration

## Philosophy

**Write tests FIRST, then migrate.** This ensures:
✅ Current behavior documented in tests
✅ Breaking changes caught immediately
✅ Safe refactoring with confidence
✅ Regression prevention

---

## Test Suite Structure

```
tests/Feature/Authorization/
├── RoleAssignmentTest.php          # Verify roles assigned correctly
├── AdminRouteAccessTest.php         # Verify admin pages work
├── FeatureFlagTest.php              # Verify Pennant flags work
├── FrontendPropsTest.php            # Verify Inertia props correct
├── BackwardCompatibilityTest.php    # Verify .env override still works
└── MigrationSmokeTest.php           # End-to-end verification
```

---

## Step 1: Document Current Behavior (Red Phase)

### Test 1: Admin Access via .env Override

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed required data
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'InstructionItemSeeder']);
});

describe('Admin Access (Current Behavior)', function () {
    
    it('allows lester@hurtado.ph to access admin pages via .env override', function () {
        // Given: User in ADMIN_OVERRIDE_EMAILS
        config(['admin.override_emails' => ['lester@hurtado.ph']]);
        
        $user = User::factory()->create([
            'email' => 'lester@hurtado.ph',
            'workos_id' => 'user_01K9H6FQS9S11T5S4MM55KA72S',
        ]);
        
        // When: User accesses admin pages
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Access granted
        $response->assertOk();
    });
    
    it('allows admin@disburse.cash to access admin pages', function () {
        // Given: System admin
        config(['admin.override_emails' => ['admin@disburse.cash']]);
        
        $admin = User::factory()->create([
            'email' => 'admin@disburse.cash',
            'workos_id' => 'user_01K9V1DWFP0M2312PPCTHKPK9C',
        ]);
        
        // When: Admin accesses pages
        $response = $this->actingAs($admin)->get('/admin/pricing');
        
        // Then: Access granted
        $response->assertOk();
    });
    
    it('denies regular users from admin pages', function () {
        // Given: Regular user NOT in override list
        config(['admin.override_emails' => []]);
        
        $user = User::factory()->create([
            'email' => 'regular@example.com',
        ]);
        
        // When: User tries to access admin page
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Access denied
        $response->assertForbidden();
    });
    
    it('shows admin menu items for override users', function () {
        // Given: User in override list
        config(['admin.override_emails' => ['lester@hurtado.ph']]);
        
        $user = User::factory()->create(['email' => 'lester@hurtado.ph']);
        
        // When: User visits dashboard
        $response = $this->actingAs($user)->get('/dashboard');
        
        // Then: Inertia props include is_admin_override
        expect($response->inertia('auth.is_admin_override'))->toBeTrue();
    });
});
```

**Run this now:** `php artisan test tests/Feature/Authorization/AdminRouteAccessTest.php`

Expected: ✅ All pass (documents current behavior)

---

## Step 2: Install Pennant (Still Red)

```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

**Run tests again:** Should still ✅ pass (nothing broken yet)

---

## Step 3: Add Tests for New Behavior (More Red)

### Test 2: Role-Based Access

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'InstructionItemSeeder']);
});

describe('Role-Based Access (New Behavior)', function () {
    
    it('allows super-admin role to access admin pages', function () {
        // Given: User with super-admin role
        $user = User::factory()->create(['email' => 'test@example.com']);
        $user->assignRole('super-admin');
        
        // When: User accesses admin page
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Access granted
        $response->assertOk();
    });
    
    it('denies users without super-admin role', function () {
        // Given: User with 'user' role
        $user = User::factory()->create();
        $user->assignRole('user');
        
        // When: User tries to access admin page
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Access denied
        $response->assertForbidden();
    });
    
    it('shows admin menu items for super-admin role', function () {
        // Given: User with super-admin role
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        
        // When: User visits dashboard
        $response = $this->actingAs($user)->get('/dashboard');
        
        // Then: Auth props include super-admin role
        expect($response->inertia('auth.roles'))->toContain('super-admin');
        expect($response->inertia('auth.is_admin_override'))->toBeTrue(); // Dual support
    });
});
```

**Run this:** `php artisan test tests/Feature/Authorization/RoleAssignmentTest.php`

Expected: 🔴 **FAILS** (routes still use admin.override middleware)

---

## Step 4: Test Backward Compatibility (Critical)

### Test 3: Dual Support

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    $this->artisan('db:seed', ['--class' => 'InstructionItemSeeder']);
});

describe('Backward Compatibility (Dual Support)', function () {
    
    it('allows access via .env override even without role', function () {
        // Given: User in .env but no role
        config(['admin.override_emails' => ['legacy@example.com']]);
        
        $user = User::factory()->create(['email' => 'legacy@example.com']);
        // No role assigned!
        
        // When: User accesses admin page
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Still works (backward compatible)
        $response->assertOk();
    });
    
    it('allows access via role even without .env override', function () {
        // Given: User with role but not in .env
        config(['admin.override_emails' => []]);
        
        $user = User::factory()->create(['email' => 'new@example.com']);
        $user->assignRole('super-admin');
        
        // When: User accesses admin page
        $response = $this->actingAs($user)->get('/admin/pricing');
        
        // Then: Works via role
        $response->assertOk();
    });
    
    it('prefers role over .env when both present', function () {
        // Given: User has role AND in .env
        config(['admin.override_emails' => ['both@example.com']]);
        
        $user = User::factory()->create(['email' => 'both@example.com']);
        $user->assignRole('super-admin');
        
        // When: Remove from .env
        config(['admin.override_emails' => []]);
        
        // Then: Still works via role
        $response = $this->actingAs($user)->get('/admin/pricing');
        $response->assertOk();
    });
});
```

**Run this:** Expected 🔴 **FAILS** (not implemented yet)

---

## Step 5: Test Feature Flags

### Test 4: Pennant Integration

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

describe('Pennant Feature Flags', function () {
    
    it('activates advanced mode for users with permission', function () {
        // Given: User with use-advanced-mode permission
        $user = User::factory()->create();
        $user->assignRole('admin'); // Admin has use-advanced-mode
        
        // When: Check feature flag
        $isActive = Feature::for($user)->active('voucher-advanced-mode');
        
        // Then: Feature is active
        expect($isActive)->toBeTrue();
    });
    
    it('denies advanced mode for users without permission', function () {
        // Given: Regular user
        $user = User::factory()->create();
        $user->assignRole('user');
        
        // When: Check feature flag
        $isActive = Feature::for($user)->active('voucher-advanced-mode');
        
        // Then: Feature is inactive
        expect($isActive)->toBeFalse();
    });
    
    it('allows manual feature toggle for permitted users', function () {
        // Given: User with permission
        $user = User::factory()->create();
        $user->assignRole('manager');
        
        // When: User manually enables feature
        Feature::for($user)->activate('voucher-advanced-mode');
        
        // Then: Feature remains active
        expect(Feature::for($user)->active('voucher-advanced-mode'))->toBeTrue();
        
        // When: User manually disables feature
        Feature::for($user)->deactivate('voucher-advanced-mode');
        
        // Then: Feature is inactive
        expect(Feature::for($user)->active('voucher-advanced-mode'))->toBeFalse();
    });
    
    it('hides location validation for users without permission', function () {
        // Given: Regular user
        $user = User::factory()->create();
        $user->assignRole('user');
        
        // When: Check feature flag
        $hasAccess = Feature::for($user)->active('feature-validation-location');
        
        // Then: Feature is hidden
        expect($hasAccess)->toBeFalse();
    });
    
    it('shows location validation for admins', function () {
        // Given: Admin user
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        // When: Check feature flag
        $hasAccess = Feature::for($user)->active('feature-validation-location');
        
        // Then: Feature is visible
        expect($hasAccess)->toBeTrue();
    });
});
```

**Run this:** Expected 🔴 **FAILS** (features not defined yet)

---

## Step 6: Test User Seeders

### Test 5: Seeder Verification

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\UserSeeder;
use Database\Seeders\RolePermissionSeeder;

uses(RefreshDatabase::class);

describe('User Seeder with Roles', function () {
    
    it('assigns super-admin role to admin@disburse.cash', function () {
        // Given: Fresh database
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class]);
        
        // When: Run UserSeeder
        $this->artisan('db:seed', ['--class' => UserSeeder::class]);
        
        // Then: Admin has super-admin role
        $admin = User::where('email', 'admin@disburse.cash')->first();
        
        expect($admin)->not->toBeNull()
            ->and($admin->hasRole('super-admin'))->toBeTrue()
            ->and($admin->can('view-pricing'))->toBeTrue()
            ->and($admin->can('view-balances'))->toBeTrue();
    });
    
    it('assigns super-admin role to lester@hurtado.ph', function () {
        // Given: Fresh database
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class]);
        
        // When: Run UserSeeder
        $this->artisan('db:seed', ['--class' => UserSeeder::class]);
        
        // Then: Lester has super-admin role
        $lester = User::where('email', 'lester@hurtado.ph')->first();
        
        expect($lester)->not->toBeNull()
            ->and($lester->hasRole('super-admin'))->toBeTrue()
            ->and($lester->can('view-pricing'))->toBeTrue()
            ->and($lester->can('use-advanced-mode'))->toBeTrue();
    });
    
    it('preserves workos_id for existing users', function () {
        // Given: Existing user with workos_id
        User::create([
            'email' => 'lester@hurtado.ph',
            'name' => 'Old Name',
            'workos_id' => 'user_01K9H6FQS9S11T5S4MM55KA72S',
        ]);
        
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class]);
        
        // When: Run seeder again
        $this->artisan('db:seed', ['--class' => UserSeeder::class]);
        
        // Then: workos_id preserved
        $user = User::where('email', 'lester@hurtado.ph')->first();
        
        expect($user->workos_id)->toBe('user_01K9H6FQS9S11T5S4MM55KA72S')
            ->and($user->hasRole('super-admin'))->toBeTrue();
    });
});
```

**Run this:** Expected 🔴 **FAILS** (seeder not updated yet)

---

## Step 7: Implement (Green Phase)

Now implement the migration following the strategy:

1. **Update UserSeeder** → Run Test 5 → ✅ Green
2. **Define Pennant features** → Run Test 4 → ✅ Green
3. **Add dual support to routes** → Run Test 3 → ✅ Green
4. **Update HandleInertiaRequests** → Run Test 2 → ✅ Green
5. **Verify nothing broke** → Run Test 1 → ✅ Still Green

**All tests must stay green at each step!**

---

## Step 8: Smoke Test (End-to-End)

### Test 6: Full Integration

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Full database setup
    $this->artisan('migrate:fresh');
    $this->artisan('db:seed');
});

describe('End-to-End Authorization', function () {
    
    it('completes full admin workflow for lester@hurtado.ph', function () {
        // Given: User logs in (simulate WorkOS)
        $lester = User::where('email', 'lester@hurtado.ph')->first();
        expect($lester)->not->toBeNull();
        
        // When: Visit admin pages
        $this->actingAs($lester)
            ->get('/admin/pricing')->assertOk()
            ->get('/admin/billing')->assertOk()
            ->get('/balances')->assertOk();
        
        // And: Visit voucher generation
        $response = $this->actingAs($lester)->get('/vouchers/generate');
        
        // Then: Has access to advanced mode
        expect($response->inertia('features.advanced_mode'))->toBeTrue()
            ->and($response->inertia('features.validation_location'))->toBeTrue();
    });
    
    it('completes full workflow for regular user', function () {
        // Given: Regular user
        $user = User::factory()->create();
        $user->assignRole('user');
        
        // When: Try to access admin pages
        $this->actingAs($user)
            ->get('/admin/pricing')->assertForbidden()
            ->get('/balances')->assertForbidden();
        
        // And: Visit voucher generation
        $response = $this->actingAs($user)->get('/vouchers/generate');
        
        // Then: No access to advanced features
        expect($response->inertia('features.advanced_mode'))->toBeFalse()
            ->and($response->inertia('features.validation_location'))->toBeFalse();
    });
});
```

**Run this:** Final validation → ✅ All Green

---

## TDD Benefits for This Migration

### ✅ **Catches Breaking Changes Early**

```bash
# Before touching routes
php artisan test
# ✅ All pass (baseline)

# After changing routes
php artisan test
# 🔴 4 tests fail → "Admin access broken for lester@hurtado.ph"
# ^ You know EXACTLY what broke
```

### ✅ **Documents Expected Behavior**

Tests serve as **executable documentation**:
- "Admin pages should work for lester@hurtado.ph" ← Test proves it
- "Regular users should not access admin pages" ← Test proves it

### ✅ **Enables Confident Refactoring**

```bash
# Change how routes work internally
# Tests still pass → Safe to deploy ✅

# Tests fail → Don't deploy, fix first 🔴
```

### ✅ **Prevents Regression**

Future developers can't accidentally break authorization without tests failing.

---

## Execution Plan

```bash
# Day 1: Write Tests (Red Phase)
php artisan make:test Authorization/AdminRouteAccessTest
php artisan make:test Authorization/RoleAssignmentTest
php artisan make:test Authorization/BackwardCompatibilityTest
php artisan make:test Authorization/FeatureFlagTest
php artisan make:test Authorization/UserSeederTest
php artisan make:test Authorization/MigrationSmokeTest

# Run tests - should have mix of ✅ pass and 🔴 fail
php artisan test tests/Feature/Authorization

# Day 2-3: Implement (Green Phase)
# Change one thing at a time, run tests after each change
# Goal: All tests ✅ green

# Day 4: Verify in browser
# Tests passing != bugs gone
# Manual testing still needed
```

---

## Success Criteria

✅ All tests pass
✅ Zero test failures after migration
✅ Both lester@hurtado.ph and admin@disburse.cash can access admin pages
✅ Regular users cannot access admin pages
✅ Feature flags work correctly
✅ Backward compatibility maintained during transition

---

## Recommendation

**Start with TDD! It will:**
1. Force you to understand current behavior
2. Catch breaking changes instantly
3. Give confidence to refactor
4. Serve as regression prevention
5. Document system behavior

**Timeline:**
- Day 1: Write failing tests (4-6 hours)
- Day 2: Implement until green (4-6 hours)
- Day 3: Manual verification (2 hours)
- Day 4: Deploy with confidence 🚀

This approach turns "⚠️ MODERATE RISK" into "✅ LOW RISK"! 🎯
