# Feature Enablement Strategy

Guide for managing per-user feature flags in production, particularly for settlement vouchers and other gated features.

## Table of Contents
- [Overview](#overview)
- [Available Methods](#available-methods)
- [Best Practices](#best-practices)
- [Usage Examples](#usage-examples)
- [Rollout Strategy](#rollout-strategy)
- [Troubleshooting](#troubleshooting)

---

## Overview

The application uses **Laravel Pennant** for feature flag management with a **three-tier evaluation system**:

1. **User-specific database records** (highest priority)
2. **Role-based defaults** (e.g., super-admin auto-enables)
3. **Environment defaults** (local/staging = enabled, production = disabled)

### Available Features
- `settlement-vouchers` - Pay-in voucher functionality
- `advanced-pricing-mode` - Advanced pricing features for power users
- `beta-features` - Experimental features (opt-in)

---

## Available Methods

### 1. **Artisan Commands** ⭐ (Recommended)

#### Check User's Feature Status
```bash
php artisan feature:list lester@hurtado.ph
```

Output:
```
Feature flags for: lester@hurtado.ph

+-----------------------+-----------------------+------------+
| Feature               | Key                   | Status     |
+-----------------------+-----------------------+------------+
| Settlement Vouchers   | settlement-vouchers   | ✓ ENABLED  |
| Advanced Pricing Mode | advanced-pricing-mode | ✓ ENABLED  |
| Beta Features         | beta-features         | ✗ DISABLED |
+-----------------------+-----------------------+------------+
```

#### Enable a Feature
```bash
php artisan feature:manage settlement-vouchers user@example.com --enable
```

#### Disable a Feature
```bash
php artisan feature:manage settlement-vouchers user@example.com --disable
```

#### Check Specific Feature Status
```bash
php artisan feature:manage settlement-vouchers user@example.com --status
```

**When to use:**
- Production support requests
- Quick enablement during onboarding
- Debugging feature access issues
- Scripted bulk operations

---

### 2. **Admin UI** (Recommended for Scale)

**Status:** Not yet implemented

**Proposed implementation:**
```
Settings > Users > [User Email] > Feature Access

☑ Settlement Vouchers - Enable pay-in voucher functionality
☐ Beta Features - Access to experimental features  
☑ Advanced Pricing Mode - Advanced pricing features
```

**Benefits:**
- Self-service for admin users
- Audit trail via model events
- No server access needed
- Real-time updates

**Implementation TODO:**
```bash
# Create Inertia page
resources/js/pages/settings/users/[id]/features.vue

# Create controller
app/Http/Controllers/Settings/UserFeatureController.php

# Add routes
routes/settings.php
```

---

### 3. **Environment Variable (Initial Setup)**

Set in `.env` for automatic activation during seeding:

```bash
# .env
SETTLEMENT_VOUCHERS_ENABLED_FOR=user1@example.com,user2@example.com,user3@example.com
```

Then run:
```bash
php artisan db:seed --class=UserSeeder
```

**When to use:**
- Initial production deployment
- Setting up pilot users
- Development/staging environments

**Limitations:**
- Requires re-seeding to update
- Not dynamic (server access needed)
- Best for initial setup only

---

### 4. **Role-Based Auto-Enable**

Modify `app/Providers/AppServiceProvider.php`:

```php
Feature::define('settlement-vouchers', function (User $user) {
    // Auto-enable for enterprise customers
    if ($user->hasAnyRole(['enterprise', 'premium'])) {
        return true;
    }
    
    // Auto-enable for pilot group
    if ($user->hasPermission('use settlement vouchers')) {
        return true;
    }
    
    // Environment defaults
    if (app()->environment('local', 'staging')) {
        return true;
    }
    
    return false;
});
```

**When to use:**
- Automated enablement based on subscription tier
- Enterprise/premium features
- Permission-based access control

---

### 5. **Manual (Emergency/Testing)**

```bash
php artisan tinker
>>> use App\Models\User;
>>> use Laravel\Pennant\Feature;
>>> $user = User::where('email', 'user@example.com')->first();
>>> Feature::for($user)->activate('settlement-vouchers');
>>> exit
```

**When to use:**
- Emergency production fixes
- One-off testing
- When commands aren't available

---

## Best Practices

### 1. **Gradual Rollout Pattern**

```bash
# Week 1: Internal testing (5 users)
php artisan feature:manage settlement-vouchers pilot1@company.com --enable
php artisan feature:manage settlement-vouchers pilot2@company.com --enable
# ... monitor for issues

# Week 2: Early adopters (25 users)
for email in $(cat early_adopters.txt); do
    php artisan feature:manage settlement-vouchers $email --enable
done

# Week 3: Broader release (100 users)
# Week 4: General availability (update AppServiceProvider default)
```

### 2. **Documentation Requirements**

**Before enabling for a user:**
- ✅ User has signed terms of service
- ✅ User completed training/onboarding
- ✅ User's account is verified (KYC if needed)
- ✅ Support ticket documenting request
- ✅ Feature requirements met (e.g., wallet balance > threshold)

### 3. **Audit Trail**

Log feature activations:

```php
// After activating
Log::info('Feature activated', [
    'feature' => 'settlement-vouchers',
    'user_email' => $user->email,
    'activated_by' => auth()->user()->email,
    'reason' => 'Customer request via ticket #12345',
]);
```

### 4. **Monitoring**

Track feature usage:

```php
// In SettlementVoucherController
if (Feature::active('settlement-vouchers')) {
    Event::dispatch(new FeatureUsed('settlement-vouchers', auth()->user()));
}
```

### 5. **Rollback Strategy**

Always have a quick rollback:

```bash
# Disable for single user
php artisan feature:manage settlement-vouchers user@example.com --disable

# Emergency: Disable for all (update AppServiceProvider)
Feature::define('settlement-vouchers', fn() => false);
php artisan config:clear
```

---

## Usage Examples

### Scenario 1: Customer Support Request

**Ticket:** "Customer wants access to settlement vouchers"

```bash
# 1. Verify user exists and is eligible
php artisan feature:list customer@example.com

# 2. Enable feature
php artisan feature:manage settlement-vouchers customer@example.com --enable

# 3. Verify
php artisan feature:manage settlement-vouchers customer@example.com --status

# 4. Notify customer
# 5. Update support ticket with command used
```

### Scenario 2: Bulk Enablement for Pilot Program

```bash
# Create list of pilot users
cat > pilot_users.txt << EOF
pilot1@company.com
pilot2@company.com
pilot3@company.com
EOF

# Enable for all
while read email; do
    php artisan feature:manage settlement-vouchers "$email" --enable
    echo "Enabled for: $email"
done < pilot_users.txt

# Verify count
php artisan tinker --execute="use Illuminate\Support\Facades\DB; echo DB::table('features')->where('name', 'settlement-vouchers')->where('value', 'true')->count();"
```

### Scenario 3: Disable for Misbehaving User

```bash
# Investigate
php artisan feature:list suspicious@example.com

# Disable settlement vouchers
php artisan feature:manage settlement-vouchers suspicious@example.com --disable

# Verify
php artisan feature:manage settlement-vouchers suspicious@example.com --status
```

---

## Rollout Strategy

### Phase 1: Internal Testing (Week 1-2)
- **Scope:** 5-10 internal users
- **Method:** Artisan command
- **Goal:** Identify bugs, validate workflows

```bash
php artisan feature:manage settlement-vouchers admin@disburse.cash --enable
php artisan feature:manage settlement-vouchers lester@hurtado.ph --enable
```

### Phase 2: Pilot Program (Week 3-4)
- **Scope:** 25-50 friendly customers
- **Method:** Artisan command + tracking
- **Goal:** Validate UX, gather feedback

```bash
# Enable via script
./scripts/enable-pilot-users.sh
```

### Phase 3: Early Adopters (Month 2)
- **Scope:** 100-500 users (opt-in)
- **Method:** Admin UI (implement first)
- **Goal:** Scale validation

### Phase 4: Controlled Rollout (Month 3)
- **Scope:** 10% → 25% → 50% → 100%
- **Method:** Role-based or feature flag percentage
- **Goal:** Gradual migration

```php
// AppServiceProvider
Feature::define('settlement-vouchers', function (User $user) {
    // Rollout to 50% of users based on ID
    if ($user->id % 2 === 0) {
        return true;
    }
    return false;
});
```

### Phase 5: General Availability (Month 4)
- **Scope:** All users
- **Method:** Update AppServiceProvider default

```php
Feature::define('settlement-vouchers', fn(User $user) => true);
```

---

## Troubleshooting

### User says feature is not showing, but it should be enabled

**Check priority hierarchy:**

```bash
# 1. Check database record (highest priority)
php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
use App\Models\User;
\$user = User::where('email', 'user@example.com')->first();
\$record = DB::table('features')
    ->where('name', 'settlement-vouchers')
    ->where('scope', 'App\\\\Models\\\\User|' . \$user->id)
    ->first();
var_dump(\$record);
"

# 2. Check feature status
php artisan feature:manage settlement-vouchers user@example.com --status

# 3. Check roles (might be disabled for role)
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'user@example.com')->first();
echo 'Roles: ' . \$user->roles->pluck('name')->join(', ');
"
```

**Common issues:**
- Database record set to `false` (overrides environment)
- Role doesn't have access in AppServiceProvider
- Frontend caching (clear browser cache)
- Config cache (`php artisan config:clear`)

### Feature enabled but UI still shows "disabled"

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Rebuild frontend
npm run build

# Check Inertia shared data
php artisan tinker --execute="
use App\Http\Middleware\HandleInertiaRequests;
echo 'Settlement enabled: ' . (app(HandleInertiaRequests::class)->share(request())['settlement_enabled'] ? 'YES' : 'NO');
"
```

---

## Environment-Specific Behavior

### Local/Staging
- Default: **Enabled** for all users (AppServiceProvider)
- Database overrides still work
- Use for testing disabled state: `php artisan feature:manage {feature} {email} --disable`

### Production
- Default: **Disabled** for all users
- Must explicitly enable via:
  1. Artisan command, OR
  2. Admin UI, OR
  3. Seeder with env variable

---

## Database Schema

Feature flags stored in `features` table:

```sql
SELECT * FROM features WHERE name = 'settlement-vouchers';

-- Default (scope = __laravel_null)
| id | name                  | scope            | value | created_at          |
|----|-----------------------|------------------|-------|---------------------|
| 1  | settlement-vouchers   | __laravel_null   | false | 2026-01-08 09:05:31 |

-- User-specific (scope = App\Models\User|{id})
| id | name                  | scope               | value | created_at          |
|----|-----------------------|---------------------|-------|---------------------|
| 2  | settlement-vouchers   | App\Models\User|2   | true  | 2026-01-08 12:19:28 |
```

**Important:** User-specific records (scope with User ID) take precedence over default.

---

## Next Steps

1. **Implement Admin UI** (Phase 2)
   - Create Inertia page for feature management
   - Add to Settings menu
   - Implement toggle switches with confirmation

2. **Add Audit Logging** (Phase 3)
   - Track who enabled/disabled features
   - Store in `activity_log` table
   - Dashboard for admins

3. **Create Analytics Dashboard** (Phase 4)
   - Track feature adoption rate
   - Monitor feature usage
   - Identify power users

4. **Automated Rollout** (Phase 5)
   - Percentage-based rollouts
   - A/B testing framework
   - Automatic graduation from pilot to GA

---

## Related Documentation
- `docs/SETTLEMENT_TESTING_GUIDE.md` - Testing settlement vouchers
- `app/Providers/AppServiceProvider.php` - Feature definitions
- `config/features.php` - Feature configuration (if created)
