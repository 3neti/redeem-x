# Form Flow Drivers

This directory contains YAML driver configuration files for the Form Flow Manager.

## Active Drivers

These drivers are actively used by the application:

- **`voucher-redemption.yaml`** - Main voucher redemption flow with splash, wallet, KYC, bio, OTP, location, selfie, and signature handlers

## Example Drivers

The `examples/` directory contains reference implementations demonstrating different driver patterns:

- **`examples/form-inputs.yaml`** - Complex mapping/transformation pattern for dynamic form building
- **`examples/location-capture.yaml`** - Simple single-handler flow for standalone location capture
- **`examples/selfie-verification.yaml`** - Flow with constants and filters for selfie verification

**These are templates only** - they won't run unless you create matching source DTOs.

## Creating Your Own Driver

### Quick Start

1. Create a new `.yaml` file in this directory (not in `examples/`)
2. Define your driver configuration:

```yaml
driver:
  name: "my-flow"
  version: "1.0"
  source: "App\\Data\\MySourceData"  # Your DTO class
  target: "LBHurtado\\FormFlowManager\\Data\\FormFlowInstructionsData"

mappings: {}  # Required (can be empty)

steps:
  intro:
    handler: "splash"
    step_name: "intro"
    config:
      content: "Welcome!"
      timeout: 3
  
  form:
    handler: "form"
    step_name: "my_form"
    title: "My Form"
    config:
      fields:
        - name: "name"
          type: "text"
          label: "Your Name"
          required: true

callbacks:
  on_complete: "{{ base_url }}/callback/complete"
```

3. The driver will be automatically discovered on boot
4. Use `DriverService::transform($yourSourceData)` to start the flow

### Documentation

**Comprehensive Guide**: See `vendor/3neti/form-flow/docs/CREATING_DRIVERS.md` for:
- Two driver approaches (declarative vs. mapping)
- YAML schema reference
- Template syntax (Twig-style `{{ }}`)
- All handler types (splash, form, location, selfie, signature, kyc, otp)
- Conditional logic
- Variables & context
- Complete examples
- Testing strategies
- Best practices

### Handler Types Available

- **`splash`** - Splash screen with optional timeout
- **`form`** - Generic form with multiple field types
- **`location`** - GPS capture with reverse geocoding
- **`selfie`** - Camera capture
- **`signature`** - Digital signature drawing
- **`kyc`** - Identity verification (HyperVerge)
- **`otp`** - OTP verification

### Template Syntax

Use Twig-style templates for dynamic values:

```yaml
title: "Welcome, {{ user.name }}!"
amount: "{{ source.cash.amount }}"
timeout: "{{ config.timeout | default(5) }}"
```

### Conditional Steps

Show/hide steps based on data:

```yaml
steps:
  kyc:
    handler: "kyc"
    condition: "{{ requires_kyc }}"  # Only show if true
```

## Testing Your Driver

```php
// 1. Check driver is discovered
$registry = app(DriverRegistry::class);
dump($registry->names());

// 2. Test transformation
$driverService = app(DriverService::class);
$instructions = $driverService->transform($yourSourceData);
dump($instructions);

// 3. Start flow
$flowService = app(FormFlowService::class);
$state = $flowService->startFlow($instructions);
dump("/form-flow/{$state['flow_id']}");
```

## Auto-Discovery

- Drivers in this directory are automatically loaded on application boot
- File naming: Use kebab-case (e.g., `my-flow.yaml`)
- Must have `.yaml` or `.yml` extension
- Subdirectories are **NOT** scanned (keep examples in `examples/` for reference only)

## Source/Target Matching

The `DriverService` finds the right driver by matching:

- **Source**: Your DTO class (e.g., `App\Data\VoucherInstructionsData`)
- **Target**: `LBHurtado\FormFlowManager\Data\FormFlowInstructionsData`

Only one driver should match each source/target pair.

## Need Help?

- **Package Documentation**: `vendor/3neti/form-flow/README.md`
- **Driver Creation Guide**: `vendor/3neti/form-flow/docs/CREATING_DRIVERS.md`
- **Plugin Architecture**: `vendor/3neti/form-flow/PLUGIN_ARCHITECTURE.md`
- **Test Examples**: `vendor/3neti/form-flow/tests/Unit/DriverServiceYamlTest.php`

---

**Note**: Drivers are powerful but type-unsafe (YAML-based). In the future, a visual UI builder will make driver creation easier while maintaining the flexibility of YAML for advanced users.
