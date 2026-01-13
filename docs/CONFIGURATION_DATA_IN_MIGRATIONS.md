# Configuration Data in Migrations

## Overview

This project uses **migrations for configuration data** rather than seeders. This is a deliberate architectural decision for managing required application settings like `VoucherSettings`.

## Why Migrations for Configuration?

### Traditional Approach (We Don't Use This)
```php
// ❌ Don't do this for required settings
// Migrations = Schema only
Schema::create('settings', ...);

// Seeders = All data
DB::table('settings')->insert([...]);
```

**Problems with this approach:**
- Seeders are never run in production (too risky)
- `migrate:fresh --seed` during development can miss settings if seeder logic is flawed
- No guaranteed execution order
- Settings become disconnected from schema evolution

### Our Approach (✅ Use This)
```php
// ✅ Do this for required settings
// Migration = Schema + Required Data
return new class extends Migration {
    public function up(): void {
        DB::table('settings')->insertOrIgnore([
            'group' => 'voucher',
            'name' => 'default_amount',
            'payload' => json_encode(50),
            'locked' => false,
        ]);
    }
    
    public function down(): void {
        DB::table('settings')
            ->where('name', 'default_amount')
            ->delete();
    }
};
```

## When to Use Each Approach

### Use Migrations for Data When:
✅ **Required configuration** - App crashes without it (e.g., `VoucherSettings`)  
✅ **Reference/lookup tables** - Countries, currencies, payment rails  
✅ **System records** - Default admin role, system wallets  
✅ **Initial permissions/roles** - Core RBAC setup  
✅ **Feature flags** - Default feature states  

### Use Seeders for Data When:
✅ **Test/demo data** - Sample users, vouchers, transactions  
✅ **Local development convenience** - Quick setup with realistic data  
✅ **Optional content** - Example campaigns, templates  

## Benefits

### 1. Production Safety
```bash
# Production deployment - settings are guaranteed
php artisan migrate  # ✅ Always safe, always run

# vs

php artisan db:seed  # ❌ NEVER run in production
```

### 2. Guaranteed Execution
```php
// Migration 001: Create settings table
Schema::create('settings', ...);

// Migration 002: Add setting A
DB::table('settings')->insertOrIgnore(['name' => 'A', ...]);

// Migration 003: Add setting B
DB::table('settings')->insertOrIgnore(['name' => 'B', ...]);
```
Runs once, in order, automatically. No manual intervention.

### 3. Team Coordination
```bash
# Developer A adds new setting via migration
git commit -m "Add new_feature_enabled setting"
git push

# Developer B pulls code
git pull
php artisan migrate  # ✅ Automatically gets new setting
```

No need to remember to run seeders or sync database state.

### 4. Rollback Support
```php
public function down(): void {
    DB::table('settings')
        ->where('name', 'new_setting')
        ->delete();
}
```
Can undo settings changes if deployment fails.

### 5. Idempotency
```php
// Safe - won't error if already exists
DB::table('settings')->insertOrIgnore([...]);

// Or check first
if (!DB::table('settings')->where('name', 'X')->exists()) {
    DB::table('settings')->insert([...]);
}
```

## Real-World Example: VoucherSettings

### Before (❌ Problematic)
```php
// VoucherSettingsSeeder.php
public function run(): void {
    $existingSettings = DB::table('settings')
        ->where('group', 'voucher')
        ->count();
    
    if ($existingSettings > 0) {
        return; // ❌ Skips if ANY settings exist
    }
    
    DB::table('settings')->insert([/* all 9 settings */]);
}
```

**What happened:**
1. Some settings added via migrations (4 settings)
2. Some settings added via seeder (5 settings)
3. Run `migrate:fresh --seed`
4. Migrations add 4 settings
5. Seeder sees settings exist, skips entirely
6. Result: Only 4/9 settings exist
7. App crashes with `MissingSettings` error

### After (✅ Correct)
```php
// 2026_01_13_090208_add_core_voucher_settings_to_settings.php
public function up(): void {
    DB::table('settings')->insertOrIgnore([
        ['group' => 'voucher', 'name' => 'default_amount', ...],
        ['group' => 'voucher', 'name' => 'default_expiry_days', ...],
        // ... all core settings
    ]);
}
```

**Result:**
- ✅ All settings created via migrations
- ✅ Works in production and development
- ✅ Safe to run multiple times
- ✅ No coordination issues

## Implementation Guidelines

### Adding a New Setting

**Step 1: Create Migration**
```bash
php artisan make:migration add_new_feature_setting_to_voucher_settings
```

**Step 2: Implement Migration**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'group' => 'voucher',
            'name' => 'new_feature_enabled',
            'payload' => json_encode(false),
            'locked' => false,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'voucher')
            ->where('name', 'new_feature_enabled')
            ->delete();
    }
};
```

**Step 3: Run Migration**
```bash
php artisan migrate
```

**Step 4: Update Settings Class**
```php
// app/Settings/VoucherSettings.php
class VoucherSettings extends Settings
{
    public bool $new_feature_enabled; // Add property
}
```

### Multiple Related Settings

For multiple related settings, add them in a single migration:

```php
public function up(): void
{
    DB::table('settings')->insertOrIgnore([
        [
            'group' => 'voucher',
            'name' => 'feature_a_enabled',
            'payload' => json_encode(false),
            'locked' => false,
        ],
        [
            'group' => 'voucher',
            'name' => 'feature_a_threshold',
            'payload' => json_encode(100),
            'locked' => false,
        ],
        [
            'group' => 'voucher',
            'name' => 'feature_a_endpoint',
            'payload' => json_encode('/api/feature-a'),
            'locked' => false,
        ],
    ]);
}

