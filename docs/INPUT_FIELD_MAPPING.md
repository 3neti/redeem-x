# Input Field Mapping

## Overview

The `InputFieldMapper` service centralizes field name transformations between form-flow handlers and voucher expectations. This ensures consistent behavior across all redemption flows.

## Why This Exists

**Problem**: Published form-flow handler packages (like `form-handler-kyc`, `form-handler-otp`) use their own field naming conventions, but vouchers may be configured with different field name expectations.

**Solution**: Rather than modifying published packages or forcing voucher creators to use specific field names, we adapt field names at the application boundary using a centralized mapper.

## Architecture

```
┌─────────────────────┐
│  Form Flow Handler  │
│  (Published Package)│
│                     │
│  Returns:           │
│  - full_name        │
│  - date_of_birth    │
│  - otp_code         │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  InputFieldMapper   │◄─── Centralized mapping logic
│  (App Service)      │
│                     │
│  Maps to:           │
│  - name             │
│  - birth_date       │
│  - otp              │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ Voucher Validation  │
│ (InputsSpec)        │
│                     │
│ Expects:            │
│ - name              │
│ - birth_date        │
│ - otp               │
└─────────────────────┘
```

## Current Mappings

| Source Field (Handler) | Target Field (Voucher) | Handler Package | Notes |
|------------------------|------------------------|-----------------|-------|
| `full_name`            | `name`                 | form-handler-kyc | Fallback (YAML also handles this) |
| `date_of_birth`        | `birth_date`           | form-handler-kyc | Fallback (YAML also handles this) |
| `otp_code`             | `otp`                  | form-handler-otp | Primary mapping |

### YAML Auto-Population

The `voucher-redemption.yaml` driver config also handles field transformations via the bio step:

```yaml
# Step: KYC (returns: name, date_of_birth, address)
kyc:
  handler: "kyc"
  step_name: "kyc_verification"

# Step: Bio form (auto-populates from KYC)
bio:
  handler: "form"
  step_name: "bio_fields"
  config:
    variables:
      $kyc_name: "$kyc_verification.name"           # References KYC output
      $kyc_birth: "$kyc_verification.date_of_birth"  # References KYC output
  fields:
    - name: "full_name"        # ← Output field name
      default: "$kyc_name"     # ← Auto-populated from KYC
    - name: "birth_date"       # ← Output field name (different from KYC!)
      default: "$kyc_birth"    # ← Auto-populated from KYC
```

**Result**: The YAML config transforms `date_of_birth` → `birth_date` during auto-population.

**Why InputFieldMapper Still Needed**:
- **Fallback**: If raw KYC data leaks through (bypassing YAML)
- **API Redemption**: Direct API calls don't use YAML driver
- **Consistency**: Ensures both flows produce identical output

## Usage

### In Controllers

```php
use App\Services\InputFieldMapper;

class MyController extends Controller
{
    public function __construct(
        protected InputFieldMapper $fieldMapper
    ) {}
    
    public function handle()
    {
        $rawInputs = request()->input('inputs', []);
        
        // Apply mappings
        $mappedInputs = $this->fieldMapper->map($rawInputs);
        
        // Use mapped inputs for validation
        $context = $service->resolveContextFromArray([
            'inputs' => $mappedInputs,
            // ...
        ]);
    }
}
```

### In Actions

```php
use App\Services\InputFieldMapper;

class MyAction
{
    public function handle(array $inputs)
    {
        $fieldMapper = app(InputFieldMapper::class);
        $mappedInputs = $fieldMapper->map($inputs);
        
        // Continue with mapped inputs
    }
}
```

### Adding Custom Mappings

```php
$mapper = app(InputFieldMapper::class);

// Add runtime mapping
$mapper->addMapping('customer_name', 'name');

$mapped = $mapper->map(['customer_name' => 'John Doe']);
// Result: ['name' => 'John Doe']
```

## Where Mappings Are Applied

1. **DisburseController** (line 343)
   - Primary redemption flow using Form Flow Manager
   - Maps collected data before validation

2. **ConfirmRedemption** (line 71)
   - API-based redemption flow
   - Maps inputs before validation

## Adding New Mappings

When a new form-flow handler package is added or a new field naming convention is introduced:

1. **Update the service**:
   ```php
   // app/Services/InputFieldMapper.php
   protected array $mappings = [
       // ... existing mappings
       'new_source_field' => 'target_field',
   ];
   ```

2. **Add test coverage**:
   ```php
   // tests/Unit/Services/InputFieldMapperTest.php
   test('maps new field correctly', function () {
       $mapper = new InputFieldMapper();
       $mapped = $mapper->map(['new_source_field' => 'value']);
       expect($mapped)->toHaveKey('target_field', 'value');
   });
   ```

3. **Update this documentation**

## Testing

Run the test suite:
```bash
php artisan test tests/Unit/Services/InputFieldMapperTest.php
```

## Troubleshooting

### "Missing required fields" error after redemption

**Symptoms**: Validation fails with "Missing required fields: Name" or similar.

**Cause**: Form flow handler returns a field name that doesn't match voucher expectations.

**Solution**:
1. Check the form flow collected data in logs
2. Check the voucher's required fields: `$voucher->instructions->inputs->fields`
3. Add mapping in `InputFieldMapper::$mappings`
4. Test the mapping

### Example Debug Session

```php
// In DisburseController::redeem() or ConfirmRedemption::asController()
Log::debug('Before mapping', ['inputs' => $inputs]);

$inputs = $fieldMapper->map($inputs);

Log::debug('After mapping', ['inputs' => $inputs]);
Log::debug('Required fields', ['fields' => $voucher->instructions->inputs->fields]);
```

## Best Practices

1. **Never modify published packages** - Always adapt at the application boundary
2. **Keep mappings centralized** - Don't duplicate mapping logic
3. **Document all mappings** - Update this file when adding new mappings
4. **Test all mappings** - Every mapping should have test coverage
5. **Be explicit** - Prefer clear field names over clever transformations

## Related Files

- `app/Services/InputFieldMapper.php` - Service implementation
- `app/Http/Controllers/Disburse/DisburseController.php` - Primary usage
- `app/Actions/Api/Redemption/ConfirmRedemption.php` - API usage
- `packages/voucher/src/Specifications/InputsSpecification.php` - Validation
- `tests/Unit/Services/InputFieldMapperTest.php` - Test coverage
