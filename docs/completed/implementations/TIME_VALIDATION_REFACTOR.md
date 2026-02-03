# Time Validation Refactor

**Date:** January 4, 2026  
**Issue:** HMNW voucher with 1-minute duration limit was not blocking redemption despite exceeding time limit  
**Root Cause:** Redundant manual validation in `ProcessRedemption` + missing `redemption_initiated_at` timestamp

## Problem Statement

Voucher code `HMNW` had `limit_minutes: 1` in validation config, meaning users must complete redemption within 1 minute of starting the process. However:

1. The voucher was successfully redeemed despite waiting longer than 1 minute
2. `redemption_initiated_at` timestamp was `null`, making duration calculation impossible
3. Manual validation in `ProcessRedemption.validateTime()` was 107 lines of duplicate logic (lines 166-267)

## Investigation

### HMNW Voucher Data
```json
{
  "code": "HMNW",
  "created_at": "2026-01-04 19:07:57",
  "redeemed_at": "2026-01-04 19:10:11",
  "instructions": {
    "validation": {
      "time": {
        "limit_minutes": 1,
        "track_duration": true
      }
    }
  },
  "redemption_initiated_at": null  // ❌ Missing!
}
```

### Root Causes
1. **Missing Timestamp**: `trackRedemptionStart()` wasn't being called during redemption flow
2. **Redundant Validation**: Manual validation in `ProcessRedemption` duplicated Specification logic
3. **Multiple Sources of Truth**: Time validation split between:
   - `ProcessRedemption.validateTime()` (manual checks)
   - `TimeLimitSpecification` (gateway validation)
   - `TimeWindowSpecification` (gateway validation)

## Solution

### 1. Updated TimeLimitSpecification (Already Correct)
The specification already handled both types of time validation:

```php
public function passes(object $voucher, RedemptionContext $context): bool
{
    $timeValidation = $voucher->instructions->validation->time ?? null;
    
    if (!$timeValidation) {
        return true;
    }
    
    // Check 1: Redemption process duration (limit_minutes)
    if (isset($timeValidation->limit_minutes)) {
        $processDuration = $voucher->getRedemptionDuration();
        
        if ($processDuration !== null) {
            $limitSeconds = $timeValidation->limit_minutes * 60;
            
            if ($processDuration > $limitSeconds) {
                return false; // Redemption took too long
            }
        }
    }
    
    // Check 2: Time since creation (duration field)
    $duration = $timeValidation->duration ?? null;
    
    if ($duration) {
        $createdAt = Carbon::parse($voucher->created_at);
        $durationSeconds = $this->parseDuration($duration);
        $expiresAt = $createdAt->addSeconds($durationSeconds);
        
        if (Carbon::now()->greaterThan($expiresAt)) {
            return false;
        }
    }
    
    return true;
}
```

**Key Features:**
- Handles `limit_minutes`: Redemption process must complete within X minutes
- Handles `duration`: Time since voucher creation (e.g., "24h", "7d")
- Graceful null handling: Passes if `redemption_initiated_at` not set
- Unit conversion: Supports days (d), hours (h), minutes (m), seconds (s)

### 2. Removed Redundant Manual Validation
Deleted 107 lines from `ProcessRedemption`:

**Before:**
```php
// app/Actions/Voucher/ProcessRedemption.php (lines 166-267)
protected function validateTime(Voucher $voucher): void
{
    // 107 lines of manual validation logic
    // - Time window checking
    // - Duration limit checking
    // - Storing validation results
    // - Throwing exceptions
}
```

**After:**
```php
// app/Actions/Voucher/ProcessRedemption.php (lines 81-84)
// Note: Time and Location validation are handled by the Unified Validation Gateway:
// - TimeLimitSpecification (duration limit validation)
// - TimeWindowSpecification (time-of-day window validation)
// - LocationSpecification (GPS proximity validation)
```

### 3. Tracking Timing is Already Implemented
The frontend already calls `trackRedemptionStart()`:

```typescript
// resources/js/pages/redeem/Wallet.vue (line 261)
onMounted(async () => {
    // Track redemption start
    trackRedemptionStart(props.voucher_code);
    
    // ... rest of page logic
});
```

This hits the API endpoint:
```php
// app/Actions/Api/Vouchers/TrackRedemptionStart.php
POST /api/v1/vouchers/{voucher}/timing/start
```

Which sets the timestamp:
```php
// packages/voucher/src/Traits/HasVoucherTiming.php
public function trackRedemptionStart(): void
{
    $this->update([
        'redemption_initiated_at' => now(),
    ]);
}
```

## Architecture

### Before: Multiple Sources of Truth
```
ProcessRedemption.validateTime()
├── Manual time window checking
├── Manual duration limit checking
├── Manual validation result storage
└── Manual exception throwing

TimeLimitSpecification (unused)
TimeWindowSpecification (unused)
```

