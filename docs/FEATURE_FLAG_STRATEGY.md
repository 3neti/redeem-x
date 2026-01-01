# Feature Flag Strategy: Voucher Generation V2

## Philosophy

**Progressive Disclosure + Gradual Feature Activation**

1. **Default to Simple Mode** - Users see only battle-tested features
2. **Opt-in Advanced Mode** - Users can request access (admin approval required)
3. **Per-Feature Flags** - Each advanced feature has its own readiness flag
4. **Safe Rollout** - Enable features only when backend is fully implemented

## Flag Hierarchy

```
voucher-generation-v2 (master flag)
├── voucher-advanced-mode (UI access control)
│   ├── Requires: admin approval OR beta tester role
│   └── Default: false (locked to simple mode)
│
├── Feature-specific flags (functional readiness)
│   ├── validation-secret-code (complete ✅)
│   ├── validation-mobile-restriction (complete ✅)
│   ├── validation-location (⚠️ UI only, redemption TODO)
│   ├── validation-time (⚠️ UI only, redemption TODO)
│   ├── feedback-channels (complete ✅)
│   ├── rider-customization (complete ✅)
│   ├── settlement-rail-selection (complete ✅)
│   └── preview-controls (⚠️ partial, needs testing)
```

## Implementation

### 1. Master Feature Definition

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;
use App\Models\User;

public function boot(): void
{
    // === MASTER FLAG: V2 UI Access ===
    Feature::define('voucher-generation-v2', fn (User $user) => 
        // Enable for everyone (V2 is default)
        true
    );
    
    // === UI MODE: Simple vs Advanced ===
    Feature::define('voucher-advanced-mode', function (User $user) {
        // Allow advanced mode only if:
        // 1. User explicitly enabled it, OR
        // 2. User is admin/beta tester
        
        // Check if user has manually activated (stored in features table)
        $manuallyActivated = Feature::for($user)->value('voucher-advanced-mode');
        if ($manuallyActivated !== null) {
            return $manuallyActivated;
        }
        
        // Auto-enable for internal team
        if ($user->hasRole('admin') || $user->hasRole('beta-tester')) {
            return true;
        }
        
        // Default: locked to simple mode
        return false;
    });
    
    // === FEATURE-SPECIFIC FLAGS ===
    
    // Secret Code Validation (READY ✅)
    Feature::define('feature-validation-secret-code', fn (User $user) => true);
    
    // Mobile Restriction (READY ✅)
    Feature::define('feature-validation-mobile', fn (User $user) => true);
    
    // Location Validation (NOT READY ⚠️)
    Feature::define('feature-validation-location', function (User $user) {
        // Only enable for admins during testing
        return $user->hasRole('admin');
    });
    
    // Time Validation (NOT READY ⚠️)
    Feature::define('feature-validation-time', function (User $user) {
        // Only enable for admins during testing
        return $user->hasRole('admin');
    });
    
    // Feedback Channels (READY ✅)
    Feature::define('feature-feedback-channels', fn (User $user) => true);
    
    // Rider Customization (READY ✅)
    Feature::define('feature-rider-customization', fn (User $user) => true);
    
    // Settlement Rail Selection (READY ✅)
    Feature::define('feature-settlement-rail', fn (User $user) => true);
    
    // Preview Controls (TESTING ⚠️)
    Feature::define('feature-preview-controls', function (User $user) {
        // Gradual rollout: 25% of advanced mode users
        if (!Feature::for($user)->active('voucher-advanced-mode')) {
            return false;
        }
        return Lottery::odds(1, 4)->choose();
    });
}
```

### 2. Backend Controller Logic

```php
// app/Http/Controllers/Vouchers/GenerateVouchersController.php
use Laravel\Pennant\Feature;

