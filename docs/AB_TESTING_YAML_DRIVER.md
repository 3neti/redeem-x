# A/B Testing YAML vs PHP Driver

This document describes how to test the YAML driver implementation against the existing PHP driver implementation.

## Overview

The application now has two parallel disburse routes:
- **`/disburse`** - Uses PHP driver (default, feature flag respected)
- **`/disburse-yaml`** - Forces YAML driver (ignores feature flag)

Both routes share the same logic for validation, redemption, and success pages. The only difference is which driver transforms voucher data into form flow instructions.

## Architecture

### Controllers
- `DisburseController` - Original controller, respects `config('form-flow.use_yaml_driver')`
- `DisburseYamlController` - Extends DisburseController, forces YAML mode in constructor

### Routes
Both route files define identical endpoints:
- `GET /disburse` or `/disburse-yaml` - Start page (enter voucher code)
- `POST /{voucher}/complete` - Form flow callback (does not redeem)
- `POST /{voucher}/redeem` - Process redemption after user confirmation
- `GET /cancel` - Cancel redemption
- `GET /{voucher}/success` - Success page

### Reference ID Differentiation
- `/disburse` → `disburse-{CODE}-{timestamp}`
- `/disburse-yaml` → `disburse-yaml-{CODE}-{timestamp}`

The frontend (`Complete.vue`) detects the prefix and routes to the correct endpoint.

## Testing Workflow

### 1. Generate Test Voucher
```bash
# Generate voucher with full inputs (bio, location, selfie, signature, KYC)
php artisan test:voucher --amount=1000 --with-bio --with-location --with-selfie --with-signature --with-kyc

# Example output: FULL-ABC123
```

### 2. Test PHP Driver (Control)
```bash
# Visit in browser
open http://localhost:8000/disburse?code=FULL-ABC123

# Or using curl
curl -I http://localhost:8000/disburse?code=FULL-ABC123
```

Expected behavior:
- Redirects to Form Flow Manager
- Reference ID: `disburse-FULL-ABC123-{timestamp}`
- Renders wallet step → bio step → location step → selfie step → signature step → KYC step
- Complete page shows "Confirm Redemption" button
- Clicking button POSTs to `/disburse/FULL-ABC123/redeem`

### 3. Test YAML Driver (Variant)
```bash
# Visit in browser with SAME voucher code
open http://localhost:8000/disburse-yaml?code=FULL-ABC123

# Or using curl
curl -I http://localhost:8000/disburse-yaml?code=FULL-ABC123
```

Expected behavior:
- Redirects to Form Flow Manager
- Reference ID: `disburse-yaml-FULL-ABC123-{timestamp}`
- Renders **identical** steps (wallet → bio → location → selfie → signature → KYC)
- Complete page shows "Confirm Redemption" button
- Clicking button POSTs to `/disburse-yaml/FULL-ABC123/redeem`

### 4. Compare Outputs

#### Method 1: Browser DevTools
1. Open DevTools → Network tab
2. Visit `/disburse?code=FULL-ABC123`
3. Find the redirect to `/form-flow/{flow_id}`
4. Inspect the Inertia response JSON (or session storage)
5. Copy `instructions` JSON
6. Repeat for `/disburse-yaml?code=FULL-ABC123`
7. Use a JSON diff tool to compare

#### Method 2: Laravel Tinker
```php
// PHP Driver
$voucher = \LBHurtado\Voucher\Models\Voucher::where('code', 'FULL-ABC123')->first();
$driverService = app(\LBHurtado\FormFlowManager\Services\DriverService::class);

config(['form-flow.use_yaml_driver' => false]);
$phpInstructions = $driverService->transform($voucher);
dump($phpInstructions->toArray());

// YAML Driver
config(['form-flow.use_yaml_driver' => true]);
$yamlInstructions = $driverService->transform($voucher);
dump($yamlInstructions->toArray());

// Compare
dump($phpInstructions->toArray() === $yamlInstructions->toArray());
```

