# Clickable Balance Navigation Implementation

## Overview

The sidebar wallet balance display (NavBalance component) is now **clickable** and navigates to the Balance Monitoring page (`/balances`) when clicked - **but only for users with the required role/permission**.

## How It Works

### Permission-Based Navigation

1. **User clicks on balance** in sidebar
2. **System checks**:
   - Is balance viewing enabled? (`BALANCE_VIEW_ENABLED`)
   - What role is required? (`BALANCE_VIEW_ROLE`)
   - Does user have that role?
3. **If authorized**: Navigate to `/balances` page
4. **If not authorized**: Nothing happens (static display)

### Visual Feedback

**Users WITH permission:**
- ✅ Hover effect (background highlight)
- ✅ Pointer cursor
- ✅ Smooth transition animation
- ✅ Click navigates to Balance Monitoring page

**Users WITHOUT permission:**
- ❌ No hover effects
- ❌ Default cursor
- ❌ Static display only
- ❌ Click does nothing

## Implementation Details

### Files Modified

1. **`app/Http/Middleware/HandleInertiaRequests.php`**
   - Added `auth.roles` to shared props
   - Added `balance.view_enabled` to shared props
   - Added `balance.view_role` to shared props

2. **`resources/js/components/NavBalance.vue`**
   - Added role checking logic
   - Added click handler for navigation
   - Added conditional CSS classes for hover/cursor
   - Prevented refresh button from triggering navigation

3. **`docs/SIDEBAR_BALANCE.md`**
   - Documented clickable navigation feature
   - Added permission check details
   - Added usage examples

### Code Highlights

**Role Check Logic:**
```typescript
const canViewBalancePage = computed(() => {
    if (!balanceViewEnabled.value) return false;
    // If no role required (empty string or null), allow all users
    if (!balanceViewRole.value) return true;
    return userRoles.value.includes(balanceViewRole.value);
});
```

**Click Handler:**
```typescript
const handleClick = () => {
    if (canViewBalancePage.value) {
        router.visit('/balances');
    }
};
```

**Conditional Styling:**
```vue
<div
    :class="{
        'cursor-pointer transition-colors hover:bg-accent/50': canViewBalancePage,
        'cursor-default': !canViewBalancePage,
    }"
    @click="handleClick"
>
```

## Configuration

### Environment Variables

The clickable navigation behavior is controlled by the same variables that protect the Balance Monitoring page:

```bash
# Enable/disable balance viewing globally
BALANCE_VIEW_ENABLED=true

# Required role to view balance page (default: admin)
# Set to empty string or omit to allow all authenticated users
BALANCE_VIEW_ROLE=admin
```

### Configuration Examples

#### Admin Only (Default)
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=admin
```
- Only users with `admin` role can click balance
- Regular users see balance but cannot navigate

#### All Authenticated Users
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=
```
- Any logged-in user can click balance
- Navigates to `/balances` for everyone

#### Super Admin Only
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=super-admin
```
- Only users with `super-admin` role can click
- Most restrictive setting

#### Static Display (Navigation Disabled)
```bash
BALANCE_VIEW_ENABLED=false
```
- Balance shows in sidebar
- No user can navigate (not clickable)
- Even direct access to `/balances` is blocked

## Backend Protection

The Balance Monitoring page (`/balances`) is **already protected** by the same role check in `BalancePageController`:

```php
// Check if balance viewing is enabled
if (!config('balance.view_enabled', true)) {
    abort(403, 'Balance viewing is currently disabled.');
}

// Check role-based access
$requiredRole = config('balance.view_role', 'admin');