public function create(Request $request)
{
    $user = $request->user();
    
    // Check master flag
    if (!Feature::for($user)->active('voucher-generation-v2')) {
        return Inertia::render('Vouchers/Generate/Create'); // Legacy UI
    }
    
    // Determine mode
    $isAdvanced = Feature::for($user)->active('voucher-advanced-mode');
    
    // Load feature availability
    $features = [
        'validation_secret_code' => Feature::for($user)->active('feature-validation-secret-code'),
        'validation_mobile' => Feature::for($user)->active('feature-validation-mobile'),
        'validation_location' => Feature::for($user)->active('feature-validation-location'),
        'validation_time' => Feature::for($user)->active('feature-validation-time'),
        'feedback_channels' => Feature::for($user)->active('feature-feedback-channels'),
        'rider_customization' => Feature::for($user)->active('feature-rider-customization'),
        'settlement_rail' => Feature::for($user)->active('feature-settlement-rail'),
        'preview_controls' => Feature::for($user)->active('feature-preview-controls'),
    ];
    
    return Inertia::render('Vouchers/Generate/CreateV2', [
        'initialMode' => $isAdvanced ? 'advanced' : 'simple',
        'canAccessAdvancedMode' => Feature::for($user)->active('voucher-advanced-mode'),
        'features' => $features,
        'config' => $this->buildConfig($features), // Hide disabled features
        // ... other props
    ]);
}

private function buildConfig(array $features): array
{
    $config = config('generate');
    
    // Hide cards for disabled features
    if (!$features['validation_location']) {
        $config['location_validation']['show_card'] = false;
    }
    
    if (!$features['validation_time']) {
        $config['time_validation']['show_card'] = false;
    }
    
    if (!$features['preview_controls']) {
        $config['preview_controls']['show_card'] = false;
    }
    
    return $config;
}
```

### 3. Frontend Feature Detection

```typescript
// resources/js/composables/useFeatureFlags.ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useFeatureFlags() {
    const page = usePage()
    
    const features = computed(() => page.props.features || {})
    
    const canUseFeature = (featureName: string) => {
        return features.value[featureName] === true
    }
    
    return {
        features,
        canUseFeature,
    }
}
```

```vue
<!-- resources/js/pages/Vouchers/Generate/CreateV2.vue -->
<script setup lang="ts">
import { useFeatureFlags } from '@/composables/useFeatureFlags'

const { features, canUseFeature } = useFeatureFlags()

// Only show Location Validation if feature is enabled
const showLocationValidation = computed(() => 
    !isSimpleMode.value && 
    canUseFeature('validation_location') &&
    config.location_validation.show_card
)
</script>

<template>
    <!-- Location Validation (conditionally rendered) -->
    <div v-if="showLocationValidation" class="space-y-2">
        <LocationValidationForm ... />
    </div>
</template>
```

### 4. Advanced Mode Access Control

**Request Access UI** (for regular users):

```vue
<!-- Simple Mode users see "Request Advanced Mode" button -->
<div v-if="isSimpleMode && !canAccessAdvancedMode" class="text-center p-4 bg-muted rounded-md">
    <p class="text-sm text-muted-foreground mb-2">
        Need more control? Request access to Advanced Mode.
    </p>
    <Button @click="requestAdvancedAccess" variant="outline" size="sm">
        Request Advanced Mode
    </Button>
</div>
```

**Admin Approval Workflow**:

```php
// routes/web.php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/feature-requests', [FeatureRequestController::class, 'index']);
    Route::post('/admin/feature-requests/{user}/approve', [FeatureRequestController::class, 'approve']);
});

// app/Http/Controllers/Admin/FeatureRequestController.php
public function approve(User $user)
{
    // Permanently enable advanced mode for this user
    Feature::for($user)->activate('voucher-advanced-mode');
    
    // Send notification
    $user->notify(new AdvancedModeApproved());
    
    return back()->with('success', "Advanced mode enabled for {$user->name}");
}
```

### 5. API Validation (Safety Net)

```php
// app/Http/Requests/GenerateVouchersRequest.php
use Laravel\Pennant\Feature;

