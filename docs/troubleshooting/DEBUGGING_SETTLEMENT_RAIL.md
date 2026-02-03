# Debugging Guide: Settlement Rail Feature Implementation

## Problem Summary
Settlement rail selection (INSTAPAY/PESONET) was not being applied during voucher disbursement despite being configured in the UI.

## Root Causes Discovered

### 1. **Multiple Entry Points** (Critical Finding)
The application has **two separate endpoints** for voucher generation:
- **Web Route**: `POST /vouchers/generate` → `VoucherGenerationRequest` → `GenerateController@store`
- **API Route**: `POST /api/v1/vouchers` → `GenerateVouchers` action

**Lesson**: When adding new fields to forms, check ALL possible entry points. The frontend was using the API route, not the web route.

### 2. **Backend Validation Chain**
Three separate validation points needed updating:
1. `VoucherGenerationRequest.php` (web endpoint)
2. `GenerateVouchers.php` action (API endpoint) ← **This was the missing piece**
3. `DisburseInputData::fromVoucher()` (enum handling)

**Lesson**: Follow the data flow from frontend → validation → storage → retrieval → processing.

### 3. **Enum Casting Issues**
The `settlement_rail` field is cast to `SettlementRail` enum in `CashInstructionData`, but the retrieval code was trying to access `->value` on a potentially null value:

```php
// ❌ Wrong - causes null pointer exception
$instructionRail = $voucher->instructions?->cash?->settlement_rail?->value ?? null;

// ✅ Correct - check instance first
$settlementRailEnum = $voucher->instructions?->cash?->settlement_rail ?? null;
if ($settlementRailEnum instanceof \LBHurtado\PaymentGateway\Enums\SettlementRail) {
    $via = $settlementRailEnum->value;
}
```

**Lesson**: Always use `instanceof` checks before accessing enum properties when dealing with nullable enum casts.

## Debugging Methodology

### Step 1: Verify Frontend → Backend Data Flow
```javascript
// Add console.log before API call
console.log('[DEBUG] Settlement Rail Value:', settlementRail.value, typeof settlementRail.value);
```

**Finding**: Frontend was correctly sending `"PESONET"` as a string.

### Step 2: Check Database Storage
```bash
php artisan tinker --execute="
\$voucher = \LBHurtado\Voucher\Models\Voucher::where('code', 'CODE')->first();
echo json_encode(\$voucher->instructions->cash->toArray(), JSON_PRETTY_PRINT);
"
```

**Finding**: `settlement_rail` was `null` in database, indicating backend wasn't accepting the field.

### Step 3: Check Backend Logs
```bash
tail -100 storage/logs/laravel.log | grep "settlement_rail\|DisburseInputData"
```

**Finding**: Logs showed "Auto-selected rail based on amount" instead of "Using rail from voucher instructions".

### Step 4: Check API Response
Browser Network Tab → POST /api/v1/vouchers → Response

**Finding**: API returned success with `settlement_rail: null` in the voucher instructions, confirming backend validation was rejecting the field.

### Step 5: Trace All Validation Points
```bash
grep -r "settlement_rail" app/Http/Requests app/Actions
```

**Finding**: `VoucherGenerationRequest` had the field, but `GenerateVouchers` action (used by API) did not.

## Testing Checklist for New Form Fields

When adding new fields to forms:

- [ ] **Frontend**
  - [ ] Add field to form component (Vue/React)
  - [ ] Add to form submission data
  - [ ] Add to TypeScript types
  - [ ] Test console.log shows correct value before submission

- [ ] **Backend - Web Route**
  - [ ] Add validation rule to `FormRequest` class
  - [ ] Add to `toInstructions()` or equivalent method
  - [ ] Test with web form submission

- [ ] **Backend - API Route**
  - [ ] Add validation rule to Action's `rules()` method
  - [ ] Add to Action's `toInstructions()` or equivalent method
  - [ ] Test with API client (Postman/Insomnia)

- [ ] **Data Models**
  - [ ] Add property to DTO/Data class
  - [ ] Add enum cast if applicable
  - [ ] Update factory/seeder if exists

- [ ] **Data Retrieval**
  - [ ] Check null safety when accessing enum properties
  - [ ] Use `instanceof` checks for nullable enums
  - [ ] Add logging to trace value flow

