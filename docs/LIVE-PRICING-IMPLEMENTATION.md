# Live Pricing Implementation

## Overview
This document describes the implementation of real-time pricing calculation for voucher generation. When users configure voucher instructions (amount, input fields, feedback channels, rider, etc.), the system calculates and displays the cost breakdown in real-time with a 500ms debounce.

## Features
- ✅ Real-time cost calculation via API
- ✅ 500ms debounced API calls to reduce server load
- ✅ Reactive to ALL instruction fields (amount, count, inputs, feedback, rider, prefix, mask, TTL)
- ✅ Admin pricing management page
- ✅ Per-feature pricing configuration

## Architecture

### Frontend Components

#### `VoucherInstructionsForm.vue`
**Location**: `resources/js/components/voucher/forms/VoucherInstructionsForm.vue`

Main form component that:
- Collects all voucher instruction data
- Calls `useChargeBreakdown` composable for live pricing
- Displays cost breakdown with formatted prices
- Used in: Settings > Campaigns > Create/Edit

**Key Features**:
- Converts flat form structure to nested `VoucherInstructionsData` structure
- Reactive computed `instructionsForPricing` triggers pricing updates
- Shows pricing breakdown in collapsible card

#### `InputFieldsForm.vue`
**Location**: `resources/js/components/voucher/forms/InputFieldsForm.vue`

Checkbox-based input field selector component.

**Fix Applied**: Changed event binding from `@update:checked` to `@click` to work with reka-ui Checkbox component.

```vue
<!-- Before (didn't work) -->
<Checkbox @update:checked="toggleField(option.value)" />

<!-- After (works) -->
<Checkbox @click="() => toggleField(option.value)" />
```

### Frontend Composables

#### `useChargeBreakdown`
**Location**: `resources/js/composables/useChargeBreakdown.ts`

Composable that handles live pricing API calls.

**Key Implementation Details**:
1. **Computed Payload Pattern** (inspired by x-change codebase):
   ```ts
   const payload = computed(() => instructions.value);
   ```
   Creates an intermediate computed ref to ensure Vue's reactivity system properly tracks nested changes.

2. **Inline Debounce**:
   ```ts
   watch(
       payload,
       debounce(() => {
           calculateCharges();
       }, 500),
       { deep: true, immediate: true }
   );
   ```
   Uses lodash `debounce` directly in the watch callback (not as a separate function).

3. **Returns**:
   - `breakdown`: Ref containing charge breakdown array
   - `loading`: Loading state
   - `error`: API error if any
   - `calculateCharges`: Manual trigger function

### Backend Services

#### `InstructionCostEvaluator`
**Location**: `app/Services/InstructionCostEvaluator.php`

Evaluates instruction data and calculates charges based on `InstructionItem` pricing.

**Key Fix**: Handle `inputs.fields` array containing enum objects:

```php
// Handle inputs.fields specially - it's an array of enum objects or strings
if (str_starts_with($item->index, 'inputs.fields.')) {
    $fieldName = str_replace('inputs.fields.', '', $item->index);
    $selectedFieldsRaw = data_get($source, 'inputs.fields', []);
    
    // Extract string values from enum objects
    $selectedFields = collect($selectedFieldsRaw)->map(function ($field) {
        // If it's an enum object like {VoucherInputField: "email"}, extract the value
        if (is_array($field) || is_object($field)) {
            $values = collect((array) $field)->values();
            return $values->first(); // Get the first (and only) value
        }
        return $field;
    })->filter()->toArray();
    
    // Case-insensitive comparison (enum values are uppercase)
    $isSelected = in_array(strtoupper($fieldName), array_map('strtoupper', $selectedFields));
    
    $value = $isSelected ? $fieldName : null;
}
```

**Why This Was Needed**:
- Frontend sends `inputs.fields` as array of `VoucherInputField` enum objects: `[{VoucherInputField: "email"}]`
- Enum values are UPPERCASE (`"EMAIL"`) but database indices use lowercase (`"inputs.fields.email"`)
- Need to extract string value from enum object and compare case-insensitively

#### `InstructionItemRepository`
**Location**: `app/Repositories/InstructionItemRepository.php`

Repository for fetching pricing items from database.

#### `ChargeCalculationController`
**Location**: `app/Http/Controllers/Api/ChargeCalculationController.php`

API endpoint: `POST /api/v1/calculate-charges`

Accepts voucher instructions JSON and returns charge breakdown.

