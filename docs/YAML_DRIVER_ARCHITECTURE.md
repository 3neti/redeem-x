# YAML Driver Architecture

## Overview

The YAML driver transforms domain models (Vouchers) into Form Flow instructions using declarative YAML configuration, enabling platform-agnostic, maintainable form flows without hardcoded PHP logic.

## High-Level Flow

```
┌─────────────┐
│   Voucher   │ (Domain Model)
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│  DisburseController │ (/disburse?code=BW3P)
└──────┬──────────────┘
       │ calls transform()
       ▼
┌──────────────────┐
│  DriverService   │ (YAML-only, 233 LOC)
└──────┬───────────┘
       │ 1. buildContext()
       │ 2. loadConfig() → voucher-redemption.yaml
       │ 3. processSteps() → FormFlowInstructionsData
       ▼
┌─────────────────────┐
│ FormFlowService     │ (Session-based state management)
└──────┬──────────────┘
       │ startFlow() → creates session state
       ▼
┌─────────────────────┐
│ FormFlowController  │ (/form-flow/{flow_id})
└──────┬──────────────┘
       │ Renders each step via handlers
       ▼
┌──────────────────────────────┐
│  FormHandler / KYCHandler    │ (Step-specific handlers)
│  LocationHandler / etc.      │
└──────┬───────────────────────┘
       │ Collects data → collected_data array
       ▼
┌─────────────────────┐
│  Complete Page      │ (/form-flow/{flow_id} - all steps done)
└──────┬──────────────┘
       │ Triggers callback
       ▼
┌─────────────────────┐
│ DisburseController  │ /disburse/{code}/complete
│ ::redeem()          │
└──────┬──────────────┘
       │ Maps collected_data → ProcessRedemption
       ▼
┌─────────────────────┐
│  ProcessRedemption  │ (Business logic: validate, disburse, notify)
└─────────────────────┘
```

---

## Detailed Components

### 1. Entry Point: DisburseController

**File:** `app/Http/Controllers/Disburse/DisburseController.php`

**Responsibilities:**
- Validate voucher code
- Check voucher status (redeemed, expired, etc.)
- Call DriverService to transform voucher → form flow

**Key Method:**
```php
public function initiateFlow(string $code): RedirectResponse
{
    $voucher = Voucher::where('code', $code)->firstOrFail();
    
    // Transform using YAML driver
    $instructions = $this->driverService->transform($voucher);
    
    // Start form flow session
    $state = $this->formFlowService->startFlow($instructions);
    
    // Redirect to form flow UI
    return redirect("/form-flow/{$state['flow_id']}");
}
```

**Data Flow:**
- Input: Voucher code (e.g., "BW3P")
- Output: Redirect to form flow with session-based flow_id

---

### 2. Core: DriverService (YAML-Only)

**File:** `packages/form-flow-manager/src/Services/DriverService.php`

**Responsibilities:**
- Load YAML configuration
- Build context from voucher
- Process steps with template rendering
- Return FormFlowInstructionsData

**Architecture:**
```
DriverService
├── loadConfig()              → Parses YAML file
├── transform()               → Main entry point
├── buildContext()            → Extracts voucher data
├── processReferenceId()      → Template: "disburse-{code}-{timestamp}"
├── processCallbacks()        → Template: "{base_url}/disburse/{code}/complete"
├── processSteps()            → Iterate steps, apply conditions
├── processFields()           → Field-level conditions & templates
└── evaluateCondition()       → Boolean expression evaluation
```

**Key Data Structures:**

**Context (Built from Voucher):**
```php
[
    'code' => 'BW3P',
    'amount' => 5000,  // in centavos
    'currency' => 'PHP',
    'owner_name' => 'Lester Hurtado',
    'base_url' => 'http://redeem-x.test',
    'timestamp' => 1734221420,
    
    // Conditional flags
    'has_name' => true,
    'has_email' => false,
    'has_birth_date' => true,
    'has_address' => false,
    'has_location' => true,
    'has_selfie' => true,
    'has_signature' => true,
    'has_kyc' => true,
]
```

**Output (FormFlowInstructionsData):**
```php
[
    'reference_id' => 'disburse-BW3P-1734221420',
    'steps' => [
        ['handler' => 'form', 'config' => [...]],      // Wallet
        ['handler' => 'kyc', 'config' => [...]],       // KYC
        ['handler' => 'form', 'config' => [...]],      // BIO
        ['handler' => 'location', 'config' => [...]],  // Location
        // ... conditionally included
    ],
    'callbacks' => [
        'on_complete' => 'http://redeem-x.test/disburse/BW3P/complete',
        'on_cancel' => 'http://redeem-x.test/disburse',
    ],
]
```

---

### 3. Configuration: voucher-redemption.yaml

**File:** `config/form-flow-drivers/voucher-redemption.yaml`

