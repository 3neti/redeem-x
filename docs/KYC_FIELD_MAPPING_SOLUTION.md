# KYC Field Mapping Solution

## Problem Summary

Voucher 8GFL redemption failed with "Missing required fields: Name" error after completing KYC verification.

## Root Cause Analysis

### Field Name Mismatch

**Voucher Expected Fields**:
- `kyc`
- `name`
- `birth_date`
- `address`

**KYC Handler Output** (from `form-handler-kyc` package):
- `transaction_id`
- `status`
- `full_name` (not `name`)
- `date_of_birth` (not `birth_date`)
- `address`

### Two Transformation Layers

The system has TWO places where field transformation happens:

#### Layer 1: YAML Driver Auto-Population
Location: `config/form-flow-drivers/voucher-redemption.yaml`

```yaml
# KYC step returns: full_name, date_of_birth, address
kyc:
  handler: "kyc"
  step_name: "kyc_verification"

# Bio step auto-populates and transforms field names
bio:
  handler: "form"
  step_name: "bio_fields"
  config:
    variables:
      $kyc_name: "$kyc_verification.name"
      $kyc_birth: "$kyc_verification.date_of_birth"  # ← References KYC output
  fields:
    - name: "full_name"                              # ← Still full_name
      default: "$kyc_name"
    - name: "birth_date"                             # ← Transforms to birth_date
      default: "$kyc_birth"
```

**Result**: YAML transforms `date_of_birth` → `birth_date`, but keeps `full_name` as-is.

#### Layer 2: Application Mapping
Location: `app/Services/InputFieldMapper.php`

```php
protected array $mappings = [
    'full_name' => 'name',           // ← Still needed!
    'date_of_birth' => 'birth_date', // ← Fallback (YAML handles primary)
    'otp_code' => 'otp',
];
```

**Why Both Are Needed**:
- YAML: Handles `/disburse` flow with form-flow manager
- Mapper: Handles direct API redemption + provides fallback

## Solution Implemented

### 1. Created Centralized Mapper Service

**File**: `app/Services/InputFieldMapper.php`

- Single source of truth for all field name mappings
- Injected into both redemption controllers
- Testable and documented

### 2. Updated Both Redemption Flows

#### DisburseController (Form Flow Path)
```php
protected function mapCollectedData(array $collectedData): array
{
    $mapped = [];
    
    // Flatten all steps
    foreach ($collectedData as $stepData) {
        if (is_array($stepData)) {
            $mapped = array_merge($mapped, $stepData);
        }
    }
    
    // Apply centralized mappings
    return $this->fieldMapper->map($mapped);
}
```

#### ConfirmRedemption (API Path)
```php
// Apply centralized field name mappings
$fieldMapper = app(InputFieldMapper::class);
$inputs = $fieldMapper->map($inputs);
```

### 3. Added Test Coverage

**File**: `tests/Unit/Services/InputFieldMapperTest.php`
- 6 tests covering all scenarios
- 28 assertions total
- ✅ All passing

### 4. Documented Everything

**Files Created**:
- `docs/INPUT_FIELD_MAPPING.md` - Comprehensive guide
- `docs/KYC_FIELD_MAPPING_SOLUTION.md` - This file

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    KYC Handler                              │
│              (form-handler-kyc package)                     │
│                                                             │
│  Returns: full_name, date_of_birth, address                │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┴────────────────┐
        │                                 │
        ▼                                 ▼
┌───────────────────┐          ┌──────────────────────┐
│   YAML Driver     │          │   Direct API Call    │
│   (Disburse Flow) │          │   (ConfirmRedemption)│
│                   │          │                      │
│ Bio Step:         │          │ Receives raw:        │
│ - full_name       │          │ - full_name          │
│ - birth_date ✓    │          │ - date_of_birth      │
└────────┬──────────┘          └──────────┬───────────┘
         │                                │
         ▼                                ▼
┌─────────────────────────────────────────────────────┐
│           InputFieldMapper Service                  │
│                                                     │
│  Maps:                                              │
│  - full_name → name          ✓                      │
│  - date_of_birth → birth_date ✓                     │
└────────────────────────┬────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────┐
│           Voucher Validation                        │
│        (InputsSpecification)                        │
│                                                     │
│  Expects: name, birth_date, address                 │
│  Status: ✅ All fields present                       │
└─────────────────────────────────────────────────────┘
```

## Testing Results

### Before Fix
```
❌ Missing required fields: Name
```

### After Fix
```
✅ Voucher 8GFL redeemed successfully
✅ All required fields present: kyc, name, birth_date, address
```

## Best Practices Established

1. **Never Modify Published Packages**
   - Keep `form-handler-kyc` unchanged
   - Adapt at application boundary

2. **Centralize Mapping Logic**
   - Single `InputFieldMapper` service
   - No duplicate mapping code

3. **Test Everything**
   - Unit tests for mapper service
   - Integration tests for both redemption flows

4. **Document Thoroughly**
   - Why mappings exist
   - Where they're applied
   - How to add new ones

## Future Considerations

### When Adding New Form Handlers

1. Check handler's output field names
2. Check voucher's expected field names
3. Add mapping if names differ:
   ```php
   // app/Services/InputFieldMapper.php
   protected array $mappings = [
       // ... existing
       'handler_field_name' => 'voucher_field_name',
   ];
   ```
4. Add test
5. Update documentation

### When Creating New Vouchers

**Option 1**: Use standard field names (recommended)
- `name` (not `full_name`)
- `birth_date` (not `date_of_birth`)
- `otp` (not `otp_code`)

**Option 2**: Add custom mapping
- If you must use different names
- Add to `InputFieldMapper`
- Document why

## Related Files

- `app/Services/InputFieldMapper.php` - Service
- `app/Http/Controllers/Disburse/DisburseController.php` - Form flow usage
- `app/Actions/Api/Redemption/ConfirmRedemption.php` - API usage
- `config/form-flow-drivers/voucher-redemption.yaml` - YAML auto-population
- `packages/voucher/src/Specifications/InputsSpecification.php` - Validation
- `tests/Unit/Services/InputFieldMapperTest.php` - Tests
- `docs/INPUT_FIELD_MAPPING.md` - Full documentation