#### Method 3: Logging
Add logging to `DriverService::transform()`:
```php
$instructions = $this->transform($voucher);
logger()->info('Form Flow Instructions', [
    'driver' => config('form-flow.use_yaml_driver') ? 'YAML' : 'PHP',
    'instructions' => $instructions->toArray(),
]);
```

Then compare log entries.

## Expected Differences

### Should Match Exactly
- Number of steps
- Step handlers (wallet, bio, location, selfie, signature, kyc)
- Field names and types in each step
- Validation rules
- Conditional logic (e.g., "only show location if has_location=true")

### Acceptable Variations
- Internal step IDs (if generated dynamically)
- Timestamp in reference ID
- Flow ID (session-based, unique per request)

## Success Criteria

✅ **YAML driver passes if:**
1. All steps render correctly in browser
2. Form validation works identically
3. Data collection matches PHP driver output
4. Redemption completes successfully
5. No JavaScript console errors
6. No backend exceptions

## Rollout Strategy

### Phase 1: Development Testing (Current)
- Use `/disburse-yaml` route manually for testing
- Compare outputs with `/disburse` route
- Fix any discrepancies

### Phase 2: Canary Testing (Future)
- Route 10% of production traffic to `/disburse-yaml`
- Monitor error rates, completion rates, redemption success
- Compare metrics with `/disburse`

### Phase 3: Full Rollout (Future)
If YAML driver proves equivalent or better:
```bash
# Option A: Flip feature flag globally
FORM_FLOW_USE_YAML_DRIVER=true

# Option B: Remove PHP methods entirely (breaking change)
# - Delete buildWalletStep(), buildTextFieldsStep(), etc.
# - Remove transformWithPhp() method
# - YAML becomes the only implementation
```

### Phase 4: Cleanup (Future)
Once YAML driver is stable:
- Remove `/disburse-yaml` route (redundant)
- Remove `DisburseYamlController`
- Update `/disburse` to always use YAML driver
- Deprecate PHP methods

## Troubleshooting

### Issue: Steps don't match
**Cause:** YAML config doesn't mirror PHP logic  
**Fix:** Update `config/form-flow-drivers/voucher-redemption.yaml`

### Issue: Fields missing or extra
**Cause:** Template rendering error or conditional logic mismatch  
**Fix:** Check `DriverService::processFields()` and YAML conditionals

### Issue: Validation errors
**Cause:** Validation rules not matching between PHP/YAML  
**Fix:** Update `rules` section in YAML step definitions

### Issue: Reference ID parsing fails
**Cause:** Complete.vue expects different prefix format  
**Fix:** Already handled - supports both `disburse-` and `disburse-yaml-` prefixes

## Testing Commands

```bash
# Run A/B testing suite
composer test tests/Feature/DisburseYamlRouteTest.php

# Run all form-flow-manager tests
composer test packages/form-flow-manager/tests

# Verify routes are registered
php artisan route:list --path=disburse

# Generate test vouchers
php artisan test:voucher --amount=100 --with-location
php artisan test:voucher --amount=500 --with-bio --with-selfie
php artisan test:voucher --amount=1000 --with-kyc
```

## Monitoring

Key metrics to track:
- **Conversion rate**: % of users who complete flow
- **Error rate**: Backend exceptions during flow
- **Step abandonment**: Which step users quit at
- **Redemption success**: % of redemptions that succeed
- **Response time**: Performance difference between drivers

## Related Documentation
- [Form Flow Manager README](../packages/form-flow-manager/README.md)
- [YAML Driver Implementation](../packages/form-flow-manager/docs/YAML_DRIVER.md)
- [Template Processor](../packages/form-flow-manager/src/Services/TemplateProcessor.php)
- [Driver Config](../config/form-flow-drivers/voucher-redemption.yaml)
