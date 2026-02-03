# Breaking Changes Analysis: Pennant + Spatie Migration

## Risk Assessment: ⚠️ MODERATE RISK

This refactor has **moderate breaking potential** but is **non-destructive** with clear rollback path.

---

## 🔴 CRITICAL: Will Break Immediately

### 1. Admin Route Access (HIGH IMPACT)

**What breaks:**
```php
// routes/web.php line 187
Route::middleware(['auth', 'admin.override'])->group(function () {
    Route::get('/admin/pricing', ...);
    Route::get('/admin/billing', ...);
    // etc.
});
```

**Why it breaks:**
- If you remove `AllowAdminOverride` middleware, routes protected by `admin.override` will stop working
- Users in `ADMIN_OVERRIDE_EMAILS` will lose access immediately

**Impact:**
- ❌ `/admin/pricing` → 403 Forbidden
- ❌ `/admin/billing` → 403 Forbidden  
- ❌ `/admin/preferences` → 403 Forbidden
- ⚠️ **lester@hurtado.ph loses admin access** until role assigned

**Safe Migration:**
```php
// STEP 1: Add BOTH checks (backward compatible)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/pricing', ...)->middleware('admin.override OR role:super-admin');
});

// STEP 2: After roles assigned, remove admin.override
Route::middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/admin/pricing', ...);
});
```

---

### 2. Frontend Admin Detection (MEDIUM IMPACT)

**What breaks:**
```js
// HandleInertiaRequests.php line 55
'is_admin_override' => in_array($request->user()->email, config('admin.override_emails', []))
```

**Why it breaks:**
- If you remove this, any frontend code checking `$page.props.auth.is_admin_override` will fail
- Might hide admin menu items or features

**Impact:**
- ⚠️ Navigation items might disappear
- ⚠️ Admin badges/indicators might not show

**Where it's used:**
```vue
// AppSidebar.vue lines 38, 40, 92, 100, 108
const isAdminOverride = computed(() => page.props.auth?.is_admin_override || false);
const hasAdminAccess = computed(() => isSuperAdmin.value || isAdminOverride.value);

// Shows admin menu items:
if (isAdminOverride.value || permissions.value.includes('view all billing')) {
    // Show Billing link
}
```

**Safe Migration:**
```php
// HandleInertiaRequests.php - Keep is_admin_override for now
'is_admin_override' => $request->user() && (
    in_array($request->user()->email, config('admin.override_emails', [])) ||
    $request->user()->hasRole('super-admin')
),
```

---

### 3. Tests Using admin.override (LOW IMPACT)

**What breaks:**
```php
// tests/Feature/BillingControllerTest.php:24
// tests/Feature/Admin/PricingControllerTest.php:19
$this->actingAs($user)->get('/admin/pricing');
```

**Why it breaks:**
- Tests rely on `ADMIN_OVERRIDE_EMAILS` or middleware
- Will fail with 403 if user doesn't have role

**Impact:**
- ❌ Test suite fails
- ⚠️ CI/CD pipeline might break

**Safe Migration:**
```php
// In tests, assign role explicitly
$user = User::factory()->create();
$user->assignRole('super-admin');
$this->actingAs($user)->get('/admin/pricing')->assertOk();
```

---

## 🟡 WILL NOT BREAK (But Needs Update)

### 4. User Model (NO IMPACT)

**Current state:**
```php
// User.php already has:
use HasRoles; // Line 32
```

**Impact:** ✅ None - already compatible

---

### 5. Database (NO IMPACT)

**Current state:**
- Spatie tables already exist (`roles`, `permissions`, `model_has_roles`, etc.)
- User table has no role column (uses pivot tables)

**Impact:** ✅ None - Spatie migrations already ran

---

### 6. Authentication (NO IMPACT)

**Current state:**
- WorkOS handles auth
- No role/permission sync needed

**Impact:** ✅ None - WorkOS and Spatie are independent

---

## 🟢 SAFE TO CHANGE (No Breaking)

