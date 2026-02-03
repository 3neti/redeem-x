# Form Flow Integration Guide

**Version**: 1.0  
**Last Updated**: 2026-02-03  
**Package**: `3neti/form-flow` v1.7+

## Table of Contents

1. [Getting Started](#getting-started)
2. [Architecture Overview](#architecture-overview)
3. [Driver Configuration Reference](#driver-configuration-reference)
4. [Input Field Mapping Deep Dive](#input-field-mapping-deep-dive)
5. [Testing & Debugging](#testing--debugging)

---

## Getting Started

### Prerequisites

Before integrating form-flow into your Laravel application, ensure you have:

- **PHP**: 8.2 or higher
- **Laravel**: 11.0 or higher (12.0 supported)
- **Composer**: Latest version
- **Node.js & npm**: For Vue.js components
- **Inertia.js**: 2.0+ already installed in your project

### Installation

The form-flow system in redeem-x is already installed, but for reference or new projects:

```bash
# Install core package
composer require 3neti/form-flow

# Install handler plugins
composer require 3neti/form-handler-location
composer require 3neti/form-handler-selfie
composer require 3neti/form-handler-signature
composer require 3neti/form-handler-kyc
composer require 3neti/form-handler-otp
```

### Publishing Assets

```bash
# Publish core configuration
php artisan vendor:publish --tag=form-flow-config

# Publish driver examples
php artisan vendor:publish --tag=form-flow-drivers

# Publish Vue components (auto-published via composer post-update-cmd)
php artisan vendor:publish --tag=form-flow-views
```

**Location of published assets**:
- Config: `config/form-flow.php`
- Drivers: `config/form-flow-drivers/*.yaml`
- Vue components: `resources/js/pages/form-flow/`

### Environment Configuration

Add to `.env`:

```bash
# Form Flow Configuration
FORM_FLOW_ROUTE_PREFIX=form-flow
# FORM_FLOW_MIDDLEWARE=web
# FORM_FLOW_DRIVER_DIRECTORY=/path/to/drivers
# FORM_FLOW_SESSION_PREFIX=form_flow
```

**Defaults** (no .env needed):
- Route prefix: `form-flow` → Routes at `/form-flow/*`
- Middleware: `['web']` → CSRF protection, sessions
- Driver directory: `config_path('form-flow-drivers')`
- Session prefix: `form_flow` → Keys like `form_flow.{flow_id}`

### First Voucher Redemption Test

1. **Generate a test voucher** with inputs:
   ```bash
   # Via Portal or Generate Vouchers UI
   # Enable: Location, Selfie, Signature inputs
   # Amount: ₱100
   ```

2. **Redeem the voucher**:
   ```
   http://your-app.test/disburse?code=YOUR-CODE
   ```

3. **Expected flow**:
   - Splash page (if enabled)
   - Wallet info form (mobile, bank_code, account_number)
   - Location capture (GPS + address)
   - Selfie capture (camera)
   - Signature capture (drawing pad)
   - Finalize page (review & confirm)

4. **Verify data collection**:
   ```php
   // In DisburseController::redeem()
   Log::debug('Collected data', $collectedData);
   ```

### Verifying Handler Registration

Check that all handlers are registered:

```bash
php artisan tinker
```

```php
// Check registered handlers
$handlers = config('form-flow.handlers');
dump(array_keys($handlers));

// Expected output:
// ['missing', 'form', 'splash', 'location', 'selfie', 'signature', 'kyc', 'otp']
```

If handlers are missing:
1. Run `composer dump-autoload`
2. Check `composer.json` for handler packages
3. Verify service providers are auto-discovered

---

## Architecture Overview

### Package vs. Plugin Architecture

Form Flow uses a **core + plugins** architecture:

```
┌─────────────────────────────────────────────────────────┐
│ Core Package (3neti/form-flow)                          │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ DriverService      (YAML → FormFlow transform)      │ │
│ │ FormFlowService    (Session state management)       │ │
│ │ FormFlowController (REST API endpoints)             │ │
│ │ FormHandler        (Generic form rendering)         │ │
│ │ SplashHandler      (Splash screen)                  │ │
│ │ MissingHandler     (Fallback for unknown handlers)  │ │
│ └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
              ▲
              │ Auto-registers via service providers
              │
┌─────────────┴───────────────────────────────────────────┐
│ Plugin Handlers (separate packages)                     │
│ ┌──────────────────┐  ┌──────────────────┐             │
│ │ LocationHandler  │  │ SelfieHandler    │             │
│ │ (GPS + geocode)  │  │ (Camera capture) │  ...        │
│ └──────────────────┘  └──────────────────┘             │
└─────────────────────────────────────────────────────────┘
```

**Core responsibilities**:
- Flow orchestration
- Session management
- Driver-based transformation
- Generic form rendering

**Plugin responsibilities**:
- Specialized input capture (location, camera, signature, KYC)
- Custom Vue components
- Domain-specific validation

### Data Flow Diagram

```
1. User enters voucher code
   ↓
┌─────────────────────────────────────────────────────────┐
│ DisburseController::start()                             │
│ - Validates voucher code                                │
│ - Checks status (redeemed, expired, active)             │
└──────────────┬──────────────────────────────────────────┘
               ↓
2. Transform voucher → form flow
   ↓
┌─────────────────────────────────────────────────────────┐
│ DriverService::transform($voucher)                      │
│ - Loads voucher-redemption.yaml                         │
│ - Builds context (code, amount, has_* flags)            │
│ - Processes steps (conditional logic)                   │
│ - Returns FormFlowInstructionsData                      │
└──────────────┬──────────────────────────────────────────┘
               ↓
3. Start flow session
   ↓
┌─────────────────────────────────────────────────────────┐
│ FormFlowService::startFlow($instructions)               │
│ - Creates unique flow_id                                │
│ - Stores state in session (form_flow.{flow_id})         │
│ - Returns state with flow_id                            │
└──────────────┬──────────────────────────────────────────┘
               ↓
4. Redirect to form flow
   ↓
┌─────────────────────────────────────────────────────────┐
│ GET /form-flow/{flow_id}                                │
│ FormFlowController::show()                              │
│ - Loads session state                                   │
│ - Determines current step                               │
│ - Resolves handler (location, selfie, etc.)             │
│ - Calls handler->render()                               │
└──────────────┬──────────────────────────────────────────┘
               ↓
5. User interacts with step
   ↓
┌─────────────────────────────────────────────────────────┐
│ POST /form-flow/{flow_id}/step/{step}                   │
│ FormFlowController::updateStep()                        │
│ - Validates input via handler->validate()               │
│ - Stores data in collected_data[step_name]              │
│ - Calls handler->handle()                               │
│ - Advances to next step or completion                   │
└──────────────┬──────────────────────────────────────────┘
               ↓
6. Flow completion
   ↓
┌─────────────────────────────────────────────────────────┐
│ POST /form-flow/{flow_id}/complete                      │
│ FormFlowController::complete()                          │
│ - Triggers on_complete callback webhook                 │
│ - POST /disburse/{code}/complete                        │
└──────────────┬──────────────────────────────────────────┘
               ↓
7. User confirms redemption
   ↓
┌─────────────────────────────────────────────────────────┐
│ POST /disburse/{code}/redeem                            │
│ DisburseController::redeem()                            │
│ - Retrieves collected_data from session                 │
│ - Maps via InputFieldMapper                             │
│ - Validates via VoucherRedemptionService                │
│ - Executes ProcessRedemption (disburse + notify)        │
│ - Clears flow session                                   │
└─────────────────────────────────────────────────────────┘
```

### Session Management & Isolation

Form flow uses **isolated session namespacing** to avoid collisions:

```php
// Session structure
session()->put('form_flow.abc123', [
    'flow_id' => 'abc123',
    'reference_id' => 'disburse-VOUCHER-1234567890',
    'status' => 'in_progress',
    'current_step' => 2,
    'completed_steps' => ['wallet_info', 'location_capture'],
    'collected_data' => [
        'wallet_info' => ['mobile' => '09171234567', 'bank_code' => 'GXCHPHM2XXX'],
        'location_capture' => ['latitude' => 14.646, 'longitude' => 121.028, ...]
    ],
    'created_at' => '2026-02-03 12:00:00',
    'updated_at' => '2026-02-03 12:05:30',
]);

// Reference ID mapping (for callback lookup)
session()->put('form_flow_ref.disburse-VOUCHER-1234567890', 'abc123');
```

**Isolation benefits**:
- No conflicts with host app session keys
- Multiple concurrent flows per user
- Easy cleanup after completion

### Handler Plugin Pattern

All handler plugins follow this registration pattern:

```php
// Example: LocationHandlerServiceProvider
namespace LBHurtado\FormHandlerLocation;

use Illuminate\Support\ServiceProvider;

class LocationHandlerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Register handler with form-flow
        $this->registerHandler();
        
        // 2. Publish frontend assets
        $this->publishes([
            __DIR__.'/../stubs/resources/js/pages/form-flow/location' 
                => resource_path('js/pages/form-flow/location'),
        ], 'location-handler-stubs');
    }
    
    protected function registerHandler(): void
    {
        $handlers = config('form-flow.handlers', []);
        $handlers['location'] = LocationHandler::class;
        config(['form-flow.handlers' => $handlers]);
    }
}
```

**Auto-discovery** via `composer.json`:
```json
{
    "extra": {
        "laravel": {
            "providers": [
                "LBHurtado\\FormHandlerLocation\\LocationHandlerServiceProvider"
            ]
        }
    }
}
```

### YAML Driver System (DirXML-Style Mapping)

The driver system transforms domain-specific data (Voucher) into generic form flow instructions using **declarative YAML configuration**.

**Why YAML drivers?**
- **Zero PHP code changes**: Add new flows via config files
- **Version controlled**: Track transformations in git
- **Self-documenting**: YAML IS the documentation
- **Testable**: DriverService validates and renders templates
- **Flexible**: Conditional logic, template rendering, auto-population

**Driver location**: `config/form-flow-drivers/*.yaml`

**Active driver**: `voucher-redemption.yaml`

---

## Driver Configuration Reference

### YAML Schema

Complete driver structure:

```yaml
# Driver metadata
driver:
  name: "my-flow"              # Unique identifier
  version: "1.0"               # Semantic version
  source: "App\\Data\\MyData"  # Source DTO class
  target: "LBHurtado\\FormFlowManager\\Data\\FormFlowInstructionsData"

# Mappings (required, can be empty)
mappings: {}

# Reference ID template
reference_id: "flow-{{ code }}-{{ timestamp }}"

# Callback URLs
callbacks:
  on_complete: "{{ base_url }}/api/complete"
  on_cancel: "{{ base_url }}/api/cancel"

# Steps definition
steps:
  step_key:
    handler: "handler_name"    # Handler type (form, location, etc.)
    step_name: "internal_name" # Used in collected_data keys
    title: "Step Title"        # Display title
    description: "Step desc"   # Optional description
    condition: "{{ flag }}"    # Optional: Show step conditionally
    config:                    # Handler-specific config
      key: value
    fields:                    # For 'form' handler only
      - name: "field_name"
        type: "text"
        label: "Field Label"
        required: true
```

### Template Syntax

Form flow uses **Twig-style template expressions** (`{{ }}`) for dynamic values:

**Basic variable substitution**:
```yaml
title: "Redeeming {{ code }} from {{ owner_name }}"
# Renders: "Redeeming BW3P from Lester Hurtado"
```

**Filters**:
```yaml
timeout: "{{ rider.timeout | default(5) }}"
# Uses 5 if rider.timeout is null/undefined
```

**Available operators**:
- `default(value)` - Fallback value
- `or` - Logical OR
- `and` - Logical AND

### Conditional Logic

Show/hide steps based on voucher data:

```yaml
steps:
  kyc:
    handler: "kyc"
    condition: "{{ has_kyc }}"  # Only show if has_kyc is true
    
  bio:
    handler: "form"
    condition: "{{ has_name or has_email }}"  # Show if either field exists
    
  location:
    handler: "location"
    condition: "{{ has_location and not has_map }}"  # Complex logic
```

**Boolean evaluation**:
- `true`, `"true"`, `1` → true
- `false`, `"false"`, `0`, `null`, `""` → false

### Context Variables

Variables available in templates (built from Voucher):

**Basic voucher data**:
```php
[
    'code' => 'BW3P',                    // Voucher code
    'amount' => 5000,                    // Amount in centavos
    'currency' => 'PHP',                 // Currency code
    'owner_name' => 'Lester Hurtado',    // Voucher owner
    'owner_email' => 'lester@hurtado.ph',
    'base_url' => 'http://redeem-x.test',
    'timestamp' => 1734221420,           // Unix timestamp
]
```

**Conditional flags** (generated from voucher instructions):
```php
[
    'has_name' => true,          // Name input field enabled
    'has_email' => false,        // Email input field enabled
    'has_birth_date' => true,    // Birth date input enabled
    'has_address' => false,      // Address input enabled
    'has_reference_code' => true,// Reference code input enabled
    'has_location' => true,      // Location input enabled
    'has_map' => false,          // Map input enabled (new format)
    'has_selfie' => true,        // Selfie input enabled
    'has_signature' => true,     // Signature input enabled
    'has_kyc' => true,           // KYC input enabled
    'has_otp' => false,          // OTP input enabled
    'splash_enabled' => true,    // Splash page enabled in rider
]
```

**Rider data** (custom voucher config):
```php
[
    'rider' => [
        'splash' => 'Welcome to redemption!',
        'splash_timeout' => 3,
        'message' => 'Thank you for redeeming',
        'url' => 'https://example.com/success',
    ]
]
```

### Step Configuration

#### Form Handler

Generic form with multiple field types:

```yaml
wallet:
  handler: "form"
  step_name: "wallet_info"
  title: "Wallet Information"
  config:
    auto_sync:  # Auto-copy mobile → account_number (INSTAPAY)
      enabled: true
      source_field: "mobile"
      target_field: "account_number"
      condition_field: "settlement_rail"
      condition_values: ["INSTAPAY"]
      debounce_ms: 1500
  fields:
    - name: "mobile"
      type: "tel"
      label: "Mobile Number"
      required: true
      emphasis: "hero"  # Large, prominent styling
      help_text: "Enter your mobile number"
    
    - name: "bank_code"
      type: "bank_account"  # Custom field type
      label: "Bank/Wallet"
      default: "GXCHPHM2XXX"
      required: true
      group: "endorsement"  # Groups with account_number
```

**Field types**:
- `text`, `email`, `tel`, `number`, `date`, `textarea`
- `select`, `checkbox`, `radio`, `hidden`
- `bank_account` (custom: dropdown with bank/wallet options)
- `file` (file upload)

**Field attributes**:
- `name` (required): Field identifier
- `type` (required): Field type
- `label`: Display label
- `placeholder`: Placeholder text
- `default`: Default value (supports templates: `"{{ amount }}"`)
- `required`: Boolean
- `readonly`: Boolean
- `validation`: Laravel validation rules (array or string)
- `variant`: Display variant (`readonly-badge` for summary cards)
- `emphasis`: Styling emphasis (`hero` for primary fields)
- `help_text`: Helper text below field
- `group`: Group identifier (fields with same group rendered together)
- `condition`: Show field conditionally (`"{{ has_email }}"`)

#### Location Handler

GPS capture with reverse geocoding:

```yaml
location:
  handler: "location"
  step_name: "location_capture"
  title: "Share Your Location"
  condition: "{{ has_location }}"
  config:
    require_address: true      # Reverse geocode lat/lng → address
    capture_snapshot: true     # Generate map image via Mapbox
    accuracy_threshold: 100    # Minimum GPS accuracy (meters)
```

**Collected data format**:
```json
{
  "latitude": 14.646966878338,
  "longitude": 121.02886961375,
  "accuracy": 107,
  "timestamp": "2026-02-03T19:18:54+08:00",
  "address": {
    "formatted": "Makati City, Metro Manila, Philippines",
    "components": {...}
  },
  "map": "data:image/png;base64,..."
}
```

#### Selfie Handler

Camera capture with face detection:

```yaml
selfie:
  handler: "selfie"
  step_name: "selfie_capture"
  title: "Take a Selfie"
  condition: "{{ has_selfie }}"
  config:
    width: 640
    height: 480
    quality: 0.9
    facing_mode: "user"  # Front camera
```

**Collected data format**:
```json
{
  "selfie": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

#### Signature Handler

Digital signature drawing pad:

```yaml
signature:
  handler: "signature"
  step_name: "signature_capture"
  title: "Digital Signature"
  condition: "{{ has_signature }}"
  config:
    width: 600
    height: 256
    quality: 0.85
    line_width: 2
    line_color: "#000000"
```

**Collected data format**:
```json
{
  "signature": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
}
```

#### KYC Handler

HyperVerge identity verification:

```yaml
kyc:
  handler: "kyc"
  step_name: "kyc_verification"
  title: "Identity Verification"
  condition: "{{ has_kyc }}"
  config:
    workflow: "onboarding"  # HyperVerge workflow type
```

**Collected data format** (flattened by handler):
```json
{
  "status": "approved",
  "transaction_id": "formflow-abc123-kyc-1234567890",
  "name": "John Doe",
  "date_of_birth": "1990-01-15",
  "address": "123 Main St, City",
  "id_type": "passport",
  "id_number": "AB1234567"
}
```

#### OTP Handler

Phone number verification:

```yaml
otp:
  handler: "otp"
  step_name: "otp_verification"
  title: "Verify Phone Number"
  condition: "{{ has_otp }}"
  config:
    code_length: 6
    expiry_minutes: 5
    resend_cooldown: 60  # Seconds before resend allowed
```

**Collected data format**:
```json
{
  "otp_verified": true,
  "verified_at": "2026-02-03T12:30:45+08:00"
}
```

#### Splash Handler

Welcome/intro screen with auto-advance:

```yaml
splash:
  handler: "splash"
  step_name: "splash_page"
  condition: "{{ splash_enabled }}"
  config:
    content: "{{ rider.splash }}"      # Markdown or HTML
    timeout: "{{ rider.splash_timeout | default(null) }}"  # Seconds (null = manual)
    voucher_code: "{{ code }}"         # Display code
```

### Auto-Population Pattern

KYC data can auto-populate bio fields using **step references**:

```yaml
steps:
  # Step 1: KYC (must come first)
  kyc:
    handler: "kyc"
    step_name: "kyc_verification"
    # Returns: {name, date_of_birth, address, ...}
  
  # Step 2: Bio (references KYC data)
  bio:
    handler: "form"
    step_name: "bio_fields"
    config:
      # Define named references to previous step data
      variables:
        $kyc_name: "$kyc_verification.name"
        $kyc_birth: "$kyc_verification.date_of_birth"
        $kyc_addr: "$kyc_verification.address"
    fields:
      - name: "full_name"
        type: "text"
        default: "$kyc_name"  # Auto-populates from KYC
        required: true
      
      - name: "birth_date"
        type: "date"
        default: "$kyc_birth"  # Auto-populates from KYC
        required: true
```

**How it works**:
1. User completes KYC step → Data stored in `collected_data['kyc_verification']`
2. Form flow renders bio step → Resolves `$kyc_name` from collected data
3. Vue component receives pre-filled default values
4. User can edit or accept auto-populated data

### Testing Drivers

Validate and test driver transformations:

```php
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;

// 1. Check driver is loaded
$driverService = app(DriverService::class);
$config = $driverService->loadConfig('voucher-redemption');
dump($config);  // Should return DriverConfigData

// 2. Transform voucher
$voucher = Voucher::where('code', 'TEST123')->first();
$instructions = $driverService->transform($voucher);
dump($instructions);  // FormFlowInstructionsData

// 3. Inspect generated steps
dump($instructions->steps);
// Should show array of steps based on voucher inputs

// 4. Check conditional logic
// Create voucher with only location input
// Transform should only include wallet + location steps (no selfie, signature)
```

---

## Input Field Mapping Deep Dive

### VoucherInstructionsData Structure

The `VoucherInstructionsData` DTO is the source of truth for form flow generation:

```php
// Location: packages/voucher/src/Data/VoucherInstructionsData.php
class VoucherInstructionsData extends Data
{
    public function __construct(
        public ?CashInstructionsData $cash,        // Amount, currency, settlement_rail
        public ?Collection $inputs,                 // Input field configurations
        public ?Collection $validations,            // Validation rules
        public ?Collection $feedbacks,              // Notification channels
        public ?RiderInstructionsData $rider,       // Custom config (splash, success message)
        public ?int $count,                         // Voucher count (for batch generation)
        public ?string $prefix,                     // Voucher code prefix
        public ?string $mask,                       // Voucher code mask pattern
        public ?int $ttl,                           // Time-to-live (hours)
        public ?string $note,                       // Internal note
    ) {}
}
```

**Input field structure** (`inputs` collection):
```php
[
    InputData {
        name: 'name',
        label: 'Full Name',
        required: true,
        type: 'text',
    },
    InputData {
        name: 'location',
        label: 'Current Location',
        required: true,
        type: 'location',  // Special type
    },
    InputData {
        name: 'signature',
        label: 'Digital Signature',
        required: true,
        type: 'signature',  // Special type
    },
]
```

### How `has_*` Flags Are Generated

The `DriverService::buildContext()` method scans voucher instructions and generates boolean flags:

```php
// Simplified logic from DriverService
protected function buildContext(Voucher $voucher): array
{
    $context = [
        'code' => $voucher->code,
        'amount' => $voucher->instructions->cash->amount,
        // ... basic fields
    ];
    
    // Generate has_* flags from inputs
    $inputNames = $voucher->instructions->inputs->pluck('name')->toArray();
    
    foreach (['name', 'email', 'birth_date', 'address', 'reference_code', 
              'location', 'map', 'selfie', 'signature', 'kyc', 'otp'] as $field) {
        $context["has_{$field}"] = in_array($field, $inputNames);
    }
    
    // Special handling for rider data
    $context['splash_enabled'] = !empty($voucher->instructions->rider->splash);
    
    return $context;
}
```

**Result**:
```php
[
    'code' => 'BW3P',
    'has_name' => true,        // 'name' found in inputs
    'has_email' => false,      // 'email' NOT in inputs
    'has_location' => true,    // 'location' found
    'has_signature' => true,   // 'signature' found
    'has_kyc' => true,         // 'kyc' found
    // ...
]
```

### Input Field Naming Conventions

Standard field names recognized by the system:

**Bio fields** (personal information):
- `name` / `full_name` - Person's full name
- `email` - Email address
- `birth_date` / `birthdate` - Date of birth
- `address` - Physical address
- `reference_code` - Custom reference identifier

**Media fields** (captured via handlers):
- `signature` - Digital signature (base64 PNG)
- `selfie` - Selfie photo (base64 JPEG/PNG)
- `location` - GPS coordinates + address (old format - JSON object)
- `map` - GPS coordinates + address (new format - form-flow structure)

**Verification fields**:
- `kyc` - KYC verification (triggers HyperVerge flow)
- `otp` - OTP verification (triggers SMS flow)

**Wallet fields** (payment info):
- `mobile` - Mobile number
- `bank_code` - Bank/wallet BIC code (e.g., GXCHPHM2XXX)
- `account_number` - Account number (auto-synced from mobile for INSTAPAY)

**Special fields**:
- `splash_viewed` - Internal flag (true after splash dismissed)
- `_step_name` - Internal metadata (current step)
- `viewed_at` - Timestamp of view

### Media Input Fields

Media fields have special handling:

**Signature**:
```yaml
# In voucher instructions
inputs:
  - name: signature
    type: signature
    required: true

# In YAML driver
steps:
  signature:
    handler: "signature"
    condition: "{{ has_signature }}"
    config:
      width: 600
      height: 256

# Collected data
collected_data:
  signature_capture:
    signature: "data:image/png;base64,iVBORw0KGgo..."
```

**Selfie**:
```yaml
# Similar pattern
inputs:
  - name: selfie
    type: selfie

steps:
  selfie:
    handler: "selfie"
    condition: "{{ has_selfie }}"

collected_data:
  selfie_capture:
    selfie: "data:image/jpeg;base64,/9j/4AAQSkZJ..."
```

**Location** (two formats):

Old format (direct JSON):
```yaml
inputs:
  - name: location
    type: location

# Collected as JSON string
"location": "{\"latitude\":14.646,\"longitude\":121.028,...}"
```

New format (via form-flow handler):
```yaml
inputs:
  - name: map  # Different name!
    type: location

steps:
  location:
    handler: "location"
    step_name: "location_capture"
    condition: "{{ has_map }}"

collected_data:
  location_capture:
    latitude: 14.646
    longitude: 121.028
    accuracy: 107
    address:
      formatted: "Makati City, Philippines"
    map: "data:image/png;base64,..."
```

### KYC Input Mapping

KYC is a special case - it's both an input flag AND a handler:

**Voucher Instructions**:
```php
// When generating voucher with KYC checkbox
inputs: [
    InputData {name: 'kyc', type: 'kyc', required: true}
]
```

**Driver Transformation**:
```yaml
# Generates has_kyc = true flag

steps:
  kyc:
    handler: "kyc"
    condition: "{{ has_kyc }}"
```

**HyperVerge Flow**:
1. KYCHandler::render() redirects to HyperVerge onboarding URL
2. User completes ID verification in HyperVerge app
3. Callback returns to KYCStatusPage with transaction_id
4. KYCHandler polls HyperVerge API for results
5. On approval, flattens data into form-flow format

**Flattened KYC Data** (from KYCHandler::flattenKYCData()):
```php
[
    'status' => 'approved',
    'transaction_id' => 'formflow-abc123-kyc-1234567890',
    'name' => 'John Doe',                    // From ID document
    'date_of_birth' => '1990-01-15',         // From ID document
    'address' => '123 Main St, City',        // From ID document
    'id_type' => 'passport',
    'id_number' => 'AB1234567',
    'id_front_image' => 'data:image/jpeg...',
    'selfie_image' => 'data:image/jpeg...',
]
```

**Auto-Population to Bio Fields**:
```yaml
bio:
  config:
    variables:
      $kyc_name: "$kyc_verification.name"
      $kyc_birth: "$kyc_verification.date_of_birth"
  fields:
    - name: "full_name"
      default: "$kyc_name"  # Auto-fills from KYC
```

### Context Building Process

The `buildContext()` method transforms Voucher → template variables:

```php
// DriverService::buildContext(Voucher $voucher): array

1. Extract basic voucher data:
   - code, amount, currency, owner info

2. Generate has_* flags:
   foreach (inputs as $input) {
       $context["has_{$input->name}"] = true;
   }

3. Add rider data:
   - splash, splash_timeout, message, url

4. Add system variables:
   - base_url (from APP_URL)
   - timestamp (current time)

5. Return merged context array
```

**Example context**:
```php
[
    // Basic
    'code' => 'BW3P',
    'amount' => 5000,
    'currency' => 'PHP',
    'owner_name' => 'Lester Hurtado',
    'owner_email' => 'lester@hurtado.ph',
    
    // Flags
    'has_name' => true,
    'has_email' => false,
    'has_location' => true,
    'has_signature' => true,
    'has_kyc' => true,
    
    // Rider
    'rider' => [
        'splash' => 'Welcome!',
        'splash_timeout' => 3,
    ],
    
    // System
    'base_url' => 'http://redeem-x.test',
    'timestamp' => 1738596134,
]
```

### Step-to-Input Correlation

How voucher inputs map to form flow steps:

| Voucher Input | Driver Step | Handler | Step Name | Collected Data Key |
|--------------|-------------|---------|-----------|-------------------|
| *(always)* | `wallet` | `form` | `wallet_info` | `wallet_info` |
| `kyc` | `kyc` | `kyc` | `kyc_verification` | `kyc_verification` |
| `name`, `email`, `birth_date` | `bio` | `form` | `bio_fields` | `bio_fields` |
| `otp` | `otp` | `otp` | `otp_verification` | `otp_verification` |
| `location` / `map` | `location` | `location` | `location_capture` | `location_capture` |
| `selfie` | `selfie` | `selfie` | `selfie_capture` | `selfie_capture` |
| `signature` | `signature` | `signature` | `signature_capture` | `signature_capture` |

**Important ordering**:
- `wallet` is always first (no condition)
- `kyc` must come BEFORE `bio` (for auto-population)
- Other steps can be in any order

### Conditional Step Rendering

Steps are conditionally included based on `condition` field:

```yaml
# Driver YAML
steps:
  bio:
    handler: "form"
    condition: "{{ has_name or has_email or has_birth_date }}"
    # Only rendered if at least one bio field is enabled
```

**Evaluation logic**:
```php
// DriverService::evaluateCondition()

1. Extract condition string: "{{ has_name or has_email }}"
2. Replace variables: "true or false"
3. Evaluate expression: true
4. Include step if true, skip if false
```

**Generated FormFlowInstructionsData**:
```php
// If has_name = true, has_email = false
steps: [
    ['handler' => 'form', 'step_name' => 'wallet_info'],
    ['handler' => 'form', 'step_name' => 'bio_fields'],  // Included (has_name = true)
    // 'otp' step skipped (has_otp = false)
    ['handler' => 'location', 'step_name' => 'location_capture'],
]
```

### Auto-Population Mechanics

KYC data flows into bio fields via **named step references**:

**Step 1: Configure variables in bio step**
```yaml
bio:
  config:
    variables:
      $kyc_name: "$kyc_verification.name"
      $kyc_email: "$kyc_verification.email"
```

**Step 2: Reference variables in field defaults**
```yaml
fields:
  - name: "full_name"
    default: "$kyc_name"
  - name: "email"
    default: "$kyc_email"
```

**Step 3: Form flow resolves references**
```php
// FormFlowService during render
1. Load collected_data from session
2. Find kyc_verification step data:
   collected_data['kyc_verification'] = [
       'name' => 'John Doe',
       'email' => 'john@example.com',
   ]

3. Resolve $kyc_name → 'John Doe'
4. Set field default to 'John Doe'
5. Pass to Vue component as initial value
```

**Vue component receives**:
```javascript
{
  fields: [
    {
      name: 'full_name',
      type: 'text',
      default: 'John Doe',  // Pre-filled from KYC
    }
  ]
}
```

### Data Collection Format

Form flow stores collected data in **flat structure** by step name:

```php
// Session: form_flow.{flow_id}.collected_data
[
    'wallet_info' => [
        'mobile' => '09171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'amount' => 5000,
        'settlement_rail' => 'INSTAPAY',
    ],
    
    'kyc_verification' => [
        'status' => 'approved',
        'transaction_id' => 'formflow-abc-kyc-123',
        'name' => 'John Doe',
        'date_of_birth' => '1990-01-15',
        'address' => '123 Main St',
    ],
    
    'bio_fields' => [
        'full_name' => 'John Doe',  // May be edited from KYC
        'email' => 'john@example.com',
        'birth_date' => '1990-01-15',
    ],
    
    'location_capture' => [
        'latitude' => 14.646,
        'longitude' => 121.028,
        'accuracy' => 107,
        'timestamp' => '2026-02-03T12:00:00+08:00',
        'address' => ['formatted' => 'Makati City'],
        'map' => 'data:image/png;base64,...',
    ],
    
    'selfie_capture' => [
        'selfie' => 'data:image/jpeg;base64,...',
    ],
    
    'signature_capture' => [
        'signature' => 'data:image/png;base64,...',
    ],
]
```

### Field Mapping in DisburseController

The `InputFieldMapper` service flattens and normalizes collected data:

```php
// app/Services/InputFieldMapper.php

public function map(array $data): array
{
    // Centralized field name mappings
    $mappings = [
        'full_name' => 'name',           // Normalize to 'name'
        'birthdate' => 'birth_date',     // Normalize spelling
        'phone' => 'mobile',             // Normalize to 'mobile'
        'signature_data' => 'signature', // Normalize naming
        // ... more mappings
    ];
    
    // Apply transformations
    foreach ($mappings as $from => $to) {
        if (isset($data[$from])) {
            $data[$to] = $data[$from];
            unset($data[$from]);
        }
    }
    
    return $data;
}
```

**DisburseController usage**:
```php
// DisburseController::redeem()

1. Retrieve collected_data from session
   $state = $this->formFlowService->getFlowState($flowId);
   $collectedData = $state['collected_data'];

2. Flatten all steps
   $flatData = [];
   foreach ($collectedData as $stepData) {
       $flatData = array_merge($flatData, $stepData);
   }

3. Apply field mappings
   $mapped = $this->fieldMapper->map($flatData);

4. Extract wallet fields
   $mobile = $mapped['mobile'];
   $bankAccount = [
       'bank_code' => $mapped['bank_code'],
       'account_number' => $mapped['account_number'],
   ];

5. Extract inputs (exclude wallet fields)
   $inputs = collect($mapped)
       ->except(['mobile', 'bank_code', 'account_number', 'amount'])
       ->toArray();

6. Pass to ProcessRedemption
   ProcessRedemption::run($voucher, $phoneNumber, $inputs, $bankAccount);
```

### Common Mapping Patterns

**Pattern 1: Simple text field**
```yaml
# Voucher instructions
inputs:
  - name: reference_code
    type: text

# Driver YAML
bio:
  fields:
    - name: "reference_code"
      type: "text"
      condition: "{{ has_reference_code }}"

# Collected
collected_data:
  bio_fields:
    reference_code: "ABC123"

# Final redemption
inputs:
  reference_code: "ABC123"
```

**Pattern 2: Media field with handler**
```yaml
# Voucher instructions
inputs:
  - name: signature
    type: signature

# Driver YAML
signature:
  handler: "signature"
  step_name: "signature_capture"
  condition: "{{ has_signature }}"

# Collected
collected_data:
  signature_capture:
    signature: "data:image/png;base64,..."

# Final redemption
inputs:
  signature: "data:image/png;base64,..."
```

**Pattern 3: KYC to bio auto-population**
```yaml
# Voucher instructions
inputs:
  - name: kyc
    type: kyc
  - name: name
    type: text

# Driver YAML
kyc:
  handler: "kyc"
  step_name: "kyc_verification"

bio:
  config:
    variables:
      $kyc_name: "$kyc_verification.name"
  fields:
    - name: "full_name"
      default: "$kyc_name"

# Collected
collected_data:
  kyc_verification:
    name: "John Doe"  # From HyperVerge
  bio_fields:
    full_name: "John Doe"  # User accepted KYC value

# Final redemption (KYC data in contact meta, name in inputs)
contact->meta:
  kyc_status: "approved"
  kyc_transaction_id: "..."
inputs:
  full_name: "John Doe"
```

### Debugging Input Transformation

**Enable verbose logging**:
```php
// In DriverService::transform()
Log::debug('[DriverService] Transform start', [
    'voucher' => $voucher->code,
    'inputs' => $voucher->instructions->inputs->pluck('name'),
]);

Log::debug('[DriverService] Context built', $context);

Log::debug('[DriverService] Steps generated', [
    'steps' => collect($instructions->steps)->map(fn($s) => $s['step_name']),
]);
```

**Check session state**:
```php
// In tinker
session()->get('form_flow.abc123');

// Or via helper
use LBHurtado\FormFlowManager\Services\FormFlowService;
$service = app(FormFlowService::class);
$state = $service->getFlowState('abc123');
dump($state['collected_data']);
```

**Verify field mapping**:
```php
// In DisburseController::redeem()
Log::debug('[DisburseController] Collected data structure', [
    'collected_data' => $collectedData,
]);

Log::debug('[DisburseController] After flattening', [
    'flat_data' => $flatData,
]);

Log::debug('[DisburseController] After mapping', [
    'mapped' => $mapped,
]);
```

**Test driver transformation**:
```bash
php artisan tinker
```

```php
use LBHurtado\FormFlowManager\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;

$driver = app(DriverService::class);
$voucher = Voucher::where('code', 'TEST123')->first();

// Check context generation
$reflection = new ReflectionClass($driver);
$method = $reflection->getMethod('buildContext');
$method->setAccessible(true);
$context = $method->invoke($driver, $voucher);
dump($context);

// Check full transformation
$instructions = $driver->transform($voucher);
dump($instructions);
```

---

## Testing & Debugging

### Testing Checklist

**Driver validation**:
- [ ] YAML syntax is valid
- [ ] All template variables exist in context
- [ ] Conditional expressions evaluate correctly
- [ ] Steps are ordered correctly (KYC before bio)
- [ ] Handler names match registered handlers

**Flow execution**:
- [ ] All steps render without errors
- [ ] Field validation works as expected
- [ ] Data is collected in correct format
- [ ] Auto-population works (KYC → bio)
- [ ] Callback is triggered on completion
- [ ] Session is cleared after redemption

**Integration**:
- [ ] Collected data maps to voucher inputs
- [ ] InputFieldMapper normalizes field names
- [ ] ProcessRedemption receives correct data
- [ ] Notifications include all input data
- [ ] Media files are stored correctly

### Common Errors

**"Handler 'location' not registered"**
```
Cause: Handler plugin not installed or service provider not loaded
Fix: 
  1. Check composer.json for package
  2. Run composer dump-autoload
  3. Verify config('form-flow.handlers') includes handler
```

**"YAML parse error"**
```
Cause: Invalid YAML syntax (indentation, quotes, etc.)
Fix:
  1. Use YAML validator: https://www.yamllint.com/
  2. Check for tabs (use spaces only)
  3. Verify template {{ }} syntax
```

**"Variable 'has_kyc' not found"**
```
Cause: Context doesn't include expected variable
Fix:
  1. Check buildContext() method
  2. Verify voucher has kyc input
  3. Log context in driver service
```

**"Session expired"**
```
Cause: User took too long or session cleared
Fix:
  1. Increase SESSION_LIFETIME in .env
  2. Check if session driver is persistent (database, redis)
  3. Ensure session middleware is applied
```

### Debugging Tools

**1. Laravel Telescope** (if installed)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```
View requests, queries, session data at `/telescope`

**2. Log Channels**
```php
// config/logging.php
'form_flow' => [
    'driver' => 'single',
    'path' => storage_path('logs/form-flow.log'),
    'level' => 'debug',
],

// In DriverService
Log::channel('form_flow')->debug('Transform complete', $data);
```

**3. Session Inspector**
```php
// Create artisan command
php artisan make:command InspectFormFlowSession

// In handle()
$flowId = $this->argument('flow_id');
$state = session()->get("form_flow.{$flowId}");
dump($state);
```

**4. Driver Validator**
```php
// Create test
use LBHurtado\FormFlowManager\Services\DriverService;

test('voucher-redemption driver is valid', function () {
    $driver = app(DriverService::class);
    $config = $driver->loadConfig('voucher-redemption');
    
    expect($config)->toBeInstanceOf(DriverConfigData::class);
    expect($config->driver->name)->toBe('voucher-redemption');
    expect($config->steps)->not->toBeEmpty();
});
```

---

## Known Limitations & Workarounds

This section documents current limitations in the form-flow system and their workarounds.

### Limitation 1: settlement_rail Not in Driver Context

**Issue**: The `settlement_rail` field from voucher instructions is not automatically passed to the driver context.

**Impact**: You cannot use `{{ settlement_rail }}` in YAML templates.

**Root Cause**: The `VoucherTemplateContextBuilder` (or equivalent context building in `DriverService::buildContext()`) doesn't extract `settlement_rail` from `voucher.instructions`.

**Workarounds**:

**Option A: Manual Context Addition** (Recommended)
```php
// app/Http/Controllers/Disburse/DisburseController.php
public function start(string $code)
{
    $voucher = Voucher::whereCode($code)->firstOrFail();
    
    // Load driver with explicit settlement_rail
    $instructions = $this->driverService->loadDriver('voucher-redemption', [
        'reference_id' => "disburse-{$voucher->code}",
        'voucher' => $voucher,
        'settlement_rail' => $voucher->instructions['settlement_rail'] ?? 'INSTAPAY',  // <-- Add this
        // ... other context variables
    ]);
}
```

Then use in YAML:
```yaml
fields:
  - name: "settlement_rail"
    type: "hidden"
    value: "{{ settlement_rail }}"
```

**Option B: Access via voucher.instructions** (If available)
```yaml
fields:
  - name: "settlement_rail"
    type: "hidden"
    value: "{{ voucher.instructions.settlement_rail ?? 'INSTAPAY' }}"
```

**Option C: Hardcode Temporarily**
```yaml
# Quick fix for testing
fields:
  - name: "settlement_rail"
    type: "hidden"
    value: "INSTAPAY"  # TODO: Make dynamic
```

**Future Fix**: Update `DriverService::buildContext()` to include:
```php
protected function buildContext(Voucher $voucher): array
{
    $instructions = $voucher->instructions;
    
    return [
        // ... existing context
        'settlement_rail' => $instructions->settlement_rail ?? 'INSTAPAY',  // Add this
    ];
}
```

**Tracking**: See `config/form-flow-drivers/voucher-redemption.yaml` line 111-114 for current workaround in use.

---

### Limitation 2: Handler-Specific Context Not Auto-Populated

**Issue**: Some handlers need specific data from previous steps (e.g., OTP handler needs mobile number), but there's no automatic cross-step data population.

**Impact**: You must manually pass data between steps via handler logic or YAML templates.

**Workarounds**:

**Option A: Handler Auto-Reads from collected_data**
```php
// In OTP Handler
public function render(FormFlowStepData $step, array $context = [])
{
    $mobile = $context['collected_data']['wallet_info']['mobile'] ?? null;
    
    return Inertia::render('form-flow/otp/OtpVerifyPage', [
        'mobile' => $mobile,  // Auto-populated from previous step
        // ...
    ]);
}
```

**Option B: Readonly Fields for Confirmation**
```yaml
# Show previous step data for review
fields:
  - name: "mobile_confirmed"
    type: "text"
    label: "Your Mobile Number"
    value: "{{ wallet_info.mobile ?? '' }}"
    readonly: true
```

Note: This relies on YAML template access to `collected_data` keys by step_name.

---

### Limitation 3: No Built-in Step Back Navigation

**Issue**: Users cannot go back to previous steps using browser back button without breaking flow state.

**Impact**: If user clicks back, session state may become inconsistent.

**Workarounds**:

**Option A: Disable Browser Back**
```js
// resources/js/pages/form-flow/components/FlowWrapper.vue
import { onMounted } from 'vue'

onMounted(() => {
  history.pushState(null, '', location.href)
  window.addEventListener('popstate', () => {
    history.pushState(null, '', location.href)
    alert('Please use the form navigation buttons.')
  })
})
```

**Option B: Implement Previous Button**
```php
// Add to FormFlowController
Route::post('/form-flow/{flow_id}/back', function ($flowId, FormFlowService $service) {
    $state = $service->getFlowState($flowId);
    $state['current_step'] = max(0, $state['current_step'] - 1);
    $service->updateFlowState($flowId, $state);
    
    return redirect("/form-flow/{$flowId}");
});
```

---

### Limitation 4: Session Persistence Across Devices

**Issue**: Flow state is session-based, so users cannot resume on a different device.

**Impact**: If user starts on mobile and switches to desktop, they must restart.

**Workarounds**:

**Option A: Reference-Based Resumption**
```php
// Store minimal data in database
$voucher->update(['flow_state' => json_encode($collectedData)]);

// Resume on any device
$state = $service->getFlowStateByReference($referenceId);
if (!$state) {
    // Restore from database
    $savedData = $voucher->flow_state;
    $state = $service->recreateFlow($instructions, json_decode($savedData, true));
}
```

**Option B: Magic Link for Resumption**
```php
// Email user a resumption link
$resumeToken = Str::random(32);
Cache::put("resume_{$resumeToken}", $flowId, now()->addHours(24));

Mail::to($user)->send(new ResumeFlowEmail(
    url("/form-flow/resume/{$resumeToken}")
));
```

---

### Limitation 5: Large Media Files and Session Size

**Issue**: Storing selfies/signatures as base64 in session can exceed session size limits.

**Impact**: Session storage fails for large images (>1MB).

**Workarounds**:

**Option A: Store Media Separately**
```php
// In Selfie/Signature Handler
public function handle(Request $request, FormFlowStepData $step, array $context = []): array
{
    $imageData = $request->input('data.selfie');
    
    // Store in temporary storage
    $path = Storage::disk('temp')->put('selfies', base64_decode($imageData));
    
    // Return path instead of data
    return [
        'selfie_path' => $path,
        'captured_at' => now()->toIso8601String(),
    ];
}
```

**Option B: Use Database Session Driver**
```bash
# .env
SESSION_DRIVER=database  # Supports larger payloads than file driver
```

```bash
php artisan session:table
php artisan migrate
```

---

### Limitation 6: Conditional Steps Don't Support Complex Logic

**Issue**: Conditions only support simple boolean/string comparisons, not complex expressions.

**Impact**: Cannot do things like `condition: "{{ amount > 1000 && is_premium }}"`

**Workarounds**:

**Option A: Pre-Compute Complex Conditions**
```php
// In controller context building
$context = [
    'requires_kyc' => ($amount > 1000 && $user->is_premium),  // Pre-computed
];
```

Then in YAML:
```yaml
condition: "{{ requires_kyc }}"
```

**Option B: Multiple Steps with AND Logic**
```yaml
# Step 1: Check amount
premium_kyc_step:
  condition: "{{ amount >= 1000 }}"
  # ... nested step with is_premium check
```

Note: This is verbose and not recommended for complex scenarios.

---

## Next Steps

After understanding this integration guide, proceed to:

1. **[HANDLERS.md](./HANDLERS.md)** - Learn to create custom handlers
2. **[INTEGRATION_CHECKLIST.md](./INTEGRATION_CHECKLIST.md)** - Quick setup reference
3. **[TROUBLESHOOTING.md](./TROUBLESHOOTING.md)** - Solve common issues
4. **[API.md](./API.md)** - Programmatic API reference
5. **[README.md](./README.md)** - Documentation index

---

## Additional Resources

- **Package Repository**: https://github.com/3neti/form-flow
- **Package Documentation**: `vendor/3neti/form-flow/README.md`
- **Driver Examples**: `config/form-flow-drivers/examples/`
- **Test Suite**: `vendor/3neti/form-flow/tests/`
- **YAML Architecture**: `docs/architecture/YAML_DRIVER_ARCHITECTURE.md`
- **Form Flow System**: `docs/architecture/FORM_FLOW_SYSTEM.md`

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-03  
**Maintained By**: Development Team
