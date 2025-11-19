# Preferences → Admin Rationalization Plan

## Problem
**Preferences** (`/settings/preferences`) are currently in the user Settings sidebar, but they are **global settings** that affect all users' voucher generation defaults. This is misleading because:

1. They're not user-specific preferences
2. All users would see and could modify the same global defaults
3. No permission/role checks exist
4. They belong in an admin-only area

## Proposed Solution

### Move Preferences to Admin Section

**Before:**
```
Settings Sidebar (Per-User):
- Profile          ← User-specific ✅
- Appearance       ← User-specific ✅
- Preferences      ← GLOBAL ❌ (wrong location)
- Campaigns        ← User-specific ✅

Main Sidebar:
- Billing          ← Admin-only, but shown to everyone ❌
```

**After:**
```
Settings Sidebar (Per-User):
- Profile
- Appearance
- Campaigns

Main Sidebar (Admin Section):
- Dashboard
- Vouchers
- Wallet
- Transactions
- Contacts
─────────────────
Admin (conditional):
- Billing          ← role:super-admin + permission:view all billing
- Pricing          ← role:super-admin + permission:manage pricing
- Preferences      ← role:super-admin + permission:manage preferences (NEW)
```

## Implementation Steps

### 1. Create Admin Preferences Permission

**Add to RolePermissionSeeder:**
```php
Permission::create(['name' => 'manage preferences']);

// Assign to super-admin role
$superAdmin->givePermissionTo('manage preferences');
```

### 2. Move PreferencesController to Admin namespace

**From:** `App\Http\Controllers\Settings\PreferencesController`  
**To:** `App\Http\Controllers\Admin\PreferencesController`

Update namespace and add defensive initialization.

### 3. Move Preferences page to admin directory

**From:** `resources/js/pages/settings/Preferences.vue`  
**To:** `resources/js/pages/admin/preferences/Index.vue`

Update imports and breadcrumbs.

### 4. Update Routes

**Remove from `routes/settings.php`:**
```php
// DELETE THESE:
Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
```

**Add to `routes/web.php` in admin section:**
```php
Route::prefix('admin')
    ->name('admin.')
    ->middleware('role:super-admin')
    ->group(function () {
        // ... existing admin routes ...
        
        // Voucher generation preferences (global defaults)
        Route::prefix('preferences')
            ->name('preferences.')
            ->middleware('permission:manage preferences')
            ->group(function () {
                Route::get('/', [Admin\PreferencesController::class, 'index'])
                    ->name('index');
                Route::patch('/', [Admin\PreferencesController::class, 'update'])
                    ->name('update');
            });
    });
```

### 5. Update Main Sidebar Navigation

**AppSidebar.vue:**
```typescript
// Compute admin items based on user permissions
const user = computed(() => page.props.auth.user);
const isSuperAdmin = computed(() => user.value?.roles?.includes('super-admin'));

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Vouchers', href: vouchersIndex.url(), icon: Ticket },
        { title: 'Wallet', href: '/wallet/load', icon: Wallet },
        { title: 'Transactions', href: transactionsIndex.url(), icon: Receipt },
        { title: 'Contacts', href: contactsIndex.url(), icon: Users },
    ];

    // Add admin section
    if (isSuperAdmin.value) {
        const permissions = user.value?.permissions || [];
        
        // Admin separator or group header
        items.push({ type: 'separator' }); // or groupHeader: 'Admin'
        
        if (permissions.includes('view all billing')) {
            items.push({ title: 'Billing', href: '/admin/billing', icon: CreditCard });
        }
        
        if (permissions.includes('manage pricing')) {
            items.push({ title: 'Pricing', href: '/admin/pricing', icon: DollarSign });
        }
        
        if (permissions.includes('manage preferences')) {
            items.push({ title: 'Preferences', href: '/admin/preferences', icon: Settings });
        }
    }

    return items;
});
```

### 6. Update Settings Sidebar

