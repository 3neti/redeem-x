# Implementation Roadmap: Pennant + Spatie Authorization

## Branch: `feature/pennant-spatie-authorization`

## Strategy: TDD + Incremental Migration

**Goal:** Migrate from `.env` admin override to proper RBAC with zero downtime.

---

## Phase 1: Setup & Documentation ✅ COMPLETE

- [x] Create comprehensive documentation
- [x] Merge UI v2 improvements to main
- [x] Create feature branch

---

## Phase 2: Write Tests First (TDD Red Phase)

### Checklist

- [ ] Install Pennant
  ```bash
  composer require laravel/pennant
  php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
  php artisan migrate
  ```

- [ ] Create test files:
  ```bash
  php artisan make:test Authorization/AdminRouteAccessTest
  php artisan make:test Authorization/RoleAssignmentTest
  php artisan make:test Authorization/BackwardCompatibilityTest
  php artisan make:test Authorization/FeatureFlagTest
  php artisan make:test Authorization/UserSeederTest
  php artisan make:test Authorization/MigrationSmokeTest
  ```

- [ ] Write Test 1: Current behavior (should pass)
- [ ] Write Test 2: Role-based access (will fail)
- [ ] Write Test 3: Dual support (will fail)
- [ ] Write Test 4: Pennant flags (will fail)
- [ ] Write Test 5: Seeder verification (will fail)
- [ ] Write Test 6: End-to-end smoke test (will fail)

- [ ] Run tests, verify baseline:
  ```bash
  php artisan test tests/Feature/Authorization
  ```

**Expected Result:** Test 1 ✅ passes, Tests 2-6 🔴 fail

**Reference:** `docs/TDD_PENNANT_MIGRATION.md` (Steps 1-6)

---

## Phase 3: Implement Seeders (TDD Green Phase)

### Checklist

- [ ] Update `RolePermissionSeeder`:
  - Add all permissions (view-pricing, use-advanced-mode, etc.)
  - Create roles (super-admin, admin, manager, beta-tester, user)
  - Assign permissions to roles

- [ ] Update `UserSeeder`:
  - Assign super-admin to admin@disburse.cash
  - Assign super-admin to lester@hurtado.ph
  - Preserve workos_id

- [ ] Update `DatabaseSeeder`:
  - Ensure RolePermissionSeeder runs first
  - Then UserSeeder

- [ ] Run seeder:
  ```bash
  php artisan db:seed --class=RolePermissionSeeder
  php artisan db:seed --class=UserSeeder
  ```

- [ ] Verify in Tinker:
  ```php
  User::where('email', 'lester@hurtado.ph')->first()->roles->pluck('name');
  // Should show ['super-admin']
  ```

- [ ] Run Test 5 → Should ✅ pass

**Reference:** `docs/WORKOS_SPATIE_INTEGRATION.md` (Updated UserSeeder)

---

## Phase 4: Define Feature Flags

### Checklist

- [ ] Update `AppServiceProvider::boot()`:
  - Define `voucher-advanced-mode`
  - Define `feature-validation-location`
  - Define `feature-validation-time`
  - Define `feature-admin-pricing`
  - Define `feature-balance-monitoring`
  - Define `feature-preview-controls`

- [ ] Test feature flags in Tinker:
  ```php
  $user = User::where('email', 'lester@hurtado.ph')->first();
  Feature::for($user)->active('voucher-advanced-mode');
  // Should be true
  ```

- [ ] Run Test 4 → Should ✅ pass

**Reference:** `docs/AUTHORIZATION_STRATEGY.md` (Phase 2.1)

---

## Phase 5: Add Backward Compatible Routes (No Breaking)

### Checklist

- [ ] Update `HandleInertiaRequests`:
  - Modify `is_admin_override` to check BOTH .env AND roles
  ```php
  'is_admin_override' => $user && (
      in_array($user->email, config('admin.override_emails', [])) ||
      $user->hasRole('super-admin')
  ),
  ```

- [ ] Add Gates to `AppServiceProvider` or `AuthServiceProvider`:
  - `viewPricing`
  - `viewBalances`
  - `useAdvancedMode`
  - `useLocationValidation`
  - `useTimeValidation`

- [ ] Update admin routes to dual support:
  ```php
  // Support BOTH .env override AND role
  Route::middleware(['auth'])->group(function () {
      Route::get('/admin/pricing', ...)->middleware(function ($request, $next) {
          if (in_array($request->user()->email, config('admin.override_emails', [])) ||
              $request->user()->hasRole('super-admin')) {
              return $next($request);
          }
          abort(403);
      });
  });
  ```

- [ ] Run Tests 1-3 → All should ✅ pass (backward compatible)

- [ ] Test manually:
  ```bash
  /dev-login/lester@hurtado.ph
  # Visit /admin/pricing → Should work
  ```

**Reference:** `docs/BREAKING_CHANGES_ANALYSIS.md` (Phase 2: Dual Support)

---

## Phase 6: Update Controllers

### Checklist

- [ ] Update `GenerateVouchersController`:
  - Pass feature flags to frontend
  - Use Pennant to determine initial mode

