# Authorization Fixes

## Issues Found During Testing

### Issue #1: `/balances` Route Not Protected ❌
**Problem**: Balance monitoring page was accessible without proper permission check.

**Root Cause**: 
- Route had no middleware protection in `routes/web.php` (line 181-182)
- BalanceController had inline authorization check, but it was checking for 'admin' role via config
- User had 'super-admin' role removed for testing, but config required 'admin' role

**Fix**:
- Added `->middleware('permission:view balance')` to `/balances` route
- Simplified BalanceController to rely on middleware authorization
- Now properly blocks access when user doesn't have 'view balance' permission

**Files Changed**:
- `routes/web.php`: Added middleware to line 182
- `app/Http/Controllers/Balances/BalanceController.php`: Removed inline auth check (lines 29-40)

---

### Issue #2: Advanced Mode Toggle Still Visible and Functional ❌
**Problem**: Users without `advanced-pricing-mode` feature flag could still switch to advanced mode.

**Root Cause**:
- Frontend wrapped UI elements with `v-if="hasAdvancedMode"` but didn't block the `switchMode()` function
- `useGenerateMode` composable had no permission check
- User could bypass UI restrictions by directly calling the switch function

**Fix**:
1. **Composable** (`useGenerateMode.ts`):
   - Added `usePage()` import
   - Added permission check in `switchMode()` function
   - Blocks mode switch if user lacks `advanced_pricing_mode` feature flag
   - Logs warning when blocked

2. **Component** (`CreateV2.vue`):
   - Simplified initial mode to always start as 'simple'
   - Removed conditional initialization logic
   - Composable now enforces permission at runtime

**Files Changed**:
- `resources/js/composables/useGenerateMode.ts`: Lines 3, 10, 13-19
- `resources/js/pages/vouchers/generate/CreateV2.vue`: Line 70

---

## Testing Verification

### Before Fixes:
1. ✅ Sidebar correctly hid Billings, Pricing, Balances, Preferences
2. ✅ Profile Settings correctly hid Feature Flags section
3. ✅ `/admin/pricing` correctly returned 403
4. ❌ `/balances` was accessible (should be 403)
5. ❌ Mode toggle visible in Generate Vouchers
6. ❌ Could switch to Advanced Mode with full access

### After Fixes (Expected):
1. ✅ Sidebar correctly hides restricted sections
2. ✅ Profile Settings hides Feature Flags
3. ✅ `/admin/pricing` returns 403
4. ✅ `/balances` returns 403 (now fixed)
5. ✅ Mode toggle hidden in Generate Vouchers (now fixed)
6. ✅ Cannot switch to Advanced Mode (now fixed)

---

## How to Test

### 1. Remove Super-Admin Role
```bash
php artisan user:toggle-role lester@hurtado.ph super-admin
```

### 2. Comment Out Admin Override
Edit `.env`:
```bash
#ADMIN_OVERRIDE_EMAILS=lester@hurtado.ph
```

### 3. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

### 4. Test Restrictions
- Visit `/balances` → Should see 403 error
- Visit `/vouchers/generate` → Should NOT see mode toggle
- Simple mode only, no "Switch to Advanced Mode" card
- Inspect browser console: Should see warning if trying to switch modes

### 5. Restore Super-Admin Role
```bash
php artisan user:toggle-role lester@hurtado.ph super-admin
```

### 6. Uncomment Admin Override (Optional)
Edit `.env`:
```bash
ADMIN_OVERRIDE_EMAILS=lester@hurtado.ph
```

---

## Architecture Notes

### Dual Authorization Pattern
Both middleware-based AND inline checks are supported:
- **Middleware**: `->middleware('permission:view balance')` (recommended)
- **Inline checks**: Controller-level validation (legacy, now removed from BalanceController)

### Feature Flag Architecture
- **Backend**: Laravel Pennant defines features in `AppServiceProvider`
- **Middleware**: `HandleInertiaRequests` shares feature flags with Vue
- **Frontend**: Components check `$page.props.auth.feature_flags.advanced_pricing_mode`
- **Composables**: Runtime validation blocks unauthorized actions

### Permission Hierarchy
1. **Permissions**: Fine-grained (e.g., 'view balance', 'manage pricing')
2. **Roles**: Collections of permissions (e.g., 'super-admin', 'power-user')
3. **Feature Flags**: Dynamic toggles (e.g., 'advanced-pricing-mode')
4. **Admin Override**: Legacy `.env` fallback for backward compatibility

---

## Related Documentation
- `docs/FEATURE_FLAGS.md` - Complete feature flag documentation
- `docs/VUE_AUTHORIZATION_USAGE.md` - Frontend authorization patterns
- `docs/MIGRATION_COMPLETE.md` - Pennant + Spatie migration guide
- `database/seeders/RolePermissionSeeder.php` - Role and permission definitions