**Structure:**
```yaml
driver:
  name: "voucher-redemption"
  version: "1.0"
  source: "VoucherInstructionsData"
  target: "FormFlowInstructionsData"

reference_id: "disburse-{{ code }}-{{ timestamp }}"

callbacks:
  on_complete: "{{ base_url }}/disburse/{{ code }}/complete"
  on_cancel: "{{ base_url }}/disburse"

steps:
  wallet:          # Step 0 (always present)
    handler: "form"
    config:
      auto_sync: [...]  # Mobile → Account number
    fields: [...]
    
  kyc:             # Step 1 (conditional: has_kyc)
    handler: "kyc"
    condition: "{{ has_kyc }}"
    
  bio:             # Step 2 (conditional: has bio fields)
    handler: "form"
    condition: "{{ has_name or has_birth_date or has_address }}"
    config:
      variables:   # Indirect references to KYC data
        $kyc_name: "$step1_name"
        $kyc_birth: "$step1_date_of_birth"
    fields:
      - name: "full_name"
        default: "$kyc_name"  # Auto-populates from KYC
```

**Key Features:**
- **Conditions:** Steps only included if condition evaluates to true
- **Templates:** `{{ variable }}` syntax for YAML-time rendering
- **Variables:** `$variable` syntax for runtime resolution
- **Auto-sync:** Declarative field synchronization

---

### 4. State Management: FormFlowService

**File:** `packages/form-flow-manager/src/Services/FormFlowService.php`

**Responsibilities:**
- Store flow state in session
- Track current step index
- Collect data from each step
- Manage flow lifecycle

**Session Structure:**
```php
session("form_flow.{flow_id}") => [
    'flow_id' => 'flow-693f4dc5b8db61',
    'reference_id' => 'disburse-BW3P-1734221420',
    'status' => 'in_progress',  // or 'completed'
    'current_step' => 2,
    'completed_steps' => [0, 1],
    'instructions' => [...],  // Original FormFlowInstructionsData
    'collected_data' => [
        0 => ['amount' => 50, 'mobile' => '09173011987', ...],  // Wallet
        1 => ['name' => 'HURTADO LESTER', 'date_of_birth' => '1970-04-21', ...],  // KYC
        2 => null,  // BIO (not yet completed)
    ],
    'created_at' => '2024-12-15T02:03:40Z',
    'updated_at' => '2024-12-15T02:04:15Z',
]
```

**Key Methods:**
- `startFlow()` - Initialize session
- `updateStepData()` - Save step data
- `getFlowState()` - Retrieve current state
- `completeFlow()` - Mark as complete, trigger callback

---

### 5. Step Execution: FormFlowController

**File:** `packages/form-flow-manager/src/Http/Controllers/FormFlowController.php`

**Responsibilities:**
- Route to correct handler for current step
- Pass context (flow_id, collected_data) to handlers
- Handle step submission and validation
- Trigger callbacks on completion

**Request Flow:**
```
GET /form-flow/{flow_id}
  ↓
1. Load state from session
2. Get current step config
3. Resolve handler (form, kyc, location, etc.)
4. Render handler view with context
  ↓
User fills form
  ↓
POST /form-flow/{flow_id}/step/{step_index}
  ↓
1. Validate via handler
2. Save to collected_data
3. Increment current_step
4. Redirect to next step
```

---

### 6. Data Collection: FormHandler

**File:** `packages/form-flow-manager/src/Handlers/FormHandler.php`

**Responsibilities:**
- Render generic form fields
- Resolve variables from collected_data
- Validate submitted data
- Return validated data for storage

**Variable Resolution (Lines 203-272):**

**Phase 1: Auto-populate from collected_data**
```php
// Create $step{N}_{fieldname} variables
foreach ($collectedData as $stepIndex => $stepData) {
    foreach ($stepData as $key => $value) {
        $variables["$step{$stepIndex}_{$key}"] = $value;
    }
}

// Example after KYC (step 1):
// $step1_name => "HURTADO LESTER"
// $step1_date_of_birth => "1970-04-21"
// $step1_address => "123 Main St..."
```

**Phase 2: Resolve indirect references**
```php
// YAML defines: $kyc_name: "$step1_name"
// Resolution: $kyc_name = "HURTADO LESTER"
```

**Phase 3: Apply to field defaults**
```php
// Field config: default: "$kyc_name"
// Resolved: default: "HURTADO LESTER"
```

**Key Insight:** This is how KYC auto-population works! The transient data flows through `collected_data` array, not from database.

---

### 7. Template Processing: TemplateProcessor

**File:** `packages/form-flow-manager/src/Services/TemplateProcessor.php`

**Responsibilities:**
- Parse `{{ variable }}` templates
- Apply filters: `{{ amount | format_money }}`
- Evaluate conditionals: `{{ has_kyc }}`
- Support boolean expressions: `{{ has_name or has_email }}`

**Example Transformations:**
```
Template: "{{ code }}"
Context: {code: "BW3P"}
Result: "BW3P"

Template: "{{ amount | format_money }}"
Context: {amount: 5000}
Result: "₱50.00"

Template: "{{ has_name or has_email }}"
Context: {has_name: true, has_email: false}
Result: "true"
```