- [ ] **End-to-End Test**
  - [ ] Create record with new field
  - [ ] Verify database storage
  - [ ] Verify field is used in business logic
  - [ ] Check logs for expected behavior

## Common Pitfalls

### 1. Assuming Single Entry Point
Don't assume there's only one controller/route handling form submissions. Modern Laravel apps often have:
- Web routes (Inertia/Blade)
- API routes (Lorisleiva Actions)
- Console commands
- Queue jobs

### 2. Frontend-Backend Mismatch
Just because the frontend sends a field doesn't mean the backend accepts it. Always verify:
- Validation rules include the field
- Field is mapped to the correct property
- Field is included in the response/database

### 3. Enum Null Safety
When using enums with nullable casting:
```php
#[WithCast(EnumCast::class)]
public ?SettlementRail $settlement_rail = null;
```

Always check `instanceof` before accessing `->value`:
```php
if ($enum instanceof EnumClass) {
    $value = $enum->value;
}
```

### 4. Silent Failures
Validation errors may not bubble up to the frontend if:
- API returns success despite missing fields
- Default values mask the issue
- Logs aren't checked

**Solution**: Always check both frontend console AND backend logs.

## Debugging Commands Reference

```bash
# Check voucher instructions
php artisan tinker --execute="\$v = \LBHurtado\Voucher\Models\Voucher::where('code', 'CODE')->first(); var_dump(\$v->instructions->cash->settlement_rail);"

# Check recent logs
tail -100 storage/logs/laravel.log | grep -A5 "pattern"

# Check failed queue jobs
php artisan queue:failed

# See failed job details
php artisan tinker --execute="\$job = DB::table('failed_jobs')->latest()->first(); echo \$job->exception;"

# Find all files with a field
grep -r "field_name" app/ packages/

# Check database directly
php artisan tinker --execute="DB::table('vouchers')->where('code', 'CODE')->first();"
```

## Prevention Strategies

1. **Add Integration Tests**: Test the full flow from API request to database storage
2. **Use Type Safety**: TypeScript on frontend, strict types in PHP
3. **Consistent Naming**: Use same field names across frontend, backend, and database
4. **Comprehensive Logging**: Log at validation, storage, and retrieval points
5. **Documentation**: Update API docs when adding new fields

## Related Files Modified

### Frontend
- `resources/js/components/voucher/forms/CashInstructionForm.vue` - UI controls
- `resources/js/components/voucher/forms/VoucherInstructionsForm.vue` - Data mapping
- `resources/js/pages/vouchers/generate/Create.vue` - Generate page
- `resources/js/pages/settings/campaigns/Create.vue` - Campaign create
- `resources/js/pages/settings/campaigns/Edit.vue` - Campaign edit
- `resources/js/types/voucher.d.ts` - TypeScript types

### Backend - Web
- `app/Http/Requests/VoucherGenerationRequest.php` - Web form validation

### Backend - API
- `app/Actions/Api/Vouchers/GenerateVouchers.php` - API validation ← **Critical**

### Backend - Data
- `packages/voucher/src/Data/CashInstructionData.php` - Enum casting
- `packages/payment-gateway/src/Data/Disburse/DisburseInputData.php` - Enum retrieval

### Backend - Services
- `packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php` - Fee tracking
- `packages/voucher/src/Services/FeeCalculator.php` - Fee strategy execution

## Success Metrics

✅ Frontend shows settlement rail dropdown
✅ Frontend console logs correct value
✅ API response includes settlement_rail field
✅ Database stores settlement_rail value
✅ Logs show "Using rail from voucher instructions"
✅ NetBank dashboard shows correct settlement rail (INSTAPAY/PESONET)

## Timeline

- **Total debugging time**: ~2 hours
- **Root cause identified**: After checking API action validation
- **Fix complexity**: Simple (add 2 validation rules + 2 lines in toInstructions)
- **Why it took so long**: Assumed web route was being used, didn't check API route first

## Key Takeaway

**Always check if your frontend is using Web routes or API routes**. In this case:
- Form looked like a traditional web form (using Inertia)
- But it was actually making API calls via `useVoucherApi()` composable
- Web route validation was complete, but API route validation was missing

**Pro tip**: Search for the actual function being called in form submission (`generateVouchers` in this case) to find which route/controller is handling the request.