- [ ] Add feature checking method:
  ```php
  private function buildConfig(array $features): array
  {
      $config = config('generate');
      if (!$features['validation_location']) {
          $config['location_validation']['show_card'] = false;
      }
      // etc.
      return $config;
  }
  ```

- [ ] Update existing tests that use admin routes:
  ```php
  // In tests, assign role explicitly
  $user->assignRole('super-admin');
  ```

- [ ] Run Test 2 → Should ✅ pass

**Reference:** `docs/AUTHORIZATION_STRATEGY.md` (Phase 3.2)

---

## Phase 7: Integration Testing

### Checklist

- [ ] Run full test suite:
  ```bash
  php artisan test
  ```

- [ ] Run authorization tests:
  ```bash
  php artisan test tests/Feature/Authorization
  ```

- [ ] Run Test 6 (smoke test) → Should ✅ pass

- [ ] Manual testing:
  - [ ] Login as lester@hurtado.ph
  - [ ] Access /admin/pricing ✅
  - [ ] Access /balances ✅
  - [ ] Generate voucher in Advanced Mode ✅
  - [ ] Login as regular user
  - [ ] Cannot access /admin/pricing ✅
  - [ ] Cannot access /balances ✅
  - [ ] Only see Simple Mode ✅

**Expected:** All ✅ green, both .env and roles work

---

## Phase 8: Cutover (OPTIONAL - Only After Testing)

⚠️ **ONLY DO THIS AFTER PHASE 7 PASSES 100%**

### Checklist

- [ ] Update routes to use ONLY roles:
  ```php
  Route::middleware(['auth', 'role:super-admin'])->group(function () {
      Route::get('/admin/pricing', ...);
  });
  ```

- [ ] Update `HandleInertiaRequests` to ONLY check roles:
  ```php
  'is_admin_override' => $user && $user->hasRole('super-admin'),
  ```

- [ ] Remove from `.env`:
  ```bash
  # Comment out, don't delete (for rollback)
  # ADMIN_OVERRIDE_EMAILS=lester@hurtado.ph
  ```

- [ ] Update `.env.example` (remove override example)

- [ ] Remove `admin.override` middleware alias from `bootstrap/app.php`

- [ ] Optional: Delete obsolete files (after confirming everything works):
  ```bash
  # Only after 1-2 weeks of testing
  # rm config/admin.php
  # rm app/Http/Middleware/AllowAdminOverride.php
  ```

- [ ] Run all tests → All should still ✅ pass

**Reference:** `docs/BREAKING_CHANGES_ANALYSIS.md` (Phase 4: Cutover)

---

## Rollback Plan

If anything breaks at any phase:

```bash
# Rollback git changes
git reset --hard HEAD~1

# Re-add .env override
echo "ADMIN_OVERRIDE_EMAILS=lester@hurtado.ph" >> .env

# Restart
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

Roles remain in database (non-destructive).

---

## Success Criteria

✅ All tests pass (especially Authorization tests)
✅ lester@hurtado.ph has super-admin role
✅ admin@disburse.cash has super-admin role
✅ Both can access all admin pages
✅ Regular users cannot access admin pages
✅ Feature flags work correctly
✅ Advanced Mode available to authorized users
✅ Zero downtime during migration
✅ Backward compatibility maintained

---

## Timeline

- **Day 1**: Phases 2-3 (Tests + Seeders) - 4-6 hours
- **Day 2**: Phases 4-5 (Flags + Routes) - 4-6 hours
- **Day 3**: Phases 6-7 (Controllers + Testing) - 2-4 hours
- **Day 4+**: Phase 8 (Cutover) - Only if confident
- **Week 2**: Monitor production, remove obsolete code

---

## Current Status

- [x] Phase 1: Setup ✅ COMPLETE
- [ ] Phase 2: Write tests (NEXT)
- [ ] Phase 3: Seeders
- [ ] Phase 4: Feature flags
- [ ] Phase 5: Dual routes
- [ ] Phase 6: Controllers
- [ ] Phase 7: Integration testing
- [ ] Phase 8: Cutover (optional)

---

## Quick Commands

```bash
# Run tests
php artisan test tests/Feature/Authorization

# Check roles
php artisan tinker
>>> User::where('email', 'lester@hurtado.ph')->first()->roles->pluck('name')

# Verify seeders
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder

# Dev login
/dev-login/lester@hurtado.ph

# Check feature flags
php artisan tinker
>>> Feature::for($user)->active('voucher-advanced-mode')
```

---

## Documentation References

- `docs/TDD_PENNANT_MIGRATION.md` - Complete test suite
- `docs/AUTHORIZATION_STRATEGY.md` - Implementation details
- `docs/BREAKING_CHANGES_ANALYSIS.md` - Risk assessment
- `docs/WORKOS_SPATIE_INTEGRATION.md` - WorkOS integration
- `docs/FEATURE_FLAG_STRATEGY.md` - Feature flag patterns

---

## Notes

- **Keep .env override during Phases 2-7** for safety
- **Run tests after each phase** - don't proceed if tests fail
- **Phase 8 is optional** - can run dual support indefinitely
- **No rush** - better to test thoroughly than deploy broken code

Good luck! 🚀
