# Location Validation Flat Format Fix

## Problem

Vouchers with location input requirements were failing redemption even when form flow successfully collected GPS coordinates. The issue affected vouchers like YULB and S575.

### Root Cause

Form flow's `mapCollectedData()` method flattens nested location data:

```php
// Form flow collects (nested):
{"location": {"latitude": 14.640, "longitude": 121.030}}

// After mapCollectedData() flattening (flat):
{"latitude": 14.640, "longitude": 121.030}
```

**The bug**: `ProcessRedemption::validateLocation()` only accepted **nested format**, not the **flat format** that form flow was providing.

## Solution

Removed redundant manual location validation from `ProcessRedemption` and let the Unified Validation Gateway handle it through `LocationSpecification`, which already supports both formats.

### Changes Made

#### 1. LocationSpecification Already Handled Both Formats ✅

The `LocationSpecification` class already had logic to handle both nested and flat formats:

```php
// packages/voucher/src/Specifications/LocationSpecification.php

private function hasLocationData(RedemptionContext $context): bool
{
    // Check nested format: inputs['location']['latitude']
    $location = $context->inputs['location'] ?? null;
    if ($location && is_array($location)) {
        $lat = $location['lat'] ?? $location['latitude'] ?? null;
        $lng = $location['lng'] ?? $location['longitude'] ?? null;
        if ($lat !== null && $lng !== null) return true;
    }
    
    // Check flat format: inputs['latitude']  
    $lat = $context->inputs['lat'] ?? $context->inputs['latitude'] ?? null;
    $lng = $context->inputs['lng'] ?? $context->inputs['longitude'] ?? null;
    return $lat !== null && $lng !== null;
}
```

#### 2. Removed Redundant Validation from ProcessRedemption ✅

The manual `validateLocation()` method in `ProcessRedemption` was duplicating validation and only accepting nested format:

```php
// app/Actions/Voucher/ProcessRedemption.php (REMOVED)

protected function validateLocation(Voucher $voucher, array $inputs): void
{
    // This only checked for nested format: inputs['location']
    if (!isset($inputs['location']) || !is_array($inputs['location'])) {
        throw new \RuntimeException('Location data is required for this voucher.');
    }
    // ... rest of manual validation
}
```

**After fix**: Removed the entire `validateLocation()` method. Location validation is now handled exclusively by `LocationSpecification` in the Unified Validation Gateway.

## Testing

### Unit Tests

All 22 LocationSpecification tests pass, including:

- ✅ Nested format with `lat`/`lng` keys
- ✅ Nested format with `latitude`/`longitude` keys  
- ✅ Flat format at root level (form flow format)
- ✅ Geofence validation with both formats
- ✅ Input requirement validation with both formats

```bash
php artisan test tests/Unit/Specifications/LocationSpecificationTest.php
# Tests:    22 passed (23 assertions)
```

### Real-World Verification

**Voucher S575** successfully redeemed with location validation:

- **Required location**: 14.64014, 121.0304
- **Actual location**: 14.64161656174, 121.03040077106
- **Distance**: 164.19 meters (0.164 km)
- **Allowed radius**: 1010 meters (1.01 km)
- **Result**: ✓ Within range - Redemption allowed

## Architecture

### Before Fix

```
ProcessRedemption
├── validateKYC()
├── validateLocation() ❌ MANUAL (nested format only)
├── validateTime()
└── RedeemVoucher::run()
    └── RedemptionGuard
        └── LocationSpecification ✅ (both formats)
```

**Problem**: Duplicate validation with different format support

### After Fix

```
ProcessRedemption
├── validateKYC()
├── validateTime()
└── RedeemVoucher::run()
    └── RedemptionGuard
        └── LocationSpecification ✅ (both formats)
```

**Solution**: Single source of truth through Unified Validation Gateway

## Benefits

1. **No Duplication**: Location validation logic exists in one place only
2. **Format Agnostic**: Handles both nested and flat formats correctly
3. **Consistent**: All validation goes through the Unified Validation Gateway
4. **Maintainable**: Changes to location validation only need to happen in `LocationSpecification`

## Related Files

- `packages/voucher/src/Specifications/LocationSpecification.php` - Handles both formats
- `app/Actions/Voucher/ProcessRedemption.php` - Removed redundant validation
- `packages/voucher/src/Guards/RedemptionGuard.php` - Unified Validation Gateway
- `tests/Unit/Specifications/LocationSpecificationTest.php` - 22 passing tests

## Date

2026-01-04
