# TODO: Fix Location and Time Validation in Voucher Generation

## Problem

Vouchers with location and time validation settings are not being saved correctly. The validation data is lost during voucher generation, causing vouchers to be redeemable without location/time checks.

**Example:**
- Voucher `58XQ` created with location and time validation
- JSON preview showed correct validation settings
- Database stored: `validation: null` (both location and time lost)
- Voucher was redeemed successfully without providing location or respecting time limits

## Root Cause

Same issue as the payable validation fix - the host app's `GenerateVouchers` action is missing:
1. Validation rules for `validation_location` and `validation_time` parameters
2. Transformation logic in `toInstructions()` method to include validation data

## Files to Fix

### 1. Add Validation Rules
**File:** `app/Actions/Api/Vouchers/GenerateVouchers.php`

**Line ~230** - Add after preview validation rules:

```php
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

### 2. Add to toInstructions() Method
**File:** `app/Actions/Api/Vouchers/GenerateVouchers.php`

**Line ~336** - Add `validation` key to `$data_array`:

```php
$data_array = [
    'cash' => [
        'amount' => $validated['amount'],
        'currency' => Number::defaultCurrency(),
        'validation' => [
            'secret' => $validated['validation_secret'] ?? null,
            'mobile' => $validated['validation_mobile'] ?? null,
            'payable' => $validated['validation_payable'] ?? null,
            'country' => config('instructions.cash.validation_rules.country', 'PH'),
            'location' => null,
            'radius' => null,
        ],
        'settlement_rail' => $validated['settlement_rail'] ?? null,
        'fee_strategy' => $validated['fee_strategy'] ?? 'absorb',
    ],
    'inputs' => [
        'fields' => $inputFields,
    ],
    'feedback' => [
        'email' => $validated['feedback_email'] ?? null,
        'mobile' => $validated['feedback_mobile'] ?? null,
        'webhook' => $validated['feedback_webhook'] ?? null,
    ],
    'rider' => [
        'message' => $validated['rider_message'] ?? null,
        'url' => $validated['rider_url'] ?? null,
        'redirect_timeout' => $validated['rider_redirect_timeout'] ?? null,
        'splash' => $validated['rider_splash'] ?? null,
        'splash_timeout' => $validated['rider_splash_timeout'] ?? null,
    ],
    // ADD THIS:
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
    'count' => $validated['count'],
    'prefix' => $validated['prefix'] ?? '',
    'mask' => $validated['mask'] ?? '',
    'ttl' => $ttl,
    'metadata' => $metadata,
];
```

### 3. Add API Documentation
**File:** `app/Actions/Api/Vouchers/GenerateVouchers.php`

**Line ~94** - Add after `preview_message` parameter:

```php
#[BodyParameter('validation_location', description: '*optional* - Location validation settings. Object with: required (bool), target_lat (float), target_lng (float), radius_meters (int), on_failure ("block"|"warn").', type: 'object', example: ['required' => true, 'target_lat' => 14.5995, 'target_lng' => 120.9842, 'radius_meters' => 1000, 'on_failure' => 'block'])]
#[BodyParameter('validation_time', description: '*optional* - Time validation settings. Object with: window (object with start_time, end_time, timezone), limit_minutes (int), track_duration (bool).', type: 'object', example: ['limit_minutes' => 1440, 'track_duration' => true])]
```

## Testing After Fix

1. Generate voucher with location validation
2. Check database: `validation.location` should be populated
3. Try to redeem from wrong location → should be blocked
4. Try to redeem from correct location → should succeed

5. Generate voucher with time limit validation
6. Check database: `validation.time` should be populated
7. Try to redeem after time limit → should be blocked
8. Try to redeem within time limit → should succeed

## Notes

- This is the SAME PATTERN as the payable validation fix
- The Unified Validation Gateway specifications (LocationSpecification, TimeWindowSpecification, TimeLimitSpecification) are already implemented and working
- The problem is purely in the voucher generation step - data is not being saved
- Once fixed, the validation will work automatically because the specifications are already checking the data

## Priority

**HIGH** - This is a security/business logic issue. Vouchers with location/time restrictions are not being enforced.