### 7. Seeders

**Change:**
```php
// UserSeeder.php
$powerUser->assignRole('super-admin'); // Add this line
```

**Impact:** ✅ None - just assigns role

---

### 8. Feature Flags (New Addition)

**Change:**
```php
// AppServiceProvider.php
Feature::define('voucher-advanced-mode', ...);
```

**Impact:** ✅ None - new feature, doesn't affect existing code

---

## 📋 BREAKING CHANGES SUMMARY

| Component | Risk | Impact | Users Affected |
|-----------|------|--------|----------------|
| Admin Routes | 🔴 HIGH | 403 errors | lester@hurtado.ph |
| Frontend Props | 🟡 MEDIUM | Hidden menus | All admins |
| Tests | 🟡 MEDIUM | Test failures | CI/CD |
| Database | 🟢 NONE | - | - |
| Auth | 🟢 NONE | - | - |
| Seeders | 🟢 NONE | - | - |

---

## 🛡️ SAFE MIGRATION STRATEGY

### Phase 1: Preparation (NO BREAKING)

```bash
# 1. Install Pennant
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate

# 2. Update seeders (doesn't break anything)
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder

# 3. Verify roles assigned
php artisan tinker
>>> User::where('email', 'lester@hurtado.ph')->first()->roles->pluck('name')
=> ["super-admin"] // Should see this
```

### Phase 2: Add Dual Support (BACKWARD COMPATIBLE)

```php
// HandleInertiaRequests.php - Support BOTH
'is_admin_override' => $request->user() && (
    in_array($request->user()->email, config('admin.override_emails', [])) ||
    $request->user()->hasRole('super-admin')
),

// routes/web.php - Support BOTH
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/pricing', ...)->middleware(function ($request, $next) {
        // Allow if admin.override OR super-admin role
        if (in_array($request->user()->email, config('admin.override_emails', [])) ||
            $request->user()->hasRole('super-admin')) {
            return $next($request);
        }
        abort(403);
    });
});
```

### Phase 3: Test Thoroughly

```bash
# Test as lester@hurtado.ph
/dev-login/lester@hurtado.ph
# Visit: /admin/pricing (should work)
# Visit: /balances (should work)
# Visit: Generate Vouchers > Advanced Mode (should work)

# Test as admin@disburse.cash  
/dev-login/admin@disburse.cash
# Same tests

# Run test suite
php artisan test --filter Admin
```

### Phase 4: Cutover (BREAKING - Do Last)

```bash
# Remove ADMIN_OVERRIDE_EMAILS from .env
sed -i '/ADMIN_OVERRIDE_EMAILS/d' .env

# Update routes to use role:super-admin
# Update HandleInertiaRequests to only check roles
# Remove admin.override middleware alias

# Delete obsolete files
rm config/admin.php
rm app/Http/Middleware/AllowAdminOverride.php
```

---

## ⏪ ROLLBACK PLAN

If anything breaks:

```bash
# 1. Re-add to .env
echo "ADMIN_OVERRIDE_EMAILS=lester@hurtado.ph" >> .env

# 2. Revert routes
git checkout routes/web.php

# 3. Revert HandleInertiaRequests
git checkout app/Http/Middleware/HandleInertiaRequests.php

# 4. Roles remain in database (no data loss)
# Can continue using them alongside override emails
```

---

## ✅ RECOMMENDATION

**This refactor is SAFE if done incrementally:**

1. ✅ **Phase 1-2**: Zero risk, dual support
2. ⚠️ **Phase 3**: Test thoroughly before Phase 4
3. ❌ **Phase 4**: Only do this after 100% confidence

**Timeline:**
- Day 1: Phases 1-2 (prepare + dual support)
- Day 2-3: Phase 3 (testing)
- Day 4+: Phase 4 (cutover) - only if tests pass

**Abort if:**
- Tests fail in Phase 3
- Production environment different than local
- Multiple users affected (not just you and admin)

This approach gives you **safety net** at every step! 🎯
