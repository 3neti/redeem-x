# Form Flow Driver Examples

This directory contains example YAML drivers demonstrating common patterns and use cases for the form-flow system.

## Available Examples

### 1. simple-2-step-flow.yaml
**Purpose**: Basic introduction to form flows  
**Complexity**: Beginner  
**Steps**: 2 (Splash + Form)  
**Handlers**: `splash`, `form`

**What it demonstrates**:
- Basic splash screen
- Simple contact form (name, email, phone)
- Field validation
- Callback structure

**Use cases**:
- Contact forms
- Lead capture
- Simple registration

**Test**:
```bash
php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $instructions = $service->loadDriver('examples/simple-2-step-flow', [
...     'reference_id' => 'test-' . time(),
...     'app_url' => url(''),
... ]);
>>> $flowService = app(LBHurtado\FormFlowManager\Services\FormFlowService::class);
>>> $state = $flowService->startFlow($instructions);
>>> // Visit: /form-flow/{$state['flow_id']}
```

---

### 2. conditional-multi-step.yaml
**Purpose**: Dynamic flow based on context variables  
**Complexity**: Intermediate  
**Steps**: 3-6 (varies based on conditions)  
**Handlers**: `splash`, `form`

**What it demonstrates**:
- Conditional step rendering (`condition:` field)
- Context-driven visibility
- Multiple scenarios from one driver
- Boolean, string, and numeric conditions

**Key patterns**:
- `condition: "{{ is_premium }}"` - Boolean flag
- `condition: "{{ amount >= 1000 }}"` - Numeric comparison
- `condition: "{{ user_type == 'business' }}"` - String comparison

**Use cases**:
- E-commerce checkout (different flows for different products)
- Feature-gated onboarding (premium vs free users)
- Conditional verification (KYC only for high-value transactions)

**Test scenarios**:
```bash
# Scenario A: All features enabled
$instructions = $service->loadDriver('examples/conditional-multi-step', [
    'is_premium' => true,
    'requires_kyc' => true,
    'has_referral_code' => true,
]);
// Steps: welcome → basic_info → premium_options → kyc_verification → referral_input → confirmation

# Scenario B: Basic flow only
$instructions = $service->loadDriver('examples/conditional-multi-step', [
    'is_premium' => false,
    'requires_kyc' => false,
    'has_referral_code' => false,
]);
// Steps: welcome → basic_info → confirmation
```

---

### 3. kyc-bio-auto-population.yaml
**Purpose**: Complete KYC verification workflow with all handler types  
**Complexity**: Advanced  
**Steps**: 9 (8 if KYC skipped)  
**Handlers**: `splash`, `form`, `otp`, `location`, `selfie`, `signature`, `kyc`

**What it demonstrates**:
- Handler plugin integration
- Auto-population from context variables
- Auto-population from previous step data
- Readonly fields for confirmation
- Real-world biometric data collection
- Conditional KYC verification

**Prerequisites**:
```bash
composer require 3neti/form-handler-location
composer require 3neti/form-handler-selfie
composer require 3neti/form-handler-signature
composer require 3neti/form-handler-kyc
composer require 3neti/form-handler-otp
```

**Environment variables needed**:
```bash
OPENCAGE_API_KEY=your_api_key  # For location geocoding
HYPERVERGE_APP_ID=your_app_id  # For KYC verification
HYPERVERGE_APP_KEY=your_app_key
ENGAGESPARK_API_KEY=your_api_key  # For OTP
ENGAGESPARK_ORG_ID=your_org_id
```

**Use cases**:
- Financial services onboarding
- Digital wallet activation
- High-value transaction verification
- Identity verification flows

**Test**:
```bash
$instructions = $service->loadDriver('examples/kyc-bio-auto-population', [
    'reference_id' => 'wallet-' . time(),
    'app_url' => url(''),
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com',
    'amount' => 5000,
    'requires_full_kyc' => true,
]);
```

**Collected data includes**:
- Mobile number (with OTP verification)
- Personal information (name, email, DOB, address)
- GPS location + map snapshot
- Selfie photo
- Digital signature
- Government ID verification (conditional)

---

## Common Patterns

### Pattern 1: Template Variables
```yaml
title: "Welcome, {{ user_name }}!"
reference_id: "order-{{ timestamp }}"
value: "{{ user_email ?? '' }}"  # With fallback
```