public function rules(): array
{
    $user = $this->user();
    
    return [
        // Always allowed (simple mode features)
        'amount' => 'required|numeric|min:0',
        'count' => 'required|integer|min:1',
        'ttl_days' => 'nullable|integer|min:1',
        
        // Conditionally allowed (advanced mode features)
        'validation_secret' => [
            'nullable',
            'string',
            Rule::requiredIf(fn () => !Feature::for($user)->active('feature-validation-secret-code')),
        ],
        
        'validation_location' => [
            'nullable',
            'array',
            function ($attribute, $value, $fail) use ($user) {
                if ($value && !Feature::for($user)->active('feature-validation-location')) {
                    $fail('Location validation is not available for your account.');
                }
            },
        ],
        
        // ... similar for other features
    ];
}
```

## Rollout Phases

### Phase 1: Internal Testing (Current)
```php
// All flags default to admin-only
Feature::define('feature-validation-location', fn (User $user) => 
    $user->hasRole('admin')
);
```

**Testing checklist:**
- [ ] Location validation works in generation UI
- [ ] Location validation enforced during redemption
- [ ] Mobile validation enforced during redemption
- [ ] Time validation enforced during redemption
- [ ] Preview controls tested

### Phase 2: Beta Testers (Week 2)
```php
// Enable for beta testers
Feature::define('feature-validation-location', fn (User $user) => 
    $user->hasRole('admin') || $user->hasRole('beta-tester')
);
```

### Phase 3: Gradual Rollout (Week 3-4)
```php
// Gradual rollout: 25% → 50% → 75% → 100%
Feature::define('feature-validation-location', function (User $user) {
    if ($user->hasRole('admin') || $user->hasRole('beta-tester')) {
        return true;
    }
    return Lottery::odds(1, 4)->choose(); // 25%
});
```

### Phase 4: General Availability
```php
// Enable for all advanced mode users
Feature::define('feature-validation-location', fn (User $user) => 
    Feature::for($user)->active('voucher-advanced-mode')
);
```

## Monitoring & Safety

### 1. Feature Usage Analytics

```php
// Track feature usage
use App\Events\FeatureUsed;

if (Feature::for($user)->active('feature-validation-location')) {
    event(new FeatureUsed($user, 'validation-location'));
}
```

### 2. Kill Switch

```php
// Emergency disable via config
Feature::define('feature-validation-location', function (User $user) {
    // Kill switch in config
    if (!config('features.validation_location_enabled', true)) {
        return false;
    }
    
    return $user->hasRole('admin');
});
```

### 3. Error Handling

```php
// Graceful degradation if feature fails
try {
    if (Feature::for($user)->active('feature-validation-location')) {
        // Use location validation
    }
} catch (\Exception $e) {
    Log::warning('Location validation feature check failed', [
        'user_id' => $user->id,
        'error' => $e->getMessage(),
    ]);
    // Fall back to no location validation
}
```

## User Communication

### 1. Feature Badges
```vue
<CardTitle>
    Location Validation
    <Badge v-if="!canUseFeature('validation_location')" variant="secondary">
        Coming Soon
    </Badge>
</CardTitle>
```

### 2. Tooltips
```vue
<Tooltip>
    <TooltipTrigger>
        <HelpCircle class="h-4 w-4" />
    </TooltipTrigger>
    <TooltipContent>
        Location validation is being tested. Request early access from your admin.
    </TooltipContent>
</Tooltip>
```

### 3. Changelog
Track which features were enabled when:
```php
// When enabling a feature
ActivityLog::create([
    'user_id' => $user->id,
    'action' => 'feature_enabled',
    'feature' => 'validation-location',
    'timestamp' => now(),
]);
```

## Recommendation

**Start with this strategy:**

1. **Week 1 (Now)**: Keep Simple Mode as default, Advanced Mode locked
   - Admin-only access to incomplete features (location, time validation)
   - Complete redemption implementation for location/time validation

2. **Week 2**: Enable Advanced Mode requests
   - Users can request access (manual approval)
   - Invite select beta testers

3. **Week 3-4**: Gradual feature rollout
   - Enable location validation: 25% → 50% → 100%
   - Enable time validation: 25% → 50% → 100%

4. **Month 2**: General availability
   - Advanced Mode available to all (self-service toggle)
   - All features enabled

This gives you control, safety, and clear migration path! 🚀