if ($requiredRole && !auth()->user()->hasRole($requiredRole)) {
    abort(403, 'You do not have permission to view balance information.');
}
```

This ensures that even if a user somehow bypasses the frontend check, they still cannot access the page.

## User Experience Flow

### Admin User Flow
1. Admin logs in
2. Sees balance in sidebar with hover effect
3. Clicks balance → navigates to `/balances`
4. Views detailed balance monitoring page

### Regular User Flow (No Permission)
1. Regular user logs in
2. Sees balance in sidebar (static display)
3. No hover effect, default cursor
4. Click does nothing
5. If they try to access `/balances` directly → 403 error

## Testing Scenarios

### Test 1: Admin User
```bash
# Set in .env
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=admin
```

**Steps:**
1. Assign admin role to user: `$user->assignRole('admin')`
2. Login as that user
3. Hover over balance → should see highlight
4. Click balance → should navigate to `/balances`

**Expected:** ✅ Navigation works

### Test 2: Regular User
```bash
# Same config as Test 1
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=admin
```

**Steps:**
1. Login as user without admin role
2. Hover over balance → no highlight
3. Click balance → nothing happens
4. Try to access `/balances` directly → 403 error

**Expected:** ❌ Navigation blocked, page protected

### Test 3: All Users Allowed
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=
```

**Steps:**
1. Login as any user (with or without roles)
2. Hover over balance → should see highlight
3. Click balance → should navigate to `/balances`

**Expected:** ✅ Navigation works for everyone

### Test 4: Viewing Disabled
```bash
BALANCE_VIEW_ENABLED=false
```

**Steps:**
1. Login as any user (including admin)
2. Hover over balance → no highlight
3. Click balance → nothing happens
4. Try to access `/balances` directly → 403 error

**Expected:** ❌ Navigation blocked for everyone

### Test 5: Refresh Button (If Enabled)
```bash
SIDEBAR_BALANCE_SHOW_REFRESH=true
```

**Steps:**
1. Login as admin user
2. Click refresh button on balance
3. Balance should refresh (API call)
4. Should NOT navigate to `/balances`

**Expected:** ✅ Refresh works, navigation prevented

## Assigning Roles

To test with different roles, use Laravel Tinker:

```bash
php artisan tinker
```

```php
// Find user
$user = App\Models\User::where('email', 'user@example.com')->first();

// Assign admin role
$user->assignRole('admin');

// Remove role
$user->removeRole('admin');

// Check if user has role
$user->hasRole('admin'); // true or false

// Create role if doesn't exist
Spatie\Permission\Models\Role::create(['name' => 'admin']);
```

## Troubleshooting

### Balance not clickable for admin
**Problem:** Admin user cannot click balance  
**Check:**
1. Is `BALANCE_VIEW_ENABLED=true`?
2. Is `BALANCE_VIEW_ROLE=admin`?
3. Does user actually have admin role? Check: `$user->hasRole('admin')`
4. Clear config cache: `php artisan config:clear`

### Balance clickable for everyone
**Problem:** All users can click balance  
**Check:**
1. Is `BALANCE_VIEW_ROLE` empty or missing?
2. Set to specific role: `BALANCE_VIEW_ROLE=admin`
3. Clear config cache: `php artisan config:clear`

### Click navigates but shows 403
**Problem:** User can click but gets error on `/balances` page  
**This should not happen** - frontend and backend use same config. If it does:
1. Check if user's roles were changed after page load
2. Refresh the page to reload props
3. Check Laravel logs for actual error

### Refresh button triggers navigation
**Problem:** Clicking refresh navigates to `/balances`  
**This should not happen** - we added `@click.stop` to prevent propagation. If it does:
1. Check if NavBalance component was updated correctly
2. Verify `@click.stop` is on Button component
3. Clear browser cache and rebuild: `npm run build`

## Related Documentation

- **Balance Page Access**: `docs/BALANCE_PAGE_ACCESS.md`
- **Sidebar Balance**: `docs/SIDEBAR_BALANCE.md`
- **Balance Monitoring**: `docs/BALANCE_MONITORING_PHASE3_COMPLETE.md`
- **System Wallet**: `docs/SYSTEM_WALLET_ARCHITECTURE.md`

## Security Notes

✅ **Double Protection**: Both frontend (hide navigation) and backend (route protection) check permissions  
✅ **Role-Based**: Uses Spatie Permission package for robust role management  
✅ **Configurable**: Easy to change via environment variables  
✅ **Auditable**: All access attempts can be logged in controller  

## Future Enhancements

Potential improvements:
- Add permission-based access (instead of just role)
- Add tooltip explaining why balance is not clickable
- Add visual indicator (e.g., lock icon) for non-clickable balance
- Add click tracking/analytics
- Add confirmation dialog before navigation
- Support multiple roles (comma-separated in config)
