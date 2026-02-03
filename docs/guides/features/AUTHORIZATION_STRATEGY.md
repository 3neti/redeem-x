# Authorization Strategy: Pennant + Spatie Permissions

## Problem Statement

**Current Issues:**
1. Admin access via `.env` hack (`ADMIN_OVERRIDE_EMAILS`)
2. Spatie Permission installed but unused
3. No proper role-based access control (RBAC)
4. Features like `/admin/pricing`, `/balances` visible to all users
5. Advanced Mode toggle needs authorization

**Goal:** Consolidate into proper RBAC using Spatie Permissions + Pennant feature flags.

## Architecture

### Three-Layer Authorization

```
┌─────────────────────────────────────────────────┐
│ Layer 1: Roles & Permissions (Spatie)          │
│ - WHO can access features                       │
│ - Role: admin, manager, user, beta-tester       │
│ - Permission: view-pricing, view-balances, etc. │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ Layer 2: Feature Flags (Pennant)               │
│ - WHAT features are enabled                     │
│ - Flag: feature-validation-location             │
│ - Flag: feature-admin-pricing                   │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ Layer 3: UI Access Control (Gates/Policies)    │
│ - HOW users interact with features              │
│ - Gate: viewPricing, viewBalances               │
│ - Policy: VoucherPolicy, CampaignPolicy         │
└─────────────────────────────────────────────────┘
```

## Implementation Plan

### Phase 1: Setup Spatie Permissions

#### 1.1 Define Roles & Permissions

```php
// database/seeders/RolePermissionSeeder.php (update existing)
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

public function run(): void
{
    // Reset cached roles and permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // ═══════════════════════════════════════════════════════
    // PERMISSIONS
    // ═══════════════════════════════════════════════════════
    
    // Voucher Management
    Permission::firstOrCreate(['name' => 'generate-vouchers']);
    Permission::firstOrCreate(['name' => 'view-vouchers']);
    Permission::firstOrCreate(['name' => 'export-vouchers']);
    
    // Advanced Voucher Features
    Permission::firstOrCreate(['name' => 'use-advanced-mode']);
    Permission::firstOrCreate(['name' => 'use-validation-location']);
    Permission::firstOrCreate(['name' => 'use-validation-time']);
    
    // Admin Pages
    Permission::firstOrCreate(['name' => 'view-pricing']);
    Permission::firstOrCreate(['name' => 'edit-pricing']);
    Permission::firstOrCreate(['name' => 'view-balances']);
    Permission::firstOrCreate(['name' => 'view-disbursements']);
    Permission::firstOrCreate(['name' => 'view-reconciliation']);
    
    // Campaigns
    Permission::firstOrCreate(['name' => 'create-campaigns']);
    Permission::firstOrCreate(['name' => 'edit-campaigns']);
    Permission::firstOrCreate(['name' => 'delete-campaigns']);
    
    // Settings
    Permission::firstOrCreate(['name' => 'manage-settings']);
    Permission::firstOrCreate(['name' => 'manage-users']);
    
    // ═══════════════════════════════════════════════════════
    // ROLES
    // ═══════════════════════════════════════════════════════
    
    // Super Admin (all permissions)
    $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
    $superAdmin->givePermissionTo(Permission::all());
    
    // Admin (most permissions, no user management)
    $admin = Role::firstOrCreate(['name' => 'admin']);
    $admin->givePermissionTo([
        'generate-vouchers',
        'view-vouchers',
        'export-vouchers',
        'use-advanced-mode',
        'use-validation-location',
        'use-validation-time',
        'view-pricing',
        'edit-pricing',
        'view-balances',
        'view-disbursements',
        'view-reconciliation',
        'create-campaigns',
        'edit-campaigns',
        'delete-campaigns',
    ]);
    
    // Manager (standard features + some admin views)
    $manager = Role::firstOrCreate(['name' => 'manager']);
    $manager->givePermissionTo([
        'generate-vouchers',
        'view-vouchers',
        'export-vouchers',
        'use-advanced-mode',
        'view-balances',
        'create-campaigns',
        'edit-campaigns',
    ]);
    
    // Beta Tester (early access to experimental features)
    $betaTester = Role::firstOrCreate(['name' => 'beta-tester']);
    $betaTester->givePermissionTo([
        'generate-vouchers',
        'view-vouchers',
        'use-advanced-mode',
        'use-validation-location',
        'use-validation-time',
    ]);
    
    // User (basic features only)
    $user = Role::firstOrCreate(['name' => 'user']);
    $user->givePermissionTo([
        'generate-vouchers',
        'view-vouchers',
    ]);
}
```

#### 1.2 Migrate Override Emails to Roles