### Admin Pricing Management

#### Pricing List Page
**Route**: `/admin/pricing`  
**Component**: `resources/js/pages/admin/pricing/Index.vue`

Shows all instruction items with their prices. Accessible via sidebar "Pricing" link.

#### Pricing Edit Page
**Route**: `/admin/pricing/{item}/edit`  
**Component**: `resources/js/pages/admin/pricing/Edit.vue`

Edit individual pricing item with:
- Price input (in PHP)
- Label and description
- Reason for price change (required)
- Price history view

#### Database Seeder
**Seeder**: `database/seeders/InstructionItemSeeder.php`

Seeds pricing for:
- `cash.amount` - ₱20.00
- `feedback.email` - ₱1.00
- `feedback.mobile` - ₱1.80
- `feedback.webhook` - ₱1.90
- `inputs.fields.*` - ₱2.20 to ₱4.00 per field
- `rider.message` - ₱2.00
- `rider.url` - ₱2.10

**Run**: `php artisan db:seed --class=InstructionItemSeeder`

### Navigation

**Sidebar Link**: `resources/js/components/AppSidebar.vue`

"Pricing" link added to main navigation (visible to all users).

**Route Protection**: Backend enforces `super-admin` role + `manage pricing` permission.

**Grant Permission**:
```bash
php artisan tinker
$user = App\Models\User::where('email', 'admin@redeem.test')->first();
$user->givePermissionTo('manage pricing');
```

## Troubleshooting

### Issue: Pricing only reactive to amount/count, not other fields

**Cause**: Vue reactivity not tracking computed property changes properly.

**Solution**: Create intermediate computed ref for instructions (x-change pattern):
```ts
const payload = computed(() => instructions.value);
watch(payload, debounce(() => { ... }));
```

### Issue: Checkbox clicks not registering

**Cause**: reka-ui Checkbox doesn't emit `update:checked` event.

**Solution**: Use `@click` event instead:
```vue
<Checkbox @click="() => toggleField(option.value)" />
```

### Issue: Input fields not charged even though selected

**Cause**: 
1. Enum values are UPPERCASE (`"EMAIL"`)
2. Database indices are lowercase (`"inputs.fields.email"`)
3. Array comparison failed

**Solution**: Case-insensitive string comparison in evaluator (see code above).

### Issue: 403 Forbidden when accessing /admin/pricing

**Cause**: User doesn't have `manage pricing` permission.

**Solution**: Grant permission via tinker (see above).

## Testing

### Manual Testing

1. **Navigate to Settings > Campaigns > Create**
2. **Set Amount**: 50
3. **Expected**: Cost Breakdown shows ₱20.00 (base)
4. **Click Email checkbox**
5. **Expected**: Cost Breakdown updates to ₱21.00 (base + email)
6. **Add Feedback Email**: `test@example.com`
7. **Expected**: Cost Breakdown updates to ₱22.00 (base + input email + feedback email)
8. **Check Network Tab**: Should see `POST /api/v1/calculate-charges` with 500ms debounce

### Browser Console Logs

When functioning correctly, you should see:
```
[VoucherInstructionsForm] localValue changed
[VoucherInstructionsForm] instructionsForPricing computed called
[useChargeBreakdown] payload computed called
[useChargeBreakdown] Watch triggered
[useChargeBreakdown] Calculating charges for: {...}
[Axios] POST /api/v1/calculate-charges
[useChargeBreakdown] Response received: {breakdown: [...], total: 2100}
```

### Laravel Logs

Check `storage/logs/laravel.log` for:
```
[InstructionCostEvaluator] Starting evaluation
[InstructionCostEvaluator] Checking input field: email
[InstructionCostEvaluator] ✅ Chargeable instruction
```

## Future Enhancements

- [ ] Add pricing tiers based on user subscription level
- [ ] Volume discounts for large voucher batches
- [ ] Currency conversion support
- [ ] Pricing preview in voucher generation page (not just campaigns)
- [ ] Price history chart in admin panel
- [ ] Bulk price update tool

## Related Documentation

- [PRICING-TODO.md](./PRICING-TODO.md) - Original implementation plan
- [IMPLEMENTATION-SUMMARY.md](./IMPLEMENTATION-SUMMARY.md) - Overall pricing system summary
- [PRICING-TESTING-GUIDE.md](./PRICING-TESTING-GUIDE.md) - Testing procedures