**Remove Preferences from `resources/js/layouts/settings/Layout.vue`:**
```typescript
const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Appearance', href: editAppearance() },
    { title: 'Campaigns', href: campaignsIndex.url() },
    // Preferences removed - now in admin section
];
```

### 7. Add Separator/Group in Sidebar Component

**Option A: Add visual separator**
```vue
<!-- After main nav items, before admin items -->
<SidebarSeparator v-if="hasAdminItems" />
<SidebarGroup v-if="hasAdminItems">
    <SidebarGroupLabel>Admin</SidebarGroupLabel>
    <NavMain :items="adminNavItems" />
</SidebarGroup>
```

**Option B: Use existing NavMain with grouped items**
```typescript
const footerNavItems = [
    { title: 'Redeem', href: redeemStart.url(), icon: TicketX },
    { title: 'Help', href: '/help', icon: HelpCircle },
];

// Admin items go in main nav with visual grouping via CSS
```

## Route Naming Convention

**Before:**
- `/settings/preferences` → `preferences.edit` (user context)

**After:**
- `/admin/preferences` → `admin.preferences.index` (admin context)

Follows existing pattern:
- `/admin/billing` → `admin.billing.index`
- `/admin/pricing` → `admin.pricing.index`

## Permission Matrix

| Route | Role | Permission | Description |
|-------|------|------------|-------------|
| `/admin/billing` | super-admin | view all billing | View all users' billing history |
| `/admin/pricing` | super-admin | manage pricing | Manage instruction item pricing |
| `/admin/preferences` | super-admin | manage preferences | Manage global voucher defaults |

## UI/UX Considerations

### Admin Section Visual Treatment

1. **Group Header**: Add "Admin" label above admin items
2. **Icon Differentiation**: Use Shield or Lock icon for admin items
3. **Color Coding**: Subtle color difference (muted or accent)
4. **Tooltip**: Show permission required on hover

### Navigation Structure

```
┌─ MAIN NAVIGATION ─────────────┐
│ Dashboard                      │
│ Vouchers                       │
│ Wallet                         │
│ Transactions                   │
│ Contacts                       │
├────────────────────────────────┤
│ ADMIN                          │
│ Billing         🔒             │
│ Pricing         🔒             │
│ Preferences     🔒             │
└────────────────────────────────┘
```

### Breadcrumbs Update

**Before:**
```
Settings > Preferences
```

**After:**
```
Admin > Preferences
```

## Migration Notes

### For Existing Users

1. Route `/settings/preferences` should redirect to `/admin/preferences`
2. Only super-admins with `manage preferences` permission can access
3. Regular users lose access (correct behavior - they shouldn't have had it)

### Communication

**Release Notes:**
> **Breaking Change:** Voucher Preferences are now admin-only
> 
> The "Preferences" page (voucher generation defaults) has been moved from user Settings to the Admin section. These are global settings that affect all users, so only administrators can now modify them.
> 
> - **For Admins:** Find Preferences under Admin → Preferences
> - **For Users:** Voucher defaults are set by your administrator
> 
> If you need access, contact your administrator to grant the "manage preferences" permission.

## Benefits

1. ✅ **Correct Permissions**: Only admins can modify global settings
2. ✅ **Clear Separation**: User settings vs system settings
3. ✅ **Consistent Pattern**: Follows Billing/Pricing admin pattern
4. ✅ **Better UX**: Users won't be confused by global settings in personal area
5. ✅ **Scalable**: Easy to add more admin settings later

## Implementation Order

1. ✅ Create permission in seeder
2. ✅ Move controller to Admin namespace
3. ✅ Move page to admin directory
4. ✅ Update routes (add admin route, remove settings route)
5. ✅ Update main sidebar (add admin section)
6. ✅ Update settings sidebar (remove preferences)
7. ✅ Test with super-admin user
8. ✅ Test with regular user (should not see admin items)
9. ✅ Update documentation