public function down(): void
{
    DB::table('settings')
        ->where('group', 'voucher')
        ->whereIn('name', [
            'feature_a_enabled',
            'feature_a_threshold',
            'feature_a_endpoint',
        ])
        ->delete();
}
```

## Best Practices

### 1. Use insertOrIgnore()
```php
// ✅ Safe - won't error if setting exists
DB::table('settings')->insertOrIgnore([...]);

// ❌ Risky - will error if setting exists
DB::table('settings')->insert([...]);
```

### 2. Always Implement down()
```php
// ✅ Proper rollback support
public function down(): void {
    DB::table('settings')
        ->where('name', 'setting_name')
        ->delete();
}

// ❌ Can't rollback
public function down(): void {
    // Empty
}
```

### 3. Use Config Fallbacks
```php
// ✅ Falls back to config if not in settings
'payload' => json_encode(config('generate.amount.default', 50)),

// ❌ Hardcoded values
'payload' => json_encode(50),
```

### 4. Descriptive Migration Names
```php
// ✅ Clear and specific
add_auto_disburse_minimum_to_voucher_settings

// ❌ Vague
update_settings
add_new_setting
```

### 5. Group Related Settings
```php
// ✅ Group related settings in one migration
add_disbursement_settings_to_voucher_settings
  - auto_disburse_enabled
  - auto_disburse_minimum
  - auto_disburse_maximum

// ❌ One migration per setting (creates clutter)
add_auto_disburse_enabled
add_auto_disburse_minimum
add_auto_disburse_maximum
```

## Testing

### Test Fresh Installation
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="dd(DB::table('settings')->where('group', 'voucher')->pluck('name'));"
```

### Test Idempotency
```bash
# Run migration
php artisan migrate

# Run again - should not error
php artisan migrate

# Verify no duplicates
php artisan tinker --execute="
    \$duplicates = DB::table('settings')
        ->select('group', 'name', DB::raw('count(*) as count'))
        ->groupBy('group', 'name')
        ->having('count', '>', 1)
        ->get();
    dd(\$duplicates->isEmpty() ? 'No duplicates ✓' : \$duplicates);
"
```

### Test Rollback
```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Verify setting removed
php artisan tinker --execute="dd(DB::table('settings')->where('name', 'new_setting')->exists());"
```

## Historical Context

### Why VoucherSettingsSeeder is Deprecated

The `VoucherSettingsSeeder` was deprecated in favor of migrations because:

1. **Flawed logic**: Skipped if ANY settings existed, causing missing settings
2. **Production gap**: Never ran in production, only in development
3. **Coordination issues**: Easy to forget to run after pulling changes
4. **No rollback**: Couldn't undo seeder changes

All voucher settings are now managed via these migrations:
- `2026_01_13_090208_add_core_voucher_settings_to_settings.php` (core settings)
- `2026_01_07_091011_add_default_settlement_endpoint_to_settings.php`
- `2026_01_11_121748_add_portal_endpoint_to_voucher_settings.php`
- `2026_01_12_075432_add_auto_disburse_minimum_to_voucher_settings.php`

## Common Pitfalls

### ❌ Pitfall 1: Mixing Approaches
```php
// Some settings in migrations
// Some settings in seeders
// Result: Incomplete settings after migrate:fresh --seed
```

**Solution:** Pick one approach and stick with it.

### ❌ Pitfall 2: Forgetting insertOrIgnore()
```php
DB::table('settings')->insert([...]); // Errors on re-run
```

**Solution:** Always use `insertOrIgnore()` or check existence first.

### ❌ Pitfall 3: No Rollback
```php
public function down(): void {
    // Empty - can't rollback
}
```

**Solution:** Always implement proper `down()` method.

### ❌ Pitfall 4: Hardcoded Values
```php
'payload' => json_encode('http://localhost:8000'), // Won't work in production
```

**Solution:** Use `config()` with fallbacks.

## AI Agent Instructions

**When adding a new setting to VoucherSettings or any Spatie Settings class:**

1. **ALWAYS create a migration** - Never add to seeders
2. **Use insertOrIgnore()** - Ensure idempotency
3. **Implement down()** - Enable rollback
4. **Use config() fallbacks** - Don't hardcode environment-specific values
5. **Descriptive names** - Migration name should clearly indicate what setting is being added
6. **Group related settings** - Add multiple related settings in one migration
7. **Update Settings class** - Add property to corresponding Settings class
8. **Test thoroughly** - Verify `migrate:fresh`, idempotency, and rollback

**Example workflow:**
```bash
# 1. Create migration
php artisan make:migration add_new_setting_to_voucher_settings

# 2. Implement with insertOrIgnore() and proper down()
# 3. Run migration
php artisan migrate

# 4. Test idempotency
php artisan migrate

# 5. Verify setting exists
php artisan tinker --execute="dd(DB::table('settings')->where('name', 'new_setting')->first());"
```

## References

- [Spatie Laravel Settings Documentation](https://github.com/spatie/laravel-settings)
- [Laravel Migration Documentation](https://laravel.com/docs/migrations)
- Project file: `database/seeders/VoucherSettingsSeeder.php` (deprecated, kept for reference)
- Project file: `app/Settings/VoucherSettings.php`