```php
// database/migrations/2026_01_01_000001_migrate_admin_overrides_to_roles.php
use App\Models\User;
use Spatie\Permission\Models\Role;

public function up(): void
{
    $overrideEmails = config('admin.override_emails', []);
    $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
    
    foreach ($overrideEmails as $email) {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->assignRole('super-admin');
            Log::info("Migrated admin override to role", ['email' => $email]);
        }
    }
}

public function down(): void
{
    // Roles remain, just log the action
    Log::info("Rollback: Admin roles were not removed (manual action required)");
}
```

### Phase 2: Integrate Pennant with Permissions

#### 2.1 Define Feature Flags (AppServiceProvider)

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;
use App\Models\User;

public function boot(): void
{
    // ═══════════════════════════════════════════════════════
    // UI MODE FLAGS
    // ═══════════════════════════════════════════════════════
    
    Feature::define('voucher-advanced-mode', function (User $user) {
        // Check if user has permission (from Spatie)
        if ($user->can('use-advanced-mode')) {
            // Check if user manually toggled (stored in features table)
            $manual = Feature::for($user)->value('voucher-advanced-mode');
            return $manual ?? true; // Default to enabled if they have permission
        }
        return false;
    });
    
    // ═══════════════════════════════════════════════════════
    // FEATURE FLAGS (Readiness + Permission)
    // ═══════════════════════════════════════════════════════
    
    // Location Validation (permission-based)
    Feature::define('feature-validation-location', function (User $user) {
        return $user->can('use-validation-location');
    });
    
    // Time Validation (permission-based)
    Feature::define('feature-validation-time', function (User $user) {
        return $user->can('use-validation-time');
    });
    
    // Admin Pricing Page (permission + kill switch)
    Feature::define('feature-admin-pricing', function (User $user) {
        // Kill switch for emergencies
        if (!config('features.admin_pricing_enabled', true)) {
            return false;
        }
        
        return $user->can('view-pricing');
    });
    
    // Balance Monitoring (permission-based)
    Feature::define('feature-balance-monitoring', function (User $user) {
        return $user->can('view-balances');
    });
    
    // ═══════════════════════════════════════════════════════
    // EXPERIMENTAL FEATURES (Gradual Rollout)
    // ═══════════════════════════════════════════════════════
    
    // Preview Controls (gradual rollout for advanced users)
    Feature::define('feature-preview-controls', function (User $user) {
        if (!$user->can('use-advanced-mode')) {
            return false;
        }
        
        // Admins always get it
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return true;
        }
        
        // 25% rollout for others
        return Lottery::odds(1, 4)->choose();
    });
}
```

#### 2.2 Create Gates for Common Checks

```php
// app/Providers/AuthServiceProvider.php (or AppServiceProvider)
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

public function boot(): void
{
    // ═══════════════════════════════════════════════════════
    // GATES (Combine permissions + feature flags)
    // ═══════════════════════════════════════════════════════
    
    // Can access admin pricing?
    Gate::define('viewPricing', function (User $user) {
        return Feature::for($user)->active('feature-admin-pricing');
    });
    
    // Can access balance monitoring?
    Gate::define('viewBalances', function (User $user) {
        return Feature::for($user)->active('feature-balance-monitoring');
    });
    
    // Can use advanced voucher mode?
    Gate::define('useAdvancedMode', function (User $user) {
        return Feature::for($user)->active('voucher-advanced-mode');
    });
    
    // Can use specific validation features?
    Gate::define('useLocationValidation', function (User $user) {
        return Feature::for($user)->active('feature-validation-location');
    });
    
    Gate::define('useTimeValidation', function (User $user) {
        return Feature::for($user)->active('feature-validation-time');
    });
}
```

### Phase 3: Update Routes & Controllers

#### 3.1 Protect Admin Routes

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

// Replace AllowAdminOverride with permission check
Route::middleware(['auth', 'can:viewPricing'])->group(function () {
    Route::get('/admin/pricing', [PricingController::class, 'index'])->name('admin.pricing');
    Route::put('/admin/pricing/{item}', [PricingController::class, 'update'])->name('admin.pricing.update');
});

Route::middleware(['auth', 'can:viewBalances'])->group(function () {
    Route::get('/balances', [BalanceController::class, 'index'])->name('balances.index');
    Route::get('/balances/{accountNumber}', [BalanceController::class, 'show'])->name('balances.show');
});
```

#### 3.2 Update Controllers to Check Features

```php
// app/Http/Controllers/Vouchers/GenerateVouchersController.php
use Laravel\Pennant\Feature;

public function create(Request $request)
{
    $user = $request->user();
    
    // Load available features
    $features = [
        'advanced_mode' => Gate::allows('useAdvancedMode'),
        'validation_location' => Gate::allows('useLocationValidation'),
        'validation_time' => Gate::allows('useTimeValidation'),
        'pricing' => Gate::allows('viewPricing'),
        'balances' => Gate::allows('viewBalances'),
    ];
    
    // Determine initial mode
    $initialMode = Feature::for($user)->active('voucher-advanced-mode') 
        ? 'advanced' 
        : 'simple';
    
    return Inertia::render('Vouchers/Generate/CreateV2', [
        'initialMode' => $initialMode,
        'features' => $features,
        'config' => $this->buildConfig($features),
    ]);
}
```

