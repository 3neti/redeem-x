# Location Input Requirement Fix

**Date**: 2026-01-04  
**Issue**: Vouchers with location input requirement were incorrectly rejecting valid redemptions

## Problem Statement

When a voucher is generated with:
- **Location input field** enabled (user provides their GPS coordinates)
- Form flow collects location data successfully (`latitude`, `longitude`)

The redemption fails with error: "Location data is required for this voucher."

This occurred because `LocationSpecification` only checked for **geofence validation** (validating user is within a specific radius), but did not handle the **location input requirement** (simply verifying location data was collected).

## Root Cause

`LocationSpecification.php` had only one validation mode:
- **Geofence Validation**: Check if `validation.location` exists with target coordinates and radius

It did NOT check if location was required as an **input field** via `inputs.fields`.

The `InputsSpecification` deliberately skips `location` (marked as "special field") because it assumes `LocationSpecification` handles it - but LocationSpecification wasn't checking for input requirements.

## Solution

Updated `LocationSpecification` to handle **two distinct scenarios**:

### 1. Location Input Requirement
Checks if `location` is in `inputs.fields` array:
```php
private function isLocationInputRequired(object $voucher): bool
{
    $requiredFields = $voucher->instructions->inputs->fields ?? [];
    
    foreach ($requiredFields as $field) {
        $fieldValue = $field instanceof VoucherInputField ? $field->value : $field;
        if ($fieldValue === 'location') {
            return true;
        }
    }
    
    return false;
}
```

If required, validates location data exists in context:
```php
private function hasLocationData(RedemptionContext $context): bool
{
    $location = $context->inputs['location'] ?? null;
    
    if (!$location || !is_array($location)) {
        return false;
    }
    
    $lat = $location['lat'] ?? $location['latitude'] ?? null;
    $lng = $location['lng'] ?? $location['longitude'] ?? null;
    
    return $lat !== null && $lng !== null;
}
```

### 2. Geofence Validation (Existing)
Validates user location is within required radius of target coordinates.

## Files Modified

1. **`packages/voucher/src/Specifications/LocationSpecification.php`**
   - Added `isLocationInputRequired()` method
   - Added `hasLocationData()` method
   - Updated `passes()` to check both scenarios
   - Added import: `use LBHurtado\Voucher\Enums\VoucherInputField;`

2. **`app/Services/VoucherRedemptionService.php`**
   - Updated error message: "Location data is required for this voucher."
   - Works for both input requirement and geofence validation failures

3. **`tests/Unit/Specifications/LocationSpecificationTest.php`**
   - Added 7 new tests for location input requirement scenario
   - Updated helper functions to include `inputs` field
   - Added `createVoucherWithInputFields()` helper

## Test Coverage

### New Tests (7)
1. ✅ Passes when location input field is not required
2. ✅ Fails when location input is required but not provided
3. ✅ Passes when location input is required and provided with `lat`/`lng`
4. ✅ Passes when location input is required and provided with `latitude`/`longitude`
5. ✅ Fails when location input has invalid data structure
6. ✅ Fails when location input has missing coordinates
7. ✅ Passes when location input is required along with other fields

### Existing Tests (11)
All existing geofence validation tests still pass.

**Total**: 18 tests with 19 assertions ✅

## Data Flow

### Form Flow → DisburseController → Validation

1. **Form flow collects location**:
   ```json
   {
     "location": {
       "latitude": 14.646978377019021,
       "longitude": 121.02890014581956
     }
   }
   ```

2. **DisburseController maps to inputs** (`DisburseController.php:183`):
   ```php
   $inputs = collect($flatData)
       ->except(['mobile', 'recipient_country', 'bank_code', 'account_number', 'amount', 'settlement_rail'])
       ->toArray();
   ```

3. **VoucherRedemptionService creates context** (`VoucherRedemptionService.php:189`):
   ```php
   $context = $service->resolveContextFromArray([
       'mobile' => $mobile,
       'inputs' => $inputs,  // Contains location data
   ]);
   ```

4. **LocationSpecification validates** (`LocationSpecification.php:20-24`):
   ```php
   if ($this->isLocationInputRequired($voucher)) {
       if (!$this->hasLocationData($context)) {
           return false; // Location data is required but not provided
       }
   }
   ```

## Usage Example

### Voucher Generation (Frontend)
```json
{
  "cash": {
    "amount": 500,
    "currency": "PHP"
  },
  "inputs": {
    "fields": ["location"]  // Require location input
  }
}
```

### Form Flow Collection
User provides GPS coordinates via location handler:
```json
{
  "latitude": 14.5547,
  "longitude": 121.0244
}
```

### Validation (Backend)
```php
// Voucher requires location input
$voucher->instructions->inputs->fields // ['location']

// User provided location via form flow
$context->inputs['location'] // ['latitude' => 14.5547, 'longitude' => 121.0244]

// LocationSpecification validates
$spec->passes($voucher, $context) // ✅ true
```

## Scenarios Covered

| Scenario | inputs.fields | validation.location | Behavior |
|----------|---------------|---------------------|----------|
| No location requirement | `[]` | `null` | ✅ Pass |
| Location input only | `['location']` | `null` | ✅ Check data collected |
| Geofence validation only | `[]` | `{coordinates, radius}` | ✅ Check within radius |
| Both requirements | `['location']` | `{coordinates, radius}` | ✅ Check data collected AND within radius |

## Related Issues

This follows the same pattern as:
- **Payable validation data loss** (fixed 2026-01-04)
- **Time validation data loss** (fixed 2026-01-04)

All three issues stemmed from specifications expecting data that wasn't being saved during voucher generation, or not checking for input requirements vs validation rules.

## Git Commit

```bash
git commit -m "fix: LocationSpecification now handles location input requirement

- Added isLocationInputRequired() to check if location is in inputs.fields
- Added hasLocationData() to validate location data exists in context
- Updated passes() to handle both input requirement and geofence validation
- Added 7 tests for location input requirement scenario
- All 18 tests passing

Fixes issue where vouchers with location input field were incorrectly
rejecting redemptions even when form flow collected valid GPS coordinates.

Co-Authored-By: Warp <agent@warp.dev>"
```

## Testing

```bash
# Run location specification tests
php artisan test tests/Unit/Specifications/LocationSpecificationTest.php

# Expected output:
# Tests:    18 passed (19 assertions)
```

## References

- Location Input Handler: `packages/form-handler-location/`
- Form Flow Manager: `packages/form-flow-manager/`
- DisburseController: `app/Http/Controllers/Disburse/DisburseController.php`
- VoucherRedemptionService: `app/Services/VoucherRedemptionService.php`
