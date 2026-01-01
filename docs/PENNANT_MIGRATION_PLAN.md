# Laravel Pennant Migration Plan: Simple/Advanced Mode

## Overview
Migrate the Simple/Advanced mode toggle from custom preferences API to Laravel Pennant for better maintainability and feature flag capabilities.

## Current State
- Mode stored in `users.ui_preferences->voucher_mode` (JSON column)
- Custom API endpoint: `PUT /api/v1/preferences/voucher-mode`
- Frontend composable: `useVoucherMode` with local storage sync

## Benefits of Pennant
1. **Standardized**: Industry-standard feature flag pattern
2. **Database-backed**: Dedicated `features` table with better indexing
3. **Rich API**: `Feature::active()`, `Feature::for($user)->value()`, etc.
4. **Testability**: Built-in `Feature::fake()` for testing
5. **Performance**: Eager loading, in-memory caching
6. **Flexibility**: Easy A/B testing, gradual rollouts

## Installation

```bash
# Install Pennant
composer require laravel/pennant

# Publish config and migration
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"

# Run migration (creates `features` table)
php artisan migrate
```

## Implementation Steps

### 1. Define Feature (AppServiceProvider)

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;
use App\Models\User;

public function boot(): void
{
    Feature::define('voucher-advanced-mode', function (User $user) {
        // Default to simple mode for all users
        // They can toggle via UI
        return false;
    });
}
```

### 2. Backend API Changes

**Option A: Keep existing API, use Pennant internally**
```php
// app/Http/Controllers/Api/PreferencesController.php
use Laravel\Pennant\Feature;

public function updateVoucherMode(Request $request)
{
    $validated = $request->validate([
        'mode' => 'required|in:simple,advanced',
    ]);
    
    $isAdvanced = $validated['mode'] === 'advanced';
    
    // Store in Pennant (replaces ui_preferences update)
    Feature::for($request->user())->activate('voucher-advanced-mode', $isAdvanced);
    
    return response()->json([
        'mode' => $validated['mode'],
        'success' => true,
    ]);
}
```

**Option B: New Pennant-specific endpoints (cleaner)**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::put('features/voucher-advanced-mode', function (Request $request) {
        $active = $request->boolean('active');
        Feature::for($request->user())->activate('voucher-advanced-mode', $active);
        return response()->json(['active' => $active]);
    });
    
    Route::get('features/voucher-advanced-mode', function (Request $request) {
        return response()->json([
            'active' => Feature::for($request->user())->active('voucher-advanced-mode'),
        ]);
    });
});
```

### 3. Controller Changes

```php
// app/Http/Controllers/Vouchers/GenerateVouchersController.php
use Laravel\Pennant\Feature;

public function create(Request $request)
{
    $isAdvanced = Feature::for($request->user())->active('voucher-advanced-mode');
    
    return Inertia::render('Vouchers/Generate/CreateV2', [
        'initialMode' => $isAdvanced ? 'advanced' : 'simple',
        // ... other props
    ]);
}
```

### 4. Frontend Composable (minimal changes)

```typescript
// resources/js/composables/useVoucherMode.ts
import { ref } from 'vue'
import axios from '@/lib/axios'

export function useVoucherMode(initialMode: 'simple' | 'advanced' = 'simple') {
    const mode = ref<'simple' | 'advanced'>(initialMode)
    
    const switchMode = async (newMode: 'simple' | 'advanced') => {
        const isAdvanced = newMode === 'advanced'
        
        // Call Pennant API
        await axios.put('/api/v1/features/voucher-advanced-mode', {
            active: isAdvanced
        })
        
        mode.value = newMode
        localStorage.setItem('voucher-mode', newMode)
    }
    
    return { mode, switchMode }
}
```

### 5. Data Migration

Migrate existing `ui_preferences` to Pennant `features` table:

```php
// database/migrations/2026_01_01_000000_migrate_voucher_mode_to_pennant.php
use Laravel\Pennant\Feature;
use App\Models\User;

public function up(): void
{
    User::whereNotNull('ui_preferences->voucher_mode')->chunk(100, function ($users) {
        foreach ($users as $user) {
            $mode = $user->ui_preferences['voucher_mode'] ?? 'simple';
            $isAdvanced = $mode === 'advanced';
            
            Feature::for($user)->activate('voucher-advanced-mode', $isAdvanced);
        }
    });
    
    // Optional: Clean up old ui_preferences column
    // User::query()->update(['ui_preferences->voucher_mode' => null]);
}
```

### 6. Testing

```php
// tests/Feature/VoucherModeTest.php
use Laravel\Pennant\Feature;

it('defaults to simple mode for new users', function () {
    $user = User::factory()->create();
    
    expect(Feature::for($user)->active('voucher-advanced-mode'))->toBeFalse();
});

it('allows users to toggle to advanced mode', function () {
    $user = User::factory()->create();
    
    Feature::for($user)->activate('voucher-advanced-mode');
    
    expect(Feature::for($user)->active('voucher-advanced-mode'))->toBeTrue();
});

it('persists mode across sessions', function () {
    $user = User::factory()->create();
    
    Feature::for($user)->activate('voucher-advanced-mode');
    
    // Simulate new request
    $freshUser = User::find($user->id);
    
    expect(Feature::for($freshUser)->active('voucher-advanced-mode'))->toBeTrue();
});
```

## Migration Checklist

- [ ] Install Pennant via Composer
- [ ] Publish config and run migrations
- [ ] Define `voucher-advanced-mode` feature in AppServiceProvider
- [ ] Update backend API (choose Option A or B)
- [ ] Update controller to use Pennant
- [ ] Update frontend composable
- [ ] Create data migration script
- [ ] Run migration to transfer existing preferences
- [ ] Update tests
- [ ] Test in browser (both modes)
- [ ] Deploy and monitor

## Rollback Plan

If issues arise:
1. Revert API changes to use `ui_preferences` again
2. Keep Pennant installed for future features
3. Old data remains in `ui_preferences` column

## Future Enhancements with Pennant

Once migrated, you can easily add:

1. **A/B Testing**: Automatically assign 50% of users to advanced mode
   ```php
   Feature::define('voucher-advanced-mode', fn (User $user) => 
       Lottery::odds(1, 2)
   );
   ```

2. **Gradual Rollout**: Enable for internal team first
   ```php
   Feature::define('voucher-advanced-mode', fn (User $user) => 
       $user->isInternalTeamMember() ?: Lottery::odds(1, 10)
   );
   ```

3. **Multi-tenant**: Different defaults per organization
   ```php
   Feature::for($user->organization)->active('voucher-advanced-mode')
   ```

4. **Blade Directives**: Server-side rendering
   ```blade
   @feature('voucher-advanced-mode')
       <!-- Advanced mode UI -->
   @else
       <!-- Simple mode UI -->
   @endfeature
   ```

## Recommendation

**Yes, migrate to Pennant!** It's a minor refactor with significant long-term benefits. The current implementation works, but Pennant provides:
- Better architecture (feature flags vs. preferences)
- Easier testing and debugging
- Path to more sophisticated feature management
- Standard Laravel pattern other devs will recognize

Start with Option B (new endpoints) for cleaner separation of concerns.