### Phase 4: Update Middleware & Navigation

#### 4.1 Remove AllowAdminOverride Middleware

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        // REMOVE: \App\Http\Middleware\AllowAdminOverride::class,
    ],
];
```

#### 4.2 Update Navigation (Conditional Menu Items)

```vue
<!-- resources/js/components/AppSidebar.vue -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const features = computed(() => page.props.features || {})

const showPricing = computed(() => features.value.pricing === true)
const showBalances = computed(() => features.value.balances === true)
</script>

<template>
    <nav>
        <!-- Always visible -->
        <NavLink href="/vouchers/generate">Generate Vouchers</NavLink>
        <NavLink href="/vouchers">My Vouchers</NavLink>
        
        <!-- Conditional admin items -->
        <NavLink v-if="showBalances" href="/balances">Balance Monitoring</NavLink>
        <NavLink v-if="showPricing" href="/admin/pricing">Pricing</NavLink>
    </nav>
</template>
```

### Phase 5: Testing & Validation

#### 5.1 Seed Test Users

```php
// database/seeders/UserSeeder.php (update)
$superAdmin = User::factory()->create([
    'email' => 'lester@hurtado.ph',
    'name' => 'Lester Hurtado',
]);
$superAdmin->assignRole('super-admin');

$admin = User::factory()->create([
    'email' => 'admin@example.com',
    'name' => 'Admin User',
]);
$admin->assignRole('admin');

$manager = User::factory()->create([
    'email' => 'manager@example.com',
    'name' => 'Manager User',
]);
$manager->assignRole('manager');

$betaTester = User::factory()->create([
    'email' => 'beta@example.com',
    'name' => 'Beta Tester',
]);
$betaTester->assignRole('beta-tester');

$regularUser = User::factory()->create([
    'email' => 'user@example.com',
    'name' => 'Regular User',
]);
$regularUser->assignRole('user');
```

#### 5.2 Test Feature Access

```php
// tests/Feature/AuthorizationTest.php
use Laravel\Pennant\Feature;

it('super admin can access all features', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');
    
    expect($user->can('view-pricing'))->toBeTrue()
        ->and($user->can('view-balances'))->toBeTrue()
        ->and($user->can('use-advanced-mode'))->toBeTrue()
        ->and(Feature::for($user)->active('feature-admin-pricing'))->toBeTrue();
});

it('regular user cannot access admin pages', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    
    expect($user->can('view-pricing'))->toBeFalse()
        ->and($user->can('view-balances'))->toBeFalse()
        ->and(Gate::allows('viewPricing'))->toBeFalse();
});

it('beta tester can access experimental features', function () {
    $user = User::factory()->create();
    $user->assignRole('beta-tester');
    
    expect($user->can('use-validation-location'))->toBeTrue()
        ->and(Feature::for($user)->active('feature-validation-location'))->toBeTrue();
});
```

## Migration Checklist

- [ ] Run RolePermissionSeeder to create roles/permissions
- [ ] Run migration to assign roles to override emails
- [ ] Update AppServiceProvider with Pennant feature definitions
- [ ] Update AuthServiceProvider with Gates
- [ ] Update routes to use `can:` middleware
- [ ] Remove AllowAdminOverride middleware
- [ ] Update controllers to check features
- [ ] Update navigation to conditionally show items
- [ ] Test with different user roles
- [ ] Remove ADMIN_OVERRIDE_EMAILS from .env
- [ ] Update .env.example
- [ ] Update documentation

## Benefits of New System

✅ **Proper RBAC**: Standard Laravel pattern
✅ **Granular Control**: Permission per feature
✅ **Auditable**: Role assignments tracked in DB
✅ **Testable**: Easy to test different user scenarios
✅ **Scalable**: Add new roles/permissions easily
✅ **Feature Flags**: Control feature rollout independently
✅ **No .env Hacks**: Clean configuration

## Rollback Plan

If issues arise:
1. Re-enable AllowAdminOverride middleware
2. Add ADMIN_OVERRIDE_EMAILS back to .env
3. Roles/permissions remain in database (no data loss)

## Future Enhancements

1. **Admin UI for Role Management**
   - CRUD for roles/permissions
   - Assign/revoke roles from users
   
2. **Feature Request Workflow**
   - Users request advanced mode
   - Admins approve/deny via UI
   
3. **Audit Logging**
   - Track who accessed what features
   - Log permission changes

4. **Multi-tenancy**
   - Organization-level feature flags
   - Different pricing per org

## Recommendation

**Implement this strategy in phases:**

1. **Week 1**: Setup roles/permissions, migrate override emails
2. **Week 2**: Integrate Pennant, update controllers
3. **Week 3**: Update navigation, test thoroughly
4. **Week 4**: Remove .env override, deploy

This gives you a production-ready authorization system! 🔐
