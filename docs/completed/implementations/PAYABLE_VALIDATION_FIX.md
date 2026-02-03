# Payable Validation Fix

## Problem

Vouchers configured with "Payable To (Vendor Alias)" validation were not enforcing the restriction. Any user could redeem vouchers regardless of their vendor alias.

**Example:**
- Voucher `P6S9` created with `validation_payable: "V00NA"`
- JSON preview showed correct value: `"payable": "V00NA"`
- Database stored: `"payable": null`
- User with vendor alias "BB" was able to redeem it (should have been blocked)

## Root Cause

There was a **type mismatch** between frontend and backend:

1. **Frontend** (`CreateV2.vue` line 580):
   - Sends `validation_payable` as a **string** (e.g., `"TESTSHOP"`, `"V00NA"`)
   - Uses datalist with `alias.alias` (the string value)

2. **Backend** (`VoucherInstructionsData.php` line 47 - BEFORE FIX):
   ```php
   'cash.validation.payable' => 'nullable|integer|exists:vendor_aliases,id',
   ```
   - Expected an **integer** (vendor alias ID from database)
   - Validation failed silently when receiving a string
   - Value became `null` after validation failure

3. **Database**:
   - Stored `payable: null` due to validation failure
   
4. **Unified Validation Gateway** (`PayableSpecification.php`):
   - Saw `null`, correctly returned `true` (no restriction)
   - Specification logic was correct, but data was wrong

## The Fix

The bug had **three layers** that all needed fixing:

### Fix 1: Package validation rule (line 47)
Changed the validation rule in the package DTO to accept **string** values:

```php
// packages/voucher/src/Data/VoucherInstructionsData.php line 47

// BEFORE:
'cash.validation.payable' => 'nullable|integer|exists:vendor_aliases,id',

// AFTER:
'cash.validation.payable' => 'nullable|string',
```

### Fix 2: Host app action (lines 208, 307)
Added the missing `payable` field to the host app's voucher generation action:

```php
// app/Actions/Api/Vouchers/GenerateVouchers.php

// Line 208 - Add validation rule:
'validation_payable' => 'nullable|string',

// Line 307 - Add to toInstructions() method:
'validation' => [
    'secret' => $validated['validation_secret'] ?? null,
    'mobile' => $validated['validation_mobile'] ?? null,
    'payable' => $validated['validation_payable'] ?? null,  // ← Added this
    'country' => config('instructions.cash.validation_rules.country', 'PH'),
    'location' => null,
    'radius' => null,
],
```

### Fix 3: API documentation (line 80)
Added BodyParameter documentation:

```php
#[BodyParameter('validation_payable', description: '*optional* - Restrict redemption to specific vendor alias (B2B vouchers). Only users with matching vendor alias can redeem.', type: 'string', example: 'TESTSHOP')]
```

### Fix 4: DisburseController redemption path (lines 9, 16, 162-171, 192-209)
Added Unified Validation Gateway to the `/disburse` (Form Flow) redemption path:

```php
// app/Http/Controllers/Disburse/DisburseController.php

// Added imports:
use App\Services\VoucherRedemptionService;
use LBHurtado\Voucher\Exceptions\RedemptionException;

// Added validation before redemption (line 162-171):
try {
    // Validate using Unified Validation Gateway
    $service = new VoucherRedemptionService();
    $context = $service->resolveContextFromArray([
        'mobile' => $mobile,
        'secret' => $flatData['secret'] ?? null,
        'inputs' => $inputs,
        'bank_account' => $bankAccount,
    ], auth()->user());
    
    $service->validateRedemption($voucher, $context);
    
    // ... rest of redemption
    ProcessRedemption::run($voucher, $phoneNumber, $inputs, $bankAccount);
    
} catch (RedemptionException $e) {
    // Handle validation failures
    Log::warning('[DisburseController] Validation failed');
    return redirect()->route('disburse.start')->withErrors(['code' => $e->getMessage()]);
}
```

## Why This Approach

### Option 1: Change backend to accept string (CHOSEN) ✅
- **Pros:**
  - Simpler - vendor alias is just a string identifier
  - No need for database lookups during voucher generation
  - More flexible - can use vendor aliases that don't exist yet in database
  - Matches how the field is used (as a text identifier, not a foreign key)
- **Cons:**
  - No database-level referential integrity

### Option 2: Change frontend to send ID (NOT CHOSEN) ❌
- **Pros:**
  - Database referential integrity
  - Ensures vendor alias exists
- **Cons:**
  - More complex frontend logic (need to look up ID from alias)
  - Breaks if vendor alias is deleted
  - Overly restrictive - vouchers should be valid even if alias changes

## Verification

After the fix, test by:

1. **Generate a voucher** with "Payable To" set to a specific vendor alias (e.g., "TESTSHOP")
2. **Check database**:
   ```bash
   php artisan tinker --execute="\$v = \LBHurtado\Voucher\Models\Voucher::latest()->first(); echo \$v->instructions->cash->validation->payable;"
   ```
   Should output: `TESTSHOP` (not `null`)

3. **Try to redeem as wrong user**:
   - Log in as user with vendor alias "BB"
   - Try to redeem voucher payable to "TESTSHOP"
   - Should be **rejected** with error message

4. **Try to redeem as correct user**:
   - Log in as user with vendor alias "TESTSHOP"
   - Redeem voucher
   - Should **succeed**

## Related Code

- **Specification**: `packages/voucher/src/Specifications/PayableSpecification.php`
  - Performs case-insensitive string comparison
  - Returns `true` if no restriction (`payable: null`)
  - Returns `false` if vendor alias doesn't match

- **Validation Service**: `packages/voucher/src/Services/VoucherRedemptionService.php`
  - Uses `RedemptionGuard` which applies all specifications
  - Passes `RedemptionContext` with `vendorAlias` from user

- **Redemption Paths** (all use Unified Validation Gateway):
  1. `app/Http/Controllers/Redeem/RedeemController.php` - Web redemption (/redeem)
  2. `app/Http/Controllers/Disburse/DisburseController.php` - Form Flow redemption (/disburse) ✅ FIXED
  3. `app/Actions/Payment/PayWithVoucher.php` - Authenticated payment
  4. `packages/voucher/src/Http/Controllers/Api/SubmitWallet.php` - API wallet submit
  5. `packages/voucher/src/Http/Controllers/Api/ConfirmRedemption.php` - API confirm

## Impact

- **Before fix**: Payable validation was silently ignored - security vulnerability
- **After fix**: Vouchers correctly enforce vendor alias restrictions - B2B vouchers work as designed

## Testing

Run unit tests to verify specifications:
```bash
php artisan test --filter=PayableSpecification
php artisan test --filter=RedemptionGuard
```

All redemption validation tests should pass:
```bash
php artisan test packages/voucher/tests
```
