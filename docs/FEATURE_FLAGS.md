# Feature Flags with Laravel Pennant

Complete guide to using and managing feature flags in this application.

---

## Overview

Feature flags allow you to:
- Enable/disable features for specific users
- Roll out features gradually
- Test features in production safely
- A/B test different approaches
- Give beta access to selected users

---

## Configuration Layers

### 1. Feature Definition (Code)

**Location:** `app/Providers/AppServiceProvider.php`

Define what features exist and their default behavior:

```php
Feature::define('feature-name', function (User $user) {
    // Return true to enable, false to disable
    return $user->hasRole('super-admin');
});
```

### 2. User Overrides (Database)

**Location:** `features` table

Per-user overrides are stored automatically when you activate/deactivate:

```php
Feature::for($user)->activate('feature-name');   // Override to true
Feature::for($user)->deactivate('feature-name'); // Override to false
```

### 3. Feature Check (Runtime)

**Usage:** Controllers, Views, Vue components

```php
// Backend
if (Feature::for($user)->active('feature-name')) {
    // Feature-specific code
}

// Frontend (Vue)
if ($page.props.auth.feature_flags.feature_name) {
    // Show feature UI
}
```

---

## Available Features

### `advanced-pricing-mode`

**Purpose:** Show advanced pricing options in voucher generation

**Default Logic:**
```php
return $user->hasAnyRole(['super-admin', 'power-user']);
```

**Who Gets It:**
- ✅ Super-admins (automatic)
- ✅ Power-users (automatic)
- ✅ Anyone manually activated

**Usage:**
- Voucher generation page switches between Simple/Advanced mode
- Advanced mode shows: time validation, location validation, complex pricing

**UI Impact:**
- Simple Mode: 3-5 essential fields
- Advanced Mode: All fields with collapsible cards

---

### `beta-features`

**Purpose:** Access to experimental/beta functionality

**Default Logic:**
```php
return false; // Disabled by default
```

**Who Gets It:**
- ❌ Nobody by default
- ✅ Manually activated per user

**Usage:**
- Future beta features
- Experimental UI components
- New integrations before general release

**UI Impact:**
- Shows "Beta" badges on experimental features
- Access to settings/preferences marked as beta

---

## Managing Feature Flags

### Via UI (Super Admin Only)

**Location:** Settings → Profile → Feature Flags

Super-admins can:
- ✅ View all available features
- ✅ See current status (active/inactive)
- ✅ Toggle features on/off
- ✅ See feature descriptions

**How it works:**
1. Navigate to Settings → Profile
2. Scroll to "Feature Flags" section
3. Toggle features on/off
4. Changes apply immediately (no logout required)

---

### Via Tinker (Developers)

```bash
php artisan tinker
```

#### Check Feature Status
```php
use Laravel\Pennant\Feature;
use App\Models\User;

$user = User::where('email', 'lester@hurtado.ph')->first();

// Check single feature
Feature::for($user)->active('advanced-pricing-mode'); // → true/false

// Check all features
Feature::for($user)->all();
// → [
//     'advanced-pricing-mode' => true,
//     'beta-features' => false,
//   ]
```

#### Activate/Deactivate Features
```php
// Activate
Feature::for($user)->activate('beta-features');

// Deactivate
Feature::for($user)->deactivate('beta-features');

// Bulk operations
Feature::for($user)->activate(['feature-1', 'feature-2']);
```

#### Check Database
```php
DB::table('features')->where('scope', 'App\\Models\\User|' . $user->id)->get();
```

---

### Via Artisan Command (Future)

Coming soon: `php artisan features:toggle {user} {feature} {on|off}`

---

## Adding New Features

### Step 1: Define Feature

**File:** `app/Providers/AppServiceProvider.php`

```php
Feature::define('new-feature', function (User $user) {
    // Your logic here
    return $user->hasRole('beta-tester');
});
```

**Common Patterns:**

