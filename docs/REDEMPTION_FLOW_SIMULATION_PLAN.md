# Redemption Workflow Simulation in Form Flow Demo

## Problem Statement
Create a demo in `/form-flow-demo.html` that simulates the complete voucher redemption workflow using the Form Flow Manager system. This demonstrates the **end-to-end data transformation pipeline**:

**INPUT** → VoucherInstructionsData + VoucherMetadata + Driver Config (DirXML-style YAML)
**TRANSFORM** → Form Flow System (autonomous multi-step collection)
**OUTPUT** → Original VoucherInstructionsData + VoucherMetadata + Collected Inputs from all handlers

This shows how the redemption flow COULD work when refactored to use the autonomous form flow system instead of the current manual page-by-page navigation.

## Current State Analysis

### Current Redemption Flow (Manual Navigation)
The existing redemption system uses explicit routes and controllers:
1. **Start** (`/redeem`) - Enter voucher code
2. **Wallet** (`/redeem/{code}/wallet`) - Collect mobile + bank account via Inertia page
3. **Dynamic Inputs** - Individual routes for each input type:
   - `/redeem/{code}/inputs` - Text inputs (email, name, birthdate)
   - `/redeem/{code}/location` - Location capture
   - `/redeem/{code}/selfie` - Selfie photo
   - `/redeem/{code}/signature` - Digital signature
   - `/redeem/{code}/kyc/initiate` - KYC verification
4. **Finalize** (`/redeem/{code}/finalize`) - Review and confirm
5. **Confirm** (`POST /redeem/{code}/confirm`) - Execute redemption
6. **Success** (`/redeem/{code}/success`) - Show confirmation

**Key characteristics:**
- Session-based data storage (`redeem.{code}.mobile`, etc.)
- Manual navigation between pages
- `RedeemController` orchestrates the flow
- `ProcessRedemption` action executes at confirm step

### Voucher Instructions Structure
Vouchers are configured with `VoucherInstructionsData` containing:
- `cash`: Amount, currency, settlement rail, fee strategy
- `inputs.fields`: Array of input field types from `VoucherInputField` enum:
  - email, mobile, name, address, birth_date
  - location, selfie, signature, kyc
  - reference_code, gross_monthly_income, otp
- `feedback`: Email, mobile, webhook for notifications
- `rider`: Message and URL shown on success
- `validation`: Location/time validation rules

### Form Flow Manager System
The autonomous form flow system provides:
- **FormFlowService**: Session-based flow state management
- **FormFlowController**: HTTP endpoints (`/form-flow/start`, `/form-flow/{flow_id}`)
- **Handler Interface**: Pluggable handlers (form, location, selfie, signature, kyc)
- **Variables Block**: Server-side resolution with `$variable` syntax (newly implemented)
- **Auto-progression**: Automatic navigation between steps
- **Callbacks**: Webhooks on completion/cancellation

**Available Handlers:**
- `form` - Built-in handler for basic inputs (text, email, number, select, etc.)
- `location` - GPS capture with reverse geocoding
- `selfie` - Camera capture for selfie photos
- `signature` - Digital signature pad
- `kyc` - HyperVerge identity verification

### Form Flow Demo Structure
Current demo has buttons for:
- Basic Form Flow
- Form + Location Flow
- Location Only
- Form + Selfie + Location
- Form + Signature + Location
- Form + KYC + Location
- Demographics + Financial Info (newly added with variables)

## Proposed Solution

### Add New Demo Button: "Simulated Redemption Flow"
Create a comprehensive demo that mimics a real voucher redemption with 7 steps.

### Step Configuration

**Step 0: Voucher Code Entry (Form Handler)**
- Single text input for voucher code
- Variables: Mock voucher metadata (`$voucherCode`, `$voucherAmount`, `$settlementRail`, `$issuerName`)
- These variables become available to ALL subsequent steps via Phase 2 context

**Step 1: Wallet Information (Form Handler)**
- Amount field: Default `$step0_voucher_amount` (readonly)
- Settlement Rail: Default `$step0_settlement_rail` (editable)
- Mobile, Country (PH, readonly), Bank/EMI, Account Number
- Pre-populated from voucher metadata

**Step 2: Demographics (Form Handler)**
- Full name, Email, Birth date, Address
- Description shows data from previous steps: mobile, voucher code, amount, rail

**Step 3: Selfie (Selfie Handler)**
- Camera capture with image quality config

**Step 4: Location (Location Handler)**
- GPS capture with reverse geocoding and map snapshot

**Step 5: Signature (Signature Handler)**
- Digital signature pad

**Step 6: KYC Verification (KYC Handler)**
- HyperVerge integration (requires credentials)

**Completion Callback:**
- URL: `/form-flow-complete`
- Payload contains: Original voucher data + All collected inputs
- Ready for `ProcessRedemption` action

## Data Flow Architecture