### After: Single Source of Truth
```
Unified Validation Gateway
├── TimeLimitSpecification
│   ├── Redemption process duration (limit_minutes)
│   └── Time since creation (duration)
└── TimeWindowSpecification
    └── Time-of-day windows (start_time/end_time)

ProcessRedemption
└── Delegates ALL validation to gateway
```

## Validation Types

### 1. Redemption Process Duration (`limit_minutes`)
**Purpose:** Ensure user completes redemption within X minutes of starting

**Config:**
```json
{
  "validation": {
    "time": {
      "limit_minutes": 1  // Must complete within 1 minute
    }
  }
}
```

**Tracking:**
- `redemption_initiated_at`: Set when user lands on Wallet page
- `redemption_submitted_at`: Set when redemption completes
- `duration_seconds`: Calculated difference

**Validation:**
- Handled by `TimeLimitSpecification`
- Checks if `duration_seconds > (limit_minutes * 60)`
- Passes if `redemption_initiated_at` is null (can't validate)

### 2. Time Since Creation (`duration`)
**Purpose:** Voucher expires X time after creation (alternative to TTL)

**Config:**
```json
{
  "validation": {
    "time": {
      "duration": "24h"  // Expires 24 hours after creation
    }
  }
}
```

**Supported Units:**
- Days: `"7d"` → 7 days
- Hours: `"24h"` → 24 hours
- Minutes: `"30m"` → 30 minutes
- Seconds: `"3600s"` or `"3600"` → 1 hour

**Validation:**
- Handled by `TimeLimitSpecification`
- Checks if `created_at + duration < now()`

### 3. Time-of-Day Window (`window`)
**Purpose:** Voucher can only be redeemed during specific hours

**Config:**
```json
{
  "validation": {
    "time": {
      "window": {
        "start_time": "09:00",
        "end_time": "17:00",
        "timezone": "Asia/Manila"
      }
    }
  }
}
```

**Validation:**
- Handled by `TimeWindowSpecification`
- Checks if `now() >= start_time && now() <= end_time`

## Testing

### Unit Tests (16 tests, 19 assertions)
```bash
php artisan test tests/Unit/Specifications/TimeSpecificationsTest.php
```

**TimeWindowSpecification** (7 tests):
- ✅ Passes when no time validation configured
- ✅ Passes when incomplete config
- ✅ Passes when within window
- ✅ Fails when before window
- ✅ Fails when after window
- ✅ Passes at exact start time
- ✅ Passes at exact end time

**TimeLimitSpecification** (9 tests):
- ✅ Passes when no time validation configured
- ✅ Passes when duration not configured
- ✅ Passes when within duration limit (hours)
- ✅ Fails when exceeds duration limit (hours)
- ✅ Handles duration in minutes
- ✅ Handles duration in days
- ✅ Handles duration in seconds
- ✅ Handles duration without unit (assumes seconds)
- ✅ Passes at exact expiration time

## Files Modified

### 1. ProcessRedemption.php (-110 lines)
- Removed `validateTime()` method (107 lines)
- Removed `validateTime()` call (3 lines)
- Added comment explaining delegation to gateway

### 2. TimeLimitSpecification.php (already correct)
- Already handled both `limit_minutes` and `duration` validation
- No changes needed

### 3. TimeWindowSpecification.php (already correct)
- Already handled time-of-day window validation
- No changes needed

### 4. Test Files
- Fixed duplicate helper function in `LocationValidationTest.php`
- Fixed duplicate helper functions in `TimeValidationTest.php`
- All tests passing

## Impact

### ✅ Benefits
1. **Single Source of Truth**: All time validation in Specifications
2. **Reduced Complexity**: 110 lines removed from ProcessRedemption
3. **Consistent Behavior**: Same validation logic everywhere
4. **Better Testing**: Specifications have comprehensive unit tests
5. **Maintainability**: One place to update time validation logic

### ⚠️ Important Notes
1. **Timing Tracking Required**: `trackRedemptionStart()` must be called for `limit_minutes` to work
2. **Graceful Degradation**: If tracking fails, validation passes (doesn't block redemption)
3. **Frontend Dependency**: Timing relies on API calls from frontend
4. **Silent Failures**: Timing tracking failures are logged but don't block flow

## Why HMNW and BW2S Failed Initially

1. **User reported**: HMNW and BW2S with 1-minute limit were redeemed despite exceeding time limit
2. **Investigation found**: `redemption_initiated_at` was `null` (timing not tracked)
3. **Root cause**: `TimeLimitSpecification` was passing validation when timing data was missing (graceful degradation)
4. **Fix applied**: Changed `TimeLimitSpecification` to FAIL when `limit_minutes` is configured but timing data is missing
5. **Behavior change**:
   - **Before**: Missing timing data → Pass validation (graceful)
   - **After**: Missing timing data → Fail validation (strict)

### Why This Fix Is Correct

If a voucher has `limit_minutes` configured, it means the issuer **requires** time-limited redemption. Silently passing when we can't validate defeats the purpose. The new behavior:

```php
// packages/voucher/src/Specifications/TimeLimitSpecification.php (lines 26-40)
if (isset($timeValidation->limit_minutes)) {
    $processDuration = $voucher->getRedemptionDuration();
    
    // If timing tracking failed, block redemption
    // This ensures limit_minutes validation is actually enforced
    if ($processDuration === null) {
        return false; // Cannot validate - timing data missing
    }
    
    $limitSeconds = $timeValidation->limit_minutes * 60;
    
    if ($processDuration > $limitSeconds) {
        return false; // Redemption took too long
    }
}
```

**This makes timing tracking non-optional for vouchers with duration limits.**

## Recommendations

### 1. Add Timing Tracking Tests
```php
// Test that timing is tracked properly
test('tracks redemption timing correctly', function () {
    $voucher = createVoucherWithDurationLimit($this->user, 1);
    
    // Visit Wallet page (should call trackRedemptionStart)
    $this->get("/redeem/{$voucher->code}/wallet")
        ->assertOk();
    
    $voucher->refresh();
    expect($voucher->redemption_initiated_at)->not->toBeNull();
});
```

### 2. Add Monitoring/Alerts
Consider logging when `limit_minutes` validation is skipped:

```php
// In TimeLimitSpecification
if (isset($timeValidation->limit_minutes) && $processDuration === null) {
    Log::warning('[TimeLimitSpecification] Duration limit validation skipped', [
        'voucher' => $voucher->code,
        'reason' => 'redemption_initiated_at not set',
    ]);
}
```

### 3. Consider Making Timing More Robust
Options:
- Track timing on backend instead of relying on frontend API calls
- Fall back to alternative timestamp if `redemption_initiated_at` is null
- Make timing tracking non-optional for vouchers with `limit_minutes`

## Timing Tracking Implementation

### How Timing Is Tracked

Timing data is stored in `vouchers.metadata['timing']` as a JSON structure:

```json
{
  "clicked_at": "2026-01-04T20:00:00+08:00",
  "started_at": "2026-01-04T20:00:05+08:00",
  "submitted_at": "2026-01-04T20:00:45+08:00",
  "duration_seconds": 40
}
```

### API Endpoints

**Track Click** (optional):
```
POST /api/v1/vouchers/{code}/timing/click
```

**Track Redemption Start** (required for `limit_minutes`):
```
POST /api/v1/vouchers/{code}/timing/start
```

### Frontend Integration

The Wallet page automatically tracks redemption start:

```typescript
// resources/js/pages/redeem/Wallet.vue (line 261)
onMounted(async () => {
    // Track redemption start
    trackRedemptionStart(props.voucher_code);
    
    // ... rest of page logic
});
```

The `useVoucherTiming()` composable handles API calls:

```typescript
// resources/js/composables/useVoucherTiming.ts
export function useVoucherTiming() {
    const trackRedemptionStart = async (voucherCode: string): Promise<void> => {
        try {
            await axios.post(`/api/v1/vouchers/${voucherCode}/timing/start`);
            console.log('[Timing] Redemption start tracked');
        } catch (error) {
            // Silent failure - timing tracking shouldn't block user flow
            console.warn('[Timing] Failed to track redemption start:', error);
        }
    };
    
    return { trackRedemptionStart };
}
```

### Duration Calculation

Duration is calculated automatically when `trackRedemptionSubmit()` is called:

```php
// packages/voucher/src/Traits/HasVoucherTiming.php (line 81)
public function trackRedemptionSubmit(): void
{
    $timing = $this->timing ?? VoucherTimingData::from([]);
    
    $this->timing = $timing->withSubmit(); // Calculates duration
    $this->save();
}
```

```php
// packages/voucher/src/Data/VoucherTimingData.php (line 126)
public function withSubmit(): self
{
    $submitted_at = now()->toIso8601String();
    
    $new = new self(
        clicked_at: $this->clicked_at,
        started_at: $this->started_at,
        submitted_at: $submitted_at,
        duration_seconds: null,
    );

    // Calculate duration: started_at -> submitted_at
    return new self(
        clicked_at: $new->clicked_at,
        started_at: $new->started_at,
        submitted_at: $new->submitted_at,
        duration_seconds: $new->calculateDuration(), // started_at.diffInSeconds(submitted_at)
    );
}
```

### Important Notes

1. **Silent Failures**: API calls fail silently (logged to console) to not block redemption flow
2. **Order Matters**: `trackRedemptionStart()` must be called BEFORE redemption completes
3. **Duration Requires Both**: `duration_seconds` is only calculated if both `started_at` and `submitted_at` exist
4. **Validation Now Strict**: If `limit_minutes` is configured and timing data is missing, redemption is blocked

## Related Fixes

This follows the same pattern as the **Location Validation Flat Format Fix**:
1. Identify redundant manual validation
2. Verify Specification already handles it correctly
3. Remove manual validation from ProcessRedemption
4. Delegate to Unified Validation Gateway
5. Ensure single source of truth

See: `docs/LOCATION_VALIDATION_FLAT_FORMAT_FIX.md`
