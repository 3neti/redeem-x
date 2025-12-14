# Redemption Workflow Simulation

## Overview
The redemption workflow simulation demonstrates the complete voucher redemption flow using the Form Flow Manager system. It simulates the user journey from wallet information collection through KYC verification, using real voucher data to pre-populate form fields.

## Architecture

### Data Flow
```
Real Voucher Data (API generated)
  ↓
Variables Block (voucher metadata)
  ↓
Form Flow Manager (6-step wizard)
  ↓
Collected Data (all user inputs + original voucher data)
```

### Implementation Location
- **Demo Page**: `public/form-flow-demo.html`
- **Button**: "Simulate Redemption Flow" (indigo color #6366f1)
- **Flow Type**: POST to `/form-flow/start` with pre-configured steps

## Flow Steps

### Step 0: Wallet Information
**Purpose**: Collect disbursement details with voucher context

**Auto-Sync Configuration**:
```javascript
auto_sync: {
  enabled: true,
  source_field: 'mobile',
  target_field: 'account_number',
  condition_field: 'settlement_rail',
  condition_values: ['INSTAPAY'],
  debounce_ms: 1500
}
```
When settlement rail is INSTAPAY, mobile number automatically syncs to account_number after 1.5s debounce. Manual edits to account_number disable auto-sync.

**Variables**:
- `$voucherCode` - The voucher code being redeemed
- `$voucherAmount` - Voucher amount (e.g., 750)
- `$voucherCurrency` - Currency code (e.g., PHP)
- `$settlementRail` - Payment rail (INSTAPAY or PESONET)
- `$issuerName` - Who issued the voucher
- `$defaultCountry` - Default country code (PH)
- `$defaultBank` - Default bank code (GXCHPHM2XXX for GCash)

**Fields**:
- `amount` - Readonly, pre-filled with `$voucherAmount`
- `settlement_rail` - Select (INSTAPAY/PESONET), default: `$settlementRail`
- `mobile` - Text input for mobile number
- `recipient_country` - Readonly, default: PH
- `bank_code` - Bank/EMI selector, default: `$defaultBank`
- `account_number` - Text input for account number (auto-synced from mobile when INSTAPAY)

**Description**: "Redeeming voucher {CODE} - ₱{AMOUNT} from {ISSUER}"

### Step 1: KYC Verification
**Handler**: `kyc`
**Purpose**: Identity verification via HyperVerge (moved to Step 1 for data extraction)

**Config**:
- `title`: "Identity Verification - KYC"
- `description`: "Complete identity verification to auto-fill your details"

**Data Returned** (flattened for Phase 2 variables):
- `name` - Full name from ID card (e.g., "HURTADO LESTER BIADORA")
- `date_of_birth` - Birth date from ID card (e.g., "1970-04-21")
- `address` - Address from ID card (if available)
- `transaction_id` - KYC transaction ID
- `status` - KYC status (approved, pending, rejected)

### Step 2: Basic Information
**Purpose**: Collect personal information (pre-populated from KYC)

**Phase 2 Context Variables** (from Step 1 KYC):
- `$step1_name` - Full name from KYC
- `$step1_date_of_birth` - Birth date from KYC
- `$step1_address` - Address from KYC

**Fields**:
- `full_name` - Text input, default: `$step1_name`
- `birth_date` - Date input, default: `$step1_date_of_birth`
- `address` - Textarea, default: `$step1_address`
- `email` - Email input (not available from KYC)

**Description**: "Verify your details from KYC"

### Step 3: Selfie Capture
**Handler**: `selfie`
**Purpose**: Identity verification via photo

**Config**:
- `title`: "Take a Selfie"
- `description`: "Please take a clear selfie for identity verification"
- `require_face_detection`: true

### Step 4: Location Capture
**Handler**: `location`
**Purpose**: GPS coordinates and address capture

**Config**:
- `title`: "Share Your Location"
- `description`: "We need your current location for verification"
- `require_address`: true

### Step 5: Signature Capture
**Handler**: `signature`
**Purpose**: Digital signature for agreement

**Config**:
- `title`: "Sign Here"
- `description`: "Please provide your digital signature"
- `required`: true


## Key Features

### 1. Auto-Sync Configuration
**Configuration-driven field synchronization** for dynamic form behavior:
- Specify source/target fields, condition field, and trigger values in JSON
- Debounced updates (configurable delay)
- Manual override detection prevents unwanted syncing
- Reset on condition change

**Example**: Mobile number auto-syncs to account_number when settlement_rail is INSTAPAY (for GCash, PayMaya)

**Configuration Schema**:
```javascript
{
  auto_sync: {
    enabled: true,
    source_field: 'mobile',
    target_field: 'account_number',
    condition_field: 'settlement_rail',
    condition_values: ['INSTAPAY'],
    debounce_ms: 1500
  }
}
```

### 2. KYC Data Extraction & Pre-Population
**Automatic data extraction** from KYC verification results:
- KYC handler flattens nested response data for Phase 2 variables
- Extracted fields: `name`, `date_of_birth`, `address`, `id_number`, `id_type`
- Phase 2 variables (`$step1_name`, `$step1_date_of_birth`, etc.) pre-populate subsequent form fields
- User can edit pre-filled values if needed

**Data Flow**:
```
KYC Handler → Flattened Data → Phase 2 Variables → Basic Info Step (pre-filled)
```

### 3. Variable Resolution
Variables from the variables block are automatically resolved throughout the flow:
- Field defaults: `$voucherAmount` → `amount` field
- Descriptions: "Redeeming voucher $voucherCode"
- Validation context: Amount limits based on `$settlementRail`

### 4. Phase 2 Context Variables
Data from previous steps is available in subsequent steps:
- Format: `$step{N}_{fieldname}`
- Example: `$step0_mobile` references the mobile field from Step 0
- Automatically populated by FormHandler from `collected_data`

### 5. Readonly Fields
Certain fields are marked readonly to prevent modification:
- `amount` - Fixed by voucher
- `recipient_country` - Fixed to PH for this demo

### 4. Real Voucher Data
The simulation uses real voucher data generated via API:
```javascript
const voucherData = {
  code: 'REDEMPTION-YZYP',
  amount: 750,
  currency: 'PHP',
  settlement_rail: 'PESONET',
  issuer_name: 'Lester B. Hurtado'
}
```

## Testing the Flow

### 1. Enable KYC Fake Mode
Add to `.env` to use mock KYC data:
```bash
KYC_USE_FAKE=true
```

Mock KYC data returned:
- **Name**: HURTADO LESTER BIADORA
- **Birth Date**: 1970-04-21
- **Address**: 123 Main Street, Quezon City, Metro Manila, Philippines
- **ID Number**: N01-87-049586
- **ID Type**: National ID

### 2. Start Simulation
1. Open `http://redeem-x.test/form-flow-demo.html`
2. Click "Simulate Redemption Flow" (indigo button)
3. Complete each step:
   - **Step 0 (Wallet)**: Enter mobile (e.g., `09173011987`), change rail to INSTAPAY to test auto-sync
   - **Step 1 (KYC)**: Click "Submit" (fake mode auto-approves)
   - **Step 2 (Basic Info)**: Verify name/birthdate/address are pre-filled from KYC, add email
   - **Steps 3-5**: Complete Selfie, Location, Signature

### 3. Test Auto-Sync
1. In Step 0, change **Payment Method** to **INSTAPAY**
2. Enter mobile number (e.g., `09173011987`)
3. Wait 1.5 seconds → **Account Number** should auto-fill with same value
4. Manually edit Account Number → auto-sync should stop
5. Change Payment Method to **PESONET** → Account Number clears, auto-sync resets

### 4. Verify Output
Check browser console for final collected data:
```javascript
{
  "reference_id": "redemption-REDEMPTION-YZYP-1765701225086",
  "step_0": {  // Wallet Information
    "amount": "750",
    "settlement_rail": "INSTAPAY",
    "mobile": "09173011987",
    "bank_code": "GXCHPHM2XXX",
    "account_number": "09173011987"  // Auto-synced from mobile!
  },
  "step_1": {  // KYC Verification
    "transaction_id": "MOCK-KYC-1234567890",
    "status": "approved",
    "name": "HURTADO LESTER BIADORA",
    "date_of_birth": "1970-04-21",
    "address": "123 Main Street, Quezon City, Metro Manila, Philippines",
    "id_number": "N01-87-049586",
    "id_type": "National ID"
  },
  "step_2": {  // Basic Information (pre-filled from KYC)
    "full_name": "HURTADO LESTER BIADORA",
    "birth_date": "1970-04-21",
    "address": "123 Main Street, Quezon City, Metro Manila, Philippines",
    "email": "user@example.com"
  },
  "step_3": {  // Selfie
    "selfie_image": "data:image/jpeg;base64,..."
  },
  "step_4": {  // Location
    "latitude": "14.5995",
    "longitude": "120.9842",
    "address": "Quezon City, Metro Manila"
  },
  "step_5": {  // Signature
    "signature": "data:image/png;base64,..."
  }
}
```

## Integration with Host Application

### Current State
The simulation is standalone in `form-flow-demo.html` for demonstration purposes.

### Future Integration
To integrate with the actual `/redeem` endpoint:

1. **RedeemController** calls Form Flow Manager after voucher validation:
```php
// After validating voucher code
$voucher = Voucher::whereCode($code)->firstOrFail();

$formFlowUrl = app(FormFlowService::class)->initiateFlow(
    referenceId: "redemption-{$voucher->code}-" . time(),
    steps: $this->buildStepsFromVoucher($voucher),
    callbacks: [
        'on_complete' => route('redeem.complete', $voucher),
        'on_cancel' => route('redeem.cancel', $voucher)
    ]
);

return redirect($formFlowUrl);
```

2. **buildStepsFromVoucher()** transforms VoucherInstructionsData:
```php
private function buildStepsFromVoucher(Voucher $voucher): array
{
    $instructions = $voucher->instructions; // VoucherInstructionsData
    
    return [
        [
            'handler' => 'form',
            'config' => [
                'title' => 'Wallet Information',
                'variables' => [
                    'voucherCode' => $voucher->code,
                    'voucherAmount' => $instructions->cash->amount,
                    'settlementRail' => $instructions->cash->settlement_rail,
                    // ... more variables
                ],
                'fields' => [
                    // Build from $instructions->input_fields
                ]
            ]
        ],
        // ... more steps
    ];
}
```

3. **Completion callback** processes collected data:
```php
Route::post('/redeem/{voucher}/complete', function(Voucher $voucher) {
    $collectedData = session()->get("form_flow_data.{$referenceId}");
    
    // Process redemption with collected data
    ProcessRedemption::run($voucher, $collectedData);
    
    return view('redeem.success');
})->name('redeem.complete');
```

## Driver Config YAML (Future)
A YAML-based driver config will define the transformation from VoucherInstructionsData to FormFlowInstructionsData:

```yaml
# config/form-flow-drivers/voucher-redemption.yaml
name: voucher-redemption
version: 1.0

variables:
  voucherCode: "{{ voucher.code }}"
  voucherAmount: "{{ voucher.instructions.cash.amount }}"
  settlementRail: "{{ voucher.instructions.cash.settlement_rail }}"
  issuerName: "{{ voucher.issuer.name }}"

steps:
  - handler: form
    title: "Wallet Information"
    description: "Redeeming voucher {{ $voucherCode }} - ₱{{ $voucherAmount }} from {{ $issuerName }}"
    fields:
      - name: amount
        type: number
        default: "{{ $voucherAmount }}"
        readonly: true
      - name: settlement_rail
        type: select
        default: "{{ $settlementRail }}"
        options: ["INSTAPAY", "PESONET"]
      # ... more fields

  - handler: form
    title: "Demographics"
    condition: "{{ 'name' in voucher.instructions.input_fields }}"
    fields:
      # Dynamically built from input_fields
```

## Lessons Learned

### 1. Variable Resolution Timing
Variables must be resolved in both `render()` and `handle()` contexts:
- `render()`: For initial field defaults and descriptions
- `handle()`: For validation rules that reference variables

### 2. Phase 2 Context Variables
Automatic context variable population simplifies step dependencies:
- No manual passing of data between steps
- Consistent naming: `$step{N}_{fieldname}`
- Works in descriptions, defaults, and validation

### 3. Readonly vs Disabled
Use `readonly: true` instead of `disabled: true`:
- Readonly fields are included in form submission
- Disabled fields are excluded from validation
- Readonly provides better UX for pre-filled values

### 4. Real Data Testing
Testing with real voucher data revealed:
- Variable resolution works correctly across all field types
- Phase 2 context variables populate dynamically
- Readonly fields maintain their values through navigation
- All handlers (selfie, location, signature, kyc) integrate seamlessly

## Next Steps

1. **Driver Config Implementation**: Create YAML-based transformation engine
2. **Host Integration**: Connect `/redeem` endpoint to Form Flow Manager
3. **Validation Enhancement**: Add settlement rail-specific amount limits
4. **Error Handling**: Graceful fallbacks for missing plugins
5. **Analytics**: Track completion rates and drop-off points

## Related Documentation
- [Form Flow Manager](../packages/form-flow-manager/README.md)
- [Plugin Architecture](../packages/form-flow-manager/PLUGIN_ARCHITECTURE.md)
- [Variables Feature](../packages/form-flow-manager/docs/VARIABLES_FEATURE.md)
- [Notification Templates](./NOTIFICATION_TEMPLATES.md)
- [Voucher Instructions](./VOUCHER_INSTRUCTIONS.md)