### INPUT (What goes IN)
```
VoucherInstructionsData:
  - cash.amount: 500
  - cash.settlement_rail: INSTAPAY
  - inputs.fields: [email, mobile, name, location, selfie, signature, kyc]
  - feedback: {email, mobile, webhook}
  - rider: {message, url}
  - validation: {location rules, time rules}

VoucherMetadata:
  - code: DEMO-12345
  - issuer: Demo Merchant
  - expires_at: 2024-12-31

Driver Config (config/form-flow-drivers/voucher-redemption.yaml):
  - Field mapping: VoucherInstructionsData.cash.amount → step1.field[amount].default
  - Field mapping: VoucherInstructionsData.cash.settlement_rail → step1.field[settlement_rail].default
  - Field attributes: step1.field[amount].readonly = true
  - Field attributes: step1.field[recipient_country].readonly = true, default = 'PH'
  - Handler selection: inputs.fields["location"] → handler: 'location'
  - Handler selection: inputs.fields["selfie"] → handler: 'selfie'
  - Handler selection: inputs.fields["kyc"] → handler: 'kyc'
  - Variable resolution: voucher.code → $voucherCode (available in all steps)
  - Variable resolution: voucher.issuer → $issuerName (available in all steps)
  - Context propagation: Step N collected data → $stepN_fieldname variables
```

### TRANSFORM (What happens)
The Driver Config YAML defines the transformation:
- Maps voucher fields to form fields
- Sets field attributes (readonly, required, defaults)
- Selects appropriate handlers based on input types
- Creates variables for cross-step data access
- Injects validation rules into handler configs

Form Flow System executes:
1. Driver reads VoucherInstructionsData + metadata
2. Driver generates FormFlowInstructionsData per YAML rules
3. Variables resolve: $voucherAmount → field.default
4. Phase 2 context: $step0_voucher_code → available in all steps
5. Handlers execute: form, selfie, location, signature, kyc
6. Each step collects data → stored in flow state
7. Auto-progression: step N complete → render step N+1

### OUTPUT (What comes OUT)
```
Original Input (preserved):
  - VoucherInstructionsData (unchanged)
  - VoucherMetadata (unchanged)

Collected Data (new):
  - step0: {voucher_code: "DEMO-12345"}
  - step1: {amount: 500, settlement_rail: "INSTAPAY", mobile: "+639...", bank_code: "GXCHPHM2XXX", account_number: "..."}
  - step2: {full_name: "Juan", email: "juan@example.com", birth_date: "1990-01-01", address: "..."}
  - step3: {selfie: "base64_image..."}
  - step4: {location: {lat: 14.5995, lng: 120.9842, address: "Manila", snapshot: "base64..."}}
  - step5: {signature: "base64_image..."}
  - step6: {kyc: {status: "approved", transaction_id: "xyz", submitted_at: "..."}}

Ready for ProcessRedemption:
  - Has all original voucher data
  - Has all collected user inputs
  - Has all verification artifacts (selfie, location, signature, kyc)
```

## Files to Modify

### public/form-flow-demo.html
**Changes:**
- Add new button: "Simulated Redemption Flow" (indigo #6366f1)
- Add `startRedemptionFlow()` function
- Configure 7-step flow with all handlers
- Use variables block for pre-population
- Use Phase 2 context variables for cross-step references

### No Backend Changes Required
All handlers already exist and are functional. Variables resolution already implemented.

## Testing Strategy
1. **Smoke Test**: Click button, verify flow creation
2. **Step Progression**: Complete each step, verify navigation
3. **Variables Resolution**: Check defaults appear correctly
4. **Phase 2 Context**: Verify step descriptions show data from previous steps
5. **Handler Integration**: Test each handler (selfie, location, signature, KYC)
6. **Completion**: Verify callback triggers and data displayed

## Success Criteria
- [ ] New "Simulated Redemption Flow" button added to demo page
- [ ] 7-step flow created with proper configuration
- [ ] All steps navigate correctly
- [ ] Variables resolve and pre-populate fields
- [ ] Phase 2 context variables work
- [ ] Handlers render correctly (form, selfie, location, signature, kyc)
- [ ] Completion callback displays collected data
- [ ] Demo accurately represents real redemption workflow

## Notes

**This is a SIMULATION:**
- No actual voucher validation
- No disbursement execution
- No real KYC verification (requires credentials)
- Demonstrates the COMPLETE data transformation pipeline

**Future Refactoring:**
When ready to refactor actual redemption:
1. **Input Layer**: Load VoucherInstructionsData + metadata from database
2. **Transform Layer**: `VoucherRedemptionDriver` reads YAML config, applies mapping rules:
   - Maps `cash.amount` → `step1.fields[amount].default`
   - Maps `cash.settlement_rail` → `step1.fields[settlement_rail].default`
   - Sets `step1.fields[amount].readonly = true`
   - Transforms `inputs.fields` array → handler steps (location → location handler, selfie → selfie handler)
   - Injects validation rules from `validation.location` → location handler config
   - Creates variables block with voucher metadata
3. **Collection Layer**: FormFlowService orchestrates handler execution with transformed config
4. **Output Layer**: Callback receives original voucher data + collected inputs
5. **Action Layer**: `ProcessRedemption` action receives complete payload, executes disbursement

**Key Insight**: The Driver Config YAML is the **declarative mapping layer**. It defines:
- Which voucher fields map to which form fields
- Which fields get defaults (and from where)
- Which fields are readonly vs editable
- Which input types trigger which handlers
- Which validation rules apply to which fields

The form flow system executes these mappings autonomously, then returns everything needed for business logic.