```php
// Role-based
return $user->hasRole('admin');

// Permission-based
return $user->hasPermissionTo('manage billing');

// Multiple roles
return $user->hasAnyRole(['super-admin', 'manager']);

// Combination
return $user->hasRole('admin') || $user->created_at->gt(now()->subMonths(3));

// Percentage rollout (10% of users)
return crc32($user->id) % 100 < 10;

// Date-based
return now()->isAfter('2026-02-01');

// Always disabled (manual activation only)
return false;
```

### Step 2: Share with Frontend

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

```php
$featureFlags = [
    'advanced_pricing_mode' => Feature::for($request->user())->active('advanced-pricing-mode'),
    'beta_features' => Feature::for($request->user())->active('beta-features'),
    'new_feature' => Feature::for($request->user())->active('new-feature'), // ← Add this
];
```

### Step 3: Update TypeScript Types

**File:** `resources/js/types/index.d.ts`

```typescript
export interface Auth {
    // ...
    feature_flags: {
        advanced_pricing_mode: boolean;
        beta_features: boolean;
        new_feature: boolean; // ← Add this
    };
}
```

### Step 4: Add to UI Toggle List

**File:** `app/Http/Controllers/Settings/ProfileController.php`

```php
'available_features' => [
    [
        'key' => 'advanced-pricing-mode',
        'name' => 'Advanced Pricing Mode',
        'description' => 'Show advanced pricing options in voucher generation',
        'locked' => true, // Can't be disabled (role-based)
    ],
    [
        'key' => 'beta-features',
        'name' => 'Beta Features',
        'description' => 'Access experimental features before public release',
        'locked' => false, // Can be toggled
    ],
    [
        'key' => 'new-feature',
        'name' => 'New Feature Name',
        'description' => 'Description of what this feature does',
        'locked' => false,
    ],
];
```

### Step 5: Document It Here

Add to **Available Features** section above.

---

## Usage Examples

### Backend Controller

```php
use Laravel\Pennant\Feature;

public function export(Request $request)
{
    // Guard feature
    if (!Feature::for($request->user())->active('bulk-export')) {
        abort(403, 'Bulk export feature not available.');
    }
    
    // Feature code
    return Excel::download(new ExportData, 'data.xlsx');
}
```

### Vue Component

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const hasBetaFeatures = computed(() => 
  page.props.auth?.feature_flags?.beta_features || false
);
</script>

<template>
  <div v-if="hasBetaFeatures" class="beta-banner">
    🧪 Beta Feature - Send us feedback!
  </div>
  
  <button v-if="hasBetaFeatures" @click="newFeature">
    Try New Feature
  </button>
</template>
```

### Blade Template (if needed)

```blade
@feature('advanced-pricing-mode')
    <div>Advanced options available!</div>
@endfeature
```

---

## Best Practices

### 1. Name Features Clearly
✅ **Good:** `advanced-pricing-mode`, `bulk-export`, `beta-checkout`  
❌ **Bad:** `feature1`, `new-thing`, `test`

### 2. Default to Safe Behavior
```php
// Default disabled for experimental features
return false;

// Default enabled only for trusted roles
return $user->hasRole('super-admin');
```

### 3. Use Descriptive Logic
```php
// ✅ Clear intent
return $user->hasRole('beta-tester') || $user->created_at->gt(now()->subWeek());

// ❌ Confusing
return ($user->id % 5 === 0) && !in_array($user->email, ['test@example.com']);
```

### 4. Document Feature Purpose
Always add to this documentation when creating a new feature.

### 5. Clean Up Old Features
When a feature is fully rolled out, remove the flag:
1. Remove `Feature::define()` from AppServiceProvider
2. Remove conditional checks from code
3. Remove from frontend types
4. Delete database entries: `DB::table('features')->where('name', 'old-feature')->delete()`

### 6. Test Both States
Write tests for feature enabled AND disabled:

```php
test('bulk export works when feature enabled', function () {
    Feature::for($user)->activate('bulk-export');
    // ... test export
});