### Pattern 2: Conditional Steps
```yaml
steps:
  premium_step:
    handler: "form"
    condition: "{{ is_premium }}"  # Only shown if true
```

### Pattern 3: Auto-Population
```yaml
fields:
  # From context variable
  - name: "email"
    value: "{{ user_email ?? '' }}"
  
  # From previous step
  - name: "mobile_confirmed"
    value: "{{ wallet_info.mobile ?? '' }}"
    readonly: true
```

### Pattern 4: Field Validation
```yaml
fields:
  - name: "email"
    type: "email"
    required: true
    validation:
      - "required"
      - "email"
      - "max:255"
```

### Pattern 5: Readonly Confirmation
```yaml
fields:
  - name: "confirm_name"
    type: "text"
    value: "{{ personal_info.full_name ?? '' }}"
    readonly: true
```

---

## How to Use These Examples

### 1. Copy and Modify
```bash
# Copy example to create your own driver
cp config/form-flow-drivers/examples/simple-2-step-flow.yaml \
   config/form-flow-drivers/my-custom-flow.yaml

# Edit and customize
vim config/form-flow-drivers/my-custom-flow.yaml
```

### 2. Test in Tinker
```bash
php artisan tinker
>>> $service = app(LBHurtado\FormFlowManager\Services\DriverService::class);
>>> $instructions = $service->loadDriver('my-custom-flow', ['reference_id' => 'test']);
>>> print_r($instructions->steps);  # Verify steps are correct
```

### 3. Integrate with Your App
```php
// In your controller
public function initiateFlow(Request $request)
{
    $driverService = app(DriverService::class);
    $formFlowService = app(FormFlowService::class);
    
    $context = [
        'reference_id' => "user-{$user->id}",
        'app_url' => url(''),
        'user_name' => $user->name,
        'user_email' => $user->email,
        // ... other context variables
    ];
    
    $instructions = $driverService->loadDriver('my-custom-flow', $context);
    $state = $formFlowService->startFlow($instructions);
    
    return redirect("/form-flow/{$state['flow_id']}");
}
```

---

## Troubleshooting

### Issue: "Handler not found"
**Solution**: Install handler package
```bash
composer require 3neti/form-handler-{handler-name}
php artisan config:clear
```

### Issue: Template variables showing as literal text
**Solution**: Ensure context variable is passed
```php
// Wrong: Missing context variable
$instructions = $service->loadDriver('driver', ['reference_id' => 'test']);

// Correct: Include all template variables
$instructions = $service->loadDriver('driver', [
    'reference_id' => 'test',
    'user_name' => 'John',  // Used in {{ user_name }}
    'is_premium' => true,   // Used in {{ is_premium }}
]);
```

### Issue: Step always/never shows despite condition
**Solution**: Check boolean evaluation
```yaml
# Wrong: String 'false' evaluates to true
condition: "false"

# Correct: Boolean false
condition: "{{ is_enabled }}"  # Where is_enabled = false in context
```

### Issue: "YAML parse error"
**Solution**: Validate YAML syntax
```bash
# Use online validator
# Copy YAML to: https://www.yamllint.com/

# Or validate in PHP
php artisan tinker
>>> $yaml = file_get_contents('config/form-flow-drivers/my-flow.yaml');
>>> $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
```

---

## Next Steps

After understanding these examples:

1. **Read the integration guide**: [INTEGRATION.md](../../../docs/guides/features/form-flow/INTEGRATION.md)
2. **Create custom handlers**: [HANDLERS.md](../../../docs/guides/features/form-flow/HANDLERS.md)
3. **Check API reference**: [API.md](../../../docs/guides/features/form-flow/API.md)
4. **Review environment variables**: [ENV_VARS.md](../../../docs/guides/features/form-flow/ENV_VARS.md)
5. **Documentation index**: [README.md](../../../docs/guides/features/form-flow/README.md)

---

## Contributing

Have a useful example driver? Submit a PR with:
- YAML file in this directory
- Descriptive comments explaining the pattern
- Update to this README

**Example template**:
```yaml
# =============================================================================
# Example Name
# =============================================================================
#
# Description: What this example demonstrates
# Use Case: When to use this pattern
# Complexity: Beginner/Intermediate/Advanced
#
# Test with:
#   php artisan tinker
#   >>> [test code here]
#
# =============================================================================
```

---

**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
