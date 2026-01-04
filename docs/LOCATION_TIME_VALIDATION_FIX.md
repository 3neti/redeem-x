# Location and Time Validation Fix

## Problem

Vouchers with location and time validation settings were not being saved correctly during generation. The validation data was lost, causing vouchers to be redeemable without location/time checks.

**Example:**
- Voucher `58XQ` created with location and time validation
- JSON preview showed correct validation settings
- Database stored: `validation: null` (both location and time lost)
- Voucher was redeemed successfully without providing location or respecting time limits

## Root Cause

Same pattern as the payable validation issue - the host app's `GenerateVouchers` action was missing:
1. Validation rules for `validation_location` and `validation_time` parameters
2. Transformation logic in `toInstructions()` method to include validation data

The frontend was sending the data correctly, but the backend wasn't processing it.

## The Fix

### Fix 1: Add Validation Rules (lines 232-247)
Added comprehensive validation rules for location and time parameters:

```php
// app/Actions/Api/Vouchers/GenerateVouchers.php

// Location validation
'validation_location' => 'nullable|array',
'validation_location.required' => 'nullable|boolean',
'validation_location.target_lat' => 'required_with:validation_location|numeric|between:-90,90',
'validation_location.target_lng' => 'required_with:validation_location|numeric|between:-180,180',
'validation_location.radius_meters' => 'required_with:validation_location|integer|min:1|max:10000',
'validation_location.on_failure' => 'required_with:validation_location|in:block,warn',

// Time validation
'validation_time' => 'nullable|array',
'validation_time.window' => 'nullable|array',
'validation_time.window.start_time' => 'required_with:validation_time.window|date_format:H:i',
'validation_time.window.end_time' => 'required_with:validation_time.window|date_format:H:i',
'validation_time.window.timezone' => 'required_with:validation_time.window|string|timezone',
'validation_time.limit_minutes' => 'nullable|integer|min:1|max:1440',
'validation_time.track_duration' => 'nullable|boolean',
```

### Fix 2: Add Transformation Logic (lines 348-365)
Added `validation` key to the data transformation:

```php
// app/Actions/Api/Vouchers/GenerateVouchers.php toInstructions()

'validation' => isset($validated['validation_location']) || isset($validated['validation_time']) ? [
    'location' => isset($validated['validation_location']) ? [
        'required' => $validated['validation_location']['required'] ?? true,
        'target_lat' => $validated['validation_location']['target_lat'],
        'target_lng' => $validated['validation_location']['target_lng'],
        'radius_meters' => $validated['validation_location']['radius_meters'],
        'on_failure' => $validated['validation_location']['on_failure'],
    ] : null,
    'time' => isset($validated['validation_time']) ? [
        'window' => isset($validated['validation_time']['window']) ? [
            'start_time' => $validated['validation_time']['window']['start_time'],
            'end_time' => $validated['validation_time']['window']['end_time'],
            'timezone' => $validated['validation_time']['window']['timezone'],
        ] : null,
        'limit_minutes' => $validated['validation_time']['limit_minutes'] ?? null,
        'track_duration' => $validated['validation_time']['track_duration'] ?? true,
    ] : null,
] : null,
```

### Fix 3: API Documentation (lines 95-96)
Added comprehensive BodyParameter documentation:

```php
#[BodyParameter('validation_location', description: '*optional* - Location validation settings...')]
#[BodyParameter('validation_time', description: '*optional* - Time validation settings...')]
```

## How It Works

### Location Validation
When a voucher is created with location validation:
```json
{
  "validation_location": {
    "required": true,
    "target_lat": 14.5995,
    "target_lng": 120.9842,
    "radius_meters": 1000,
    "on_failure": "block"
  }
}
```

The `LocationSpecification` will:
1. Check if `location` input was provided during redemption
2. Calculate distance using Haversine formula
3. Compare against `radius_meters`
4. Block redemption if outside radius (when `on_failure: "block"`)
5. Store validation results in voucher metadata for audit

### Time Validation
When a voucher is created with time validation:

**Time Window:**
```json
{
  "validation_time": {
    "window": {
      "start_time": "09:00",
      "end_time": "17:00",
      "timezone": "Asia/Manila"
    }
  }
}
```

The `TimeWindowSpecification` validates redemption occurs during allowed hours.

**Time Limit:**
```json
{
  "validation_time": {
    "limit_minutes": 1440,
    "track_duration": true
  }
}
```

The `TimeLimitSpecification` validates redemption occurs within specified minutes from voucher creation.

## Testing

### Test Location Validation
```bash
# 1. Generate voucher with location validation
curl -X POST https://api.example.com/vouchers \
  -d '{
    "amount": 50,
    "count": 1,
    "validation_location": {
      "target_lat": 14.5995,
      "target_lng": 120.9842,
      "radius_meters": 1000,
      "on_failure": "block"
    }
  }'

# 2. Check database
php artisan tinker --execute="
  \$v = \LBHurtado\Voucher\Models\Voucher::latest()->first();
  echo json_encode(\$v->instructions->validation->location->toArray(), JSON_PRETTY_PRINT);
"

# Should output location validation settings

# 3. Try to redeem from wrong location → BLOCKED
# 4. Try to redeem from correct location → SUCCESS
```

### Test Time Validation
```bash
# 1. Generate voucher with 5-minute time limit
curl -X POST https://api.example.com/vouchers \
  -d '{
    "amount": 50,
    "count": 1,
    "validation_time": {
      "limit_minutes": 5,
      "track_duration": true
    }
  }'

# 2. Check database
php artisan tinker --execute="
  \$v = \LBHurtado\Voucher\Models\Voucher::latest()->first();
  echo json_encode(\$v->instructions->validation->time->toArray(), JSON_PRETTY_PRINT);
"

# Should output time validation settings

# 3. Try to redeem immediately → SUCCESS
# 4. Wait 6 minutes, try to redeem → BLOCKED
```

## Integration with Form Flow

The Form Flow Manager (`/disburse` path) will automatically:
1. Detect `location` in `inputs.fields` array
2. Present location capture page with GPS and address collection
3. Pass location data to `DisburseController::redeem()`
4. Validation gateway checks against `validation.location` settings

**Note:** Time validation happens automatically in the background during redemption - no UI needed.

## Related Components

- **Specifications** (already implemented and tested):
  - `LocationSpecification.php` - Validates GPS coordinates
  - `TimeWindowSpecification.php` - Validates time windows
  - `TimeLimitSpecification.php` - Validates redemption duration

- **Validation Service**:
  - `VoucherRedemptionService.php` - Orchestrates all validations

- **All Redemption Paths** now properly validate:
  - `/redeem` (legacy)
  - `/disburse` (primary)
  - Authenticated payment
  - API endpoints

## Impact

- **Before fix**: Location and time validation silently ignored - security vulnerability
- **After fix**: Vouchers correctly enforce location and time restrictions - geofencing and time-limited campaigns work as designed

## Branch

This fix was implemented in branch: `feature/fix-location-time-validation-generation`