test('bulk export blocked when feature disabled', function () {
    Feature::for($user)->deactivate('bulk-export');
    // ... expect 403
});
```

---

## Troubleshooting

### Feature Not Showing in Frontend

**Check:**
1. ✅ Defined in `AppServiceProvider`?
2. ✅ Added to `HandleInertiaRequests` shared data?
3. ✅ Added to TypeScript `Auth` interface?
4. ✅ Browser cache cleared? (`Ctrl+Shift+R`)

### Feature Always Active/Inactive

**Check:**
1. Database override: `DB::table('features')->where('name', 'feature-name')->get()`
2. Default logic: `Feature::define()` in AppServiceProvider
3. User has correct role/permission?

### Changes Not Applying

**Solution:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild assets
npm run build
```

---

## Database Schema

```sql
CREATE TABLE features (
    name VARCHAR(255),              -- Feature name (e.g., 'beta-features')
    scope VARCHAR(255),             -- User scope (e.g., 'App\Models\User|1')
    value TEXT,                     -- 'true' or 'false'
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(name, scope)
);
```

**Query Examples:**

```sql
-- All features for a user
SELECT * FROM features WHERE scope = 'App\\Models\\User|1';

-- All users with a feature enabled
SELECT scope FROM features WHERE name = 'beta-features' AND value = 'true';

-- Feature usage stats
SELECT name, COUNT(*) as active_users 
FROM features 
WHERE value = 'true' 
GROUP BY name;
```

---

## API Reference

### Feature Facade

```php
use Laravel\Pennant\Feature;

// Check if active
Feature::for($user)->active('feature-name');        // → bool
Feature::for($user)->inactive('feature-name');      // → bool
Feature::for($user)->value('feature-name');         // → mixed

// Get all features
Feature::for($user)->all();                         // → array

// Activate/Deactivate
Feature::for($user)->activate('feature-name');
Feature::for($user)->deactivate('feature-name');

// Bulk operations
Feature::for($user)->activate(['f1', 'f2']);
Feature::for($user)->deactivate(['f1', 'f2']);

// Forget (remove override)
Feature::for($user)->forget('feature-name');

// Global scope
Feature::active('feature-name');                    // Current user
```

---

## Migration Guide

### From Old Feature System

If you had a custom feature flag system:

1. **Identify existing flags** in your code
2. **Define in Pennant**:
   ```php
   Feature::define('old-flag', fn($user) => $user->settings['old_flag'] ?? false);
   ```
3. **Migrate data**:
   ```php
   User::all()->each(function ($user) {
       if ($user->settings['old_flag']) {
           Feature::for($user)->activate('old-flag');
       }
   });
   ```
4. **Update code** to use `Feature::for($user)->active('old-flag')`
5. **Remove old system** after testing

---

## Security Considerations

1. **Never trust frontend** - Always check features on backend
2. **Sensitive features** - Use permission-based logic
3. **Audit access** - Log when features are toggled
4. **Super-admin only** - Feature management UI restricted to super-admins

---

## Performance

- ✅ **Cached:** Feature checks are cached per request
- ✅ **Efficient:** Database queries minimized via eager loading
- ✅ **Scalable:** Works with millions of users

**Optimization:**

```php
// Eager load all features at once
$features = Feature::for($user)->all(); // Single query

// Then check without additional queries
if ($features['beta-features']) {
    // ...
}
```

---

## Future Enhancements

- [ ] Scheduled feature activation/deactivation
- [ ] Feature analytics dashboard
- [ ] Percentage-based rollouts (10% → 50% → 100%)
- [ ] A/B testing framework
- [ ] Feature flag audit log
- [ ] API for external feature management
- [ ] Multi-tenancy support

---

## Resources

- [Laravel Pennant Docs](https://laravel.com/docs/11.x/pennant)
- [Feature Flags Best Practices](https://martinfowler.com/articles/feature-toggles.html)
- Internal: `VUE_AUTHORIZATION_USAGE.md` - Frontend usage
- Internal: `AUTHORIZATION_STRATEGY.md` - Role integration

---

## Questions?

- **Developers:** Check `VUE_AUTHORIZATION_USAGE.md` for frontend examples
- **Admins:** Use Settings → Profile → Feature Flags UI
- **Issues:** Create ticket with feature name and expected/actual behavior