---

## Data Flow: Complete Example

### Voucher BW3P Flow

**1. User visits:** `http://redeem-x.test/disburse?code=BW3P`

**2. DisburseController validates & transforms:**
```php
$voucher = Voucher::where('code', 'BW3P')->first();
// amount: 50, has_kyc: true, has_name: true, has_birth_date: true
```

**3. DriverService builds context:**
```php
[
    'code' => 'BW3P',
    'amount' => 50,
    'has_kyc' => true,
    'has_name' => true,
    'has_birth_date' => true,
    // ...
]
```

**4. DriverService processes YAML:**
- Wallet step: Always included
- KYC step: Included (has_kyc = true)
- BIO step: Included (has_name = true)
- Location step: Included (has_location = true)
- Others: Conditionally included

**5. FormFlowService creates session:**
```php
session("form_flow.flow-xyz123") => [
    'reference_id' => 'disburse-BW3P-1734221420',
    'current_step' => 0,
    'collected_data' => [],
]
```

**6. User completes steps:**

**Step 0 (Wallet):**
- User enters: mobile="09173011987", bank_code="GXCHPHM2XXX", etc.
- Saved to: `collected_data[0]`
- Auto-sync: mobile → account_number (debounced 1500ms)

**Step 1 (KYC - Fake Mode):**
- Handler returns: name="HURTADO LESTER", date_of_birth="1970-04-21", address="123 Main St"
- Saved to: `collected_data[1]`

**Step 2 (BIO):**
- FormHandler resolves variables:
  - `$step1_name` = "HURTADO LESTER"
  - `$kyc_name` → `$step1_name` = "HURTADO LESTER"
- Field default: `default: "$kyc_name"` → `default: "HURTADO LESTER"`
- Form renders with pre-filled name!
- User confirms or modifies
- Saved to: `collected_data[2]`

**7. Complete callback:**
```php
POST /disburse/BW3P/complete
→ DisburseController::redeem()
→ Maps collected_data to ProcessRedemption
→ Validates, disburses, sends notifications
```

---

## Key Design Patterns

### 1. Declarative Configuration
- **What:** YAML defines flow structure, not code
- **Benefit:** Non-developers can modify flows
- **Trade-off:** Less flexibility for complex logic

### 2. Template + Variable Two-Phase Resolution
- **Phase 1 (Build Time):** `{{ variable }}` → YAML templates
- **Phase 2 (Runtime):** `$variable` → FormHandler variables
- **Benefit:** Separates static vs. dynamic data
- **Example:** `{{ code }}` is static, `$step1_name` is dynamic

### 3. Auto-Discovery from collected_data
- **What:** FormHandler creates `$step{N}_{field}` variables automatically
- **Benefit:** No manual wiring needed
- **Trade-off:** Step order matters (dependency on indices)

### 4. Session-Based State
- **What:** Flow state stored in user session
- **Benefit:** Simple, stateless controllers
- **Trade-off:** Lost on session expiration

### 5. Callback Pattern
- **What:** Form flow calls back to application on completion
- **Benefit:** Decouples form collection from business logic
- **Trade-off:** Requires endpoint implementation

---

## Critical Dependencies

### Step Order Matters!
```
✅ CORRECT:
0. Wallet
1. KYC        → Collects: name, date_of_birth
2. BIO        → References: $step1_name

❌ WRONG:
0. Wallet
1. BIO        → References: $step5_name (doesn't exist yet!)
2. KYC        → Collects: name, date_of_birth
```

**Solution (TODO):** Named step references instead of index-based

### Variable Name Contracts
- KYC Handler returns: `name`, `date_of_birth`, `address`
- BIO Form expects: field name = `full_name` (for schema)
- YAML maps: `default: "$kyc_name"` where `$kyc_name: "$step1_name"`

**Mismatch = Literal string bug** (as experienced with BW3P)

---

## Testing & Debugging

### Enable Debug Logging
```php
// See variable resolution
\Log::debug('[FormHandler] Variables', ['variables' => $variables]);

// See collected data
\Log::debug('[FormFlowController] Collected data', ['collected_data' => $collectedData]);
```

### Inspect Session
```bash
php artisan tinker
>>> session()->all()
>>> session('form_flow.flow-xyz123')
```

### Test Flow
```bash
# Generate test voucher with KYC
php artisan test:voucher --amount=5000 --with-kyc --with-name --with-birth_date

# Visit disburse flow
open http://redeem-x.test/disburse?code=GENERATED_CODE
```

---

## Future Enhancements (from TODO.md)

1. **Named Step References** - Replace `$step1_name` with `$kyc.name`
2. **Priority-Based Ordering** - Automatic topological sort
3. **Dependency Declarations** - Explicit `depends_on: ["kyc"]`
4. **Step Branching** - Conditional paths based on answers
5. **Async Handlers** - Long-running verifications

---

**Last Updated:** 2025-12-15  
**Author:** WARP AI Assistant  
**Version:** 2.0.0 (YAML-only, PHP driver removed)
