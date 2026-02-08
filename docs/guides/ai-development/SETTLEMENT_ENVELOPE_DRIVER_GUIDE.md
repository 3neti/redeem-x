# Settlement Envelope Driver Development Guide

A comprehensive guide for creating custom settlement envelope drivers, including schema reference, design patterns, and AI-assisted scaffolding prompts.

## Table of Contents
1. [Driver Fundamentals](#1-driver-fundamentals)
2. [Driver Dissection - Real Examples](#2-driver-dissection---real-examples)
3. [Design Decision Framework](#3-design-decision-framework)
4. [Creating a New Driver](#4-creating-a-new-driver)
5. [AI-Assisted Driver Scaffolding](#5-ai-assisted-driver-scaffolding)
6. [Evolution of payable.default](#6-evolution-of-payabledefault)
7. [Testing Drivers](#7-testing-drivers)
8. [Best Practices](#8-best-practices)

---

## 1. Driver Fundamentals

### 1.1 What is a Driver?

A **driver** is a YAML configuration file that defines the complete behavior of a settlement envelope. It controls:

- **Payload schema** - What data must be collected
- **Documents** - What files can be uploaded
- **Checklist** - What items must be satisfied before settlement
- **Signals** - What manual/system approvals are required
- **Gates** - What conditions determine settlement readiness

**Location:** `storage/app/envelope-drivers/{driver-id}/v{version}.yaml`

**Example:** `storage/app/envelope-drivers/payable.default/v1.0.0.yaml`

### 1.2 Driver Anatomy

Every driver YAML file has these sections:

```yaml
driver:        # Metadata (required)
payload:       # Data schema (required)
documents:     # Uploadable file types (required)
checklist:     # Items to satisfy (required)
signals:       # Boolean flags (required, can be empty)
gates:         # Readiness rules (required)
audit:         # Logging config (optional)
manifest:      # Integrity config (optional)
ui:            # UI hints (optional)
```

### 1.3 Complete YAML Schema Reference

This schema is derived from `packages/settlement-envelope/src/Data/DriverData.php` (Spatie Laravel Data classes). Use this as the authoritative reference when creating drivers.

#### Complete Driver Template

```yaml
# =============================================================================
# SETTLEMENT ENVELOPE DRIVER SCHEMA v1.0
# =============================================================================
# This template documents ALL available fields with their types and defaults.
# Copy and modify for your use case.
# =============================================================================

# -----------------------------------------------------------------------------
# DRIVER METADATA (required)
# -----------------------------------------------------------------------------
driver:
  id: "domain.purpose"              # string, required - Unique identifier (e.g., "bank.home-loan")
  version: "1.0.0"                  # string, required - Semantic version
  title: "Human Readable Title"     # string, required - Display name
  description: "Description"        # string, optional - Longer description
  domain: "payment"                 # string, optional - Business domain (payment, lending, vendor, etc.)
  issuer_type: "voucher"            # string, optional - What creates envelopes (voucher, loan, invoice)

# -----------------------------------------------------------------------------
# PAYLOAD CONFIGURATION (required)
# -----------------------------------------------------------------------------
payload:
  schema:
    id: "driver-id.v1"              # string, required - Schema identifier
    format: "json_schema"           # string, default: "json_schema" - Schema format
    uri: null                       # string, optional - Path to external schema file (relative to driver dir)
    inline:                         # object, optional - Inline JSON Schema (use this OR uri, not both)
      type: "object"
      properties:
        field_name:
          type: "string"
          description: "Field description"
      required: ["field_name"]
      additionalProperties: false   # Set to true for freeform payloads
  storage:
    mode: "versioned"               # string, default: "versioned" - How payload changes are stored
    patch_strategy: "merge"         # string, default: "merge" - How patches are applied

# -----------------------------------------------------------------------------
# DOCUMENT REGISTRY (required, can be empty array)
# -----------------------------------------------------------------------------
documents:
  registry:
    - type: "DOCUMENT_TYPE"         # string, required - Unique type code (UPPER_SNAKE_CASE)
      title: "Document Title"       # string, required - Display name
      allowed_mimes:                # array, default: ["application/pdf", "image/jpeg", "image/png"]
        - "application/pdf"
        - "image/jpeg"
        - "image/png"
      max_size_mb: 10               # integer, default: 10 - Max file size in MB
      multiple: false               # boolean, default: false - Allow multiple uploads of this type

# -----------------------------------------------------------------------------
# CHECKLIST TEMPLATE (required, can be empty array)
# -----------------------------------------------------------------------------
checklist:
  template:
    # --- Payload Field Item ---
    - key: "unique_key"             # string, required - Unique identifier
      label: "Human readable label" # string, required - Display text
      kind: "payload_field"         # string, required - One of: payload_field, document, signal, attestation
      payload_pointer: "/path"      # string, required for payload_field - JSON Pointer to field
      doc_type: null                # string, null for payload_field
      signal_key: null              # string, null for payload_field
      attestation_type: null        # string, null for payload_field
      required: true                # boolean, default: true - Must be satisfied for settlement
      review: "none"                # string, default: "none" - One of: none, optional, required

    # --- Document Item ---
    - key: "doc_key"
      label: "Upload document"
      kind: "document"
      doc_type: "DOCUMENT_TYPE"     # string, required for document - Must match documents.registry[].type
      payload_pointer: null
      signal_key: null
      attestation_type: null
      required: true
      review: "required"            # "none" = auto-accept, "optional" = can review, "required" = must review

    # --- Signal Item ---
    - key: "signal_key"
      label: "Approval received"
      kind: "signal"
      signal_key: "approval_signal" # string, required for signal - Must match signals.definitions[].key
      payload_pointer: null
      doc_type: null
      attestation_type: null
      required: true
      review: "none"

# -----------------------------------------------------------------------------
# SIGNAL DEFINITIONS (required, can be empty array)
# -----------------------------------------------------------------------------
signals:
  definitions:
    - key: "signal_name"            # string, required - Unique identifier (lower_snake_case)
      type: "boolean"               # string, default: "boolean" - Signal data type
      source: "host"                # string, default: "host" - Who sets it: "host" (manual) or "system" (auto)
      default: false                # mixed, default: false - Initial value
      required: false               # boolean, default: false - Must be true for settlement
      signal_category: "decision"   # string, default: "decision" - "decision" (human) or "integration" (system)
      system_settable: false        # boolean, default: false - Can system auto-set (only for integration)

# -----------------------------------------------------------------------------
# GATE DEFINITIONS (required)
# -----------------------------------------------------------------------------
gates:
  definitions:
    - key: "gate_name"              # string, required - Unique identifier
      rule: "expression"            # string, required - Boolean expression (see Gate Expression Syntax)

    # The "settleable" gate is REQUIRED - determines when envelope can be settled
    - key: "settleable"
      rule: "payload.valid && checklist.required_accepted && signal._all_satisfied"

# -----------------------------------------------------------------------------
# AUDIT CONFIGURATION (optional)
# -----------------------------------------------------------------------------
audit:
  enabled: true                     # boolean, default: true - Enable audit logging
  capture:                          # array, default: all of these
    - "payload_patch"               # Log payload changes
    - "attachment_upload"           # Log document uploads
    - "attachment_review"           # Log document reviews
    - "attestation_submit"          # Log attestations
    - "signal_set"                  # Log signal changes
    - "gate_change"                 # Log gate transitions
    - "status_change"               # Log status transitions

# -----------------------------------------------------------------------------
# MANIFEST CONFIGURATION (optional)
# -----------------------------------------------------------------------------
manifest:
  enabled: true                     # boolean, default: true - Enable integrity manifest
  includes:
    payload_hash: true              # boolean, default: true - Include payload in manifest
    attachments_hashes: true        # boolean, default: true - Include attachment hashes
    envelope_fingerprint: true      # boolean, default: true - Include envelope fingerprint

# -----------------------------------------------------------------------------
# UI CONFIGURATION (optional)
# -----------------------------------------------------------------------------
ui:
  display:
    title_field: "/borrower/name"   # JSON pointer to use as display title
    subtitle_field: "/amount"       # JSON pointer to use as subtitle
  steps:                            # UI wizard steps (for custom UIs)
    - "step_one"
    - "step_two"
```

#### Gate Expression Syntax

Gates use a simple expression language evaluated by `GateEvaluator`:

**Operators:**
- `&&` - Logical AND
- `||` - Logical OR
- `==` - Equality
- `!=` - Inequality
- `!` - Negation

**Context Variables:**

```yaml
# Payload context
payload.valid          # boolean - Has non-empty payload
payload.version        # integer - Current payload version

# Checklist context
checklist.total             # integer - Total checklist items
checklist.required_count    # integer - Required items count
checklist.required_present  # boolean - All required items have status ≠ missing
checklist.required_accepted # boolean - All required items have status = accepted
checklist.all_accepted      # boolean - ALL items accepted (including optional)
checklist.has_rejected      # boolean - Any items rejected
checklist.pending_count     # integer - Items still pending

# Signal context
signal.{key}           # boolean - Value of specific signal
signal._blocking       # array - Keys of unsatisfied required signals
signal._all_satisfied  # boolean - All required signals are true

# Gate context (for chaining)
gate.{key}             # boolean - Result of previous gate

# Envelope context
envelope.status        # string - Current status value
envelope.payload_version # integer - Payload version
```

**Example Expressions:**

```yaml
# Simple payload check
rule: "payload != null"
rule: "payload.valid == true"

# Checklist-based
rule: "checklist.required_accepted == true"
rule: "checklist.required_present && !checklist.has_rejected"

# Signal-based
rule: "signal.kyc_passed && signal.account_created"
rule: "signal._all_satisfied"

# Compound with gate chaining
rule: "gate.payload_valid && gate.evidence_ready && signal.approved"

# Complex multi-condition
rule: "gate.evidence_ready && signal.kyc_passed && signal.underwriting_approved"
```

---

## 2. Driver Dissection - Real Examples

### 2.1 `payable.default` - Auto-Settle Pattern

**Use Case:** Simple payment vouchers where you just need to track payment metadata.

**File:** `storage/app/envelope-drivers/payable.default/v1.0.0.yaml`

```yaml
driver:
  id: "payable.default"
  version: "1.0.0"
  title: "Default Payable/Settlement Driver"
  description: "Auto-created driver for payable and settlement vouchers"
  domain: "payment"
  issuer_type: "voucher"

payload:
  schema:
    id: "payable.default.v1"
    format: "json_schema"
    inline:
      type: "object"
      additionalProperties: true    # KEY: Accept ANY payload structure
      description: "Freeform JSON payload"
  storage:
    mode: "versioned"
    patch_strategy: "merge"

documents:
  registry:
    - type: "REFERENCE_DOC"
      title: "Reference Document"
      allowed_mimes: ["application/pdf", "image/jpeg", "image/png"]
      max_size_mb: 5
      multiple: true                 # Allow multiple invoices/receipts

checklist:
  template:
    - key: "payload_present"
      label: "Payment details provided"
      kind: "payload_field"
      payload_pointer: "/"           # Root payload - just needs to exist
      required: true
      review: "none"
    - key: "reference_documents"
      label: "Reference documents uploaded"
      kind: "document"
      doc_type: "REFERENCE_DOC"
      required: false                # Documents are OPTIONAL
      review: "none"                 # Auto-accept - no human review

signals:
  definitions: []                    # NO signals - auto-settle

gates:
  definitions:
    - key: "has_payload"
      rule: "payload != null"
    - key: "settleable"
      rule: "payload != null"        # Settle as soon as payload exists

audit:
  enabled: true
  capture: ["payload_patch", "attachment_upload", "attachment_review", "signal_set", "gate_change"]

manifest:
  enabled: false                     # No integrity manifest needed
```

**Key Design Decisions:**

| Decision | Rationale |
|----------|-----------|
| `additionalProperties: true` | External metadata varies per use case - don't constrain |
| `required: false` for documents | Invoices are just references, not evidence |
| `review: "none"` | No human review needed - auto-accept uploads |
| No signals | Payable vouchers don't need manual approval |
| `settleable: payload != null` | Auto-settle when any payload exists |

**When to Use:** Bills, invoices, simple vendor payments, any case where you just track metadata.

---

### 2.2 `vendor.pay-by-face` - Multi-Evidence Pattern

**Use Case:** Face verification for vendor payments - requires photo evidence and human verification.

**File:** `storage/app/envelope-drivers/vendor.pay-by-face/v1.0.0.yaml`

```yaml
driver:
  id: "vendor.pay-by-face"
  version: "1.0.0"
  title: "Vendor Pay-by-Face Integration Testing"
  description: "Evidence envelope for vendor face payment verification"
  domain: "vendor_payment"
  issuer_type: "vendor"

payload:
  schema:
    id: "vendor.pay-by-face.v1.0.0"
    format: "json_schema"
    inline:
      type: "object"
      properties:
        reference_id:
          type: "string"
          description: "Vendor's transaction reference"
        amount:
          type: "number"
          minimum: 0
        callback_url:
          type: "string"
          format: "uri"
        # ... more fields
      required:
        - reference_id
        - amount
        - callback_url

documents:
  registry:
    - type: "FACE_PHOTO"
      title: "Face Photo for Verification"
      allowed_mimes: ["image/jpeg", "image/png"]
      max_size_mb: 5
      multiple: false
    - type: "ID_FRONT"
      title: "ID Card (Front)"
      allowed_mimes: ["image/jpeg", "image/png", "application/pdf"]
      max_size_mb: 5
      multiple: false
    - type: "ID_BACK"
      title: "ID Card (Back)"
      allowed_mimes: ["image/jpeg", "image/png", "application/pdf"]
      max_size_mb: 5
      multiple: false

checklist:
  template:
    # Payload fields
    - key: "reference_id_provided"
      label: "Reference ID provided"
      kind: "payload_field"
      payload_pointer: "/reference_id"
      required: true
      review: "none"
    - key: "amount_provided"
      label: "Amount specified"
      kind: "payload_field"
      payload_pointer: "/amount"
      required: true
      review: "none"
    - key: "callback_url_provided"
      label: "Callback URL specified"
      kind: "payload_field"
      payload_pointer: "/callback_url"
      required: true
      review: "none"
    # Documents
    - key: "face_photo"
      label: "Face photo captured"
      kind: "document"
      doc_type: "FACE_PHOTO"
      required: true
      review: "none"                 # Auto-accept photo
    - key: "id_front"
      label: "ID card front (optional)"
      kind: "document"
      doc_type: "ID_FRONT"
      required: false
      review: "optional"             # Can review but not required
    - key: "id_back"
      label: "ID card back (optional)"
      kind: "document"
      doc_type: "ID_BACK"
      required: false
      review: "optional"
    # Signal
    - key: "face_verified"
      label: "Face verification passed"
      kind: "signal"
      signal_key: "face_verified"
      required: true                 # MUST be verified
      review: "none"

signals:
  definitions:
    - key: "face_verified"
      type: "boolean"
      source: "host"                 # Human sets this
      default: false
      required: false                # Signal definition required != checklist required
      signal_category: "decision"    # Human decision
      system_settable: false
    - key: "callback_sent"
      type: "boolean"
      source: "host"
      default: false
      required: false
      signal_category: "decision"
      system_settable: false

gates:
  definitions:
    - key: "payload_valid"
      rule: "payload.valid == true"
    - key: "face_captured"
      rule: "checklist.face_photo == true"  # Note: checklist item key
    - key: "evidence_ready"
      rule: "gate.payload_valid && gate.face_captured"
    - key: "settleable"
      rule: "gate.evidence_ready && signal.face_verified"
```

**Key Design Decisions:**

| Decision | Rationale |
|----------|-----------|
| Strict schema with `required` | Payment data must be validated |
| `FACE_PHOTO` required | Evidence for face verification |
| `ID_FRONT/BACK` optional | Not all payments need ID |
| `face_verified` signal | Human must confirm face matches |
| Gate chaining | Debug intermediate states |

**When to Use:** Any payment requiring identity verification, high-value transactions.

---

### 2.3 `bank.home-loan-takeout` - Complex Approval Pattern

**Use Case:** Bank-grade home loan settlement with multiple external approvals.

```yaml
driver:
  id: "bank.home-loan-takeout"
  version: "1.0.0"
  title: "Bank Home Loan Take-Out"
  description: "Evidence envelope for developer-originated home-loan take-out"
  domain: "housing_finance"
  issuer_type: "developer"

payload:
  schema:
    id: "bank.home_loan_takeout.v1"
    format: "json_schema"
    uri: "schemas/takeout.v1.schema.json"  # External schema file

documents:
  registry:
    - type: "BORROWER_ID_FRONT"
      title: "Borrower ID (Front)"
      max_size_mb: 10
    - type: "BORROWER_ID_BACK"
      title: "Borrower ID (Back)"
      max_size_mb: 10
    - type: "PROOF_OF_INCOME"
      title: "Proof of Income / Employment"
      max_size_mb: 15
      multiple: true
    - type: "TITLE_TCT"
      title: "Property Title (TCT/CCT)"
      max_size_mb: 20

checklist:
  template:
    # Payload
    - key: "borrower_name"
      label: "Borrower name captured"
      kind: "payload_field"
      payload_pointer: "/borrower/full_name"
      required: true
    - key: "tcp_declared"
      label: "Total Contract Price declared"
      kind: "payload_field"
      payload_pointer: "/loan/tcp"
      required: true
    # Documents with REQUIRED review
    - key: "borrower_id_front"
      label: "Borrower ID (Front) uploaded"
      kind: "document"
      doc_type: "BORROWER_ID_FRONT"
      required: true
      review: "required"             # MUST be reviewed
    - key: "title_tct"
      label: "Property title uploaded"
      kind: "document"
      doc_type: "TITLE_TCT"
      required: true
      review: "required"
    # Multiple approval signals
    - key: "kyc_passed_signal"
      label: "Bank-grade KYC passed"
      kind: "signal"
      signal_key: "kyc_passed"
      required: true
    - key: "account_created_signal"
      label: "Borrower bank account created"
      kind: "signal"
      signal_key: "account_created"
      required: true
    - key: "underwriting_approved_signal"
      label: "Underwriting approved"
      kind: "signal"
      signal_key: "underwriting_approved"
      required: true

signals:
  definitions:
    - key: "kyc_passed"
      type: "boolean"
      source: "host"
      default: false
    - key: "account_created"
      type: "boolean"
      source: "host"
      default: false
    - key: "underwriting_approved"
      type: "boolean"
      source: "host"
      default: false

gates:
  definitions:
    - key: "payload_valid"
      rule: "payload.valid == true"
    - key: "checklist_complete"
      rule: "checklist.required_accepted == true"
    - key: "evidence_ready"
      rule: "gate.payload_valid && gate.checklist_complete"
    - key: "account_ready"
      rule: "signal.kyc_passed && signal.account_created"
    - key: "settleable"
      rule: "gate.evidence_ready && gate.account_ready && signal.underwriting_approved"
```

**Key Design Decisions:**

| Decision | Rationale |
|----------|-----------|
| External schema file | Complex schema maintained separately |
| `review: "required"` | Documents MUST be reviewed by human |
| 3 separate signals | Different approvals from different systems/people |
| Layered gates | Clear debugging path for what's blocking |

---

### 2.4 `simple.envelope` vs `simple.test`

Both are minimal drivers for testing:

**`simple.envelope`** - For host app testing:
- Includes a `TEST_DOC` document type
- Has an `approved` signal
- Good for UI testing in the host app

**`simple.test`** - For package unit tests:
- Minimal configuration
- Used in `packages/settlement-envelope/tests/`
- Documents and signals both required

**When to use:** Testing envelope functionality without business complexity.

---

## 3. Design Decision Framework

### 3.1 Checklist Item Kinds

| Kind | Use When | Key Properties |
|------|----------|----------------|
| `payload_field` | Data must be provided in JSON | `payload_pointer` (JSON Pointer syntax: `/path/to/field`) |
| `document` | File must be uploaded | `doc_type` (must match registry), `review` mode |
| `signal` | Manual/system approval needed | `signal_key` (must match definitions) |
| `attestation` | User must declare something | `attestation_type` (future feature) |

**JSON Pointer Syntax:**
```yaml
payload_pointer: "/"              # Root object exists
payload_pointer: "/amount"        # $.amount exists
payload_pointer: "/borrower/name" # $.borrower.name exists
```

### 3.2 Signal Design

**Required vs Optional:**
- `required: true` in checklist = MUST be true for settlement
- `required: true` in signal definition = tracked but doesn't block alone

**Source Types:**
- `source: "host"` - Set manually by voucher owner via UI
- `source: "system"` - Set by webhooks/integrations (future)

**Categories:**
- `signal_category: "decision"` - Human judgment (face_verified, approved)
- `signal_category: "integration"` - System integration (kyc_passed from API)

**Design Questions:**
1. Does a human need to approve something? → Add signal with `decision` category
2. Does an external system need to confirm? → Add signal with `integration` category
3. Is this blocking settlement? → Set `required: true` in checklist item

### 3.3 Gate Expression Patterns

**Pattern 1: Simple Existence**
```yaml
rule: "payload != null"
```
Use for: Auto-settle when any data provided

**Pattern 2: Checklist-Based**
```yaml
rule: "checklist.required_accepted == true"
```
Use for: All required items must be accepted

**Pattern 3: Signal-Based**
```yaml
rule: "signal.approved"
rule: "signal.kyc_passed && signal.account_created"
```
Use for: Manual approvals required

**Pattern 4: Gate Chaining**
```yaml
gates:
  - key: "payload_valid"
    rule: "payload.valid == true"
  - key: "evidence_ready"
    rule: "gate.payload_valid && checklist.required_accepted"
  - key: "settleable"
    rule: "gate.evidence_ready && signal.approved"
```
Use for: Complex workflows - intermediate gates help debugging

### 3.4 Review Mode Strategy

| Mode | Behavior | Use When |
|------|----------|----------|
| `none` | Auto-accept on upload | Invoices, references, self-captured photos |
| `optional` | Can review, not required | Supporting documents, optional evidence |
| `required` | MUST be accepted before settlement | Legal documents, ID verification |

---

## 4. Creating a New Driver

### 4.1 Step-by-Step Process

#### Step 1: Define Use Case

Answer these questions:
- What is being settled? (payment, loan, contract)
- What evidence is required?
- Who needs to approve?
- What external systems are involved?

#### Step 2: Design Payload Schema

```yaml
payload:
  schema:
    inline:
      type: "object"
      properties:
        # List all required data fields
      required: [...]
      additionalProperties: false  # or true for flexible payloads
```

#### Step 3: List Document Types

For each required/optional document:
```yaml
documents:
  registry:
    - type: "TYPE_CODE"
      title: "Human Title"
      allowed_mimes: [...]
      max_size_mb: N
      multiple: true/false
```

#### Step 4: Define Signals

For each manual approval:
```yaml
signals:
  definitions:
    - key: "approval_name"
      type: "boolean"
      source: "host"
      default: false
```

#### Step 5: Build Checklist

Map requirements to checklist items:
```yaml
checklist:
  template:
    - key: "unique_key"
      label: "Description"
      kind: "payload_field|document|signal"
      # ... kind-specific properties
      required: true/false
      review: "none|optional|required"
```

#### Step 6: Write Gate Expressions

Build up to `settleable`:
```yaml
gates:
  definitions:
    - key: "intermediate_gate"
      rule: "..."
    - key: "settleable"
      rule: "gate.intermediate_gate && signal.approval"
```

#### Step 7: Test with Command

```bash
php artisan test:envelope --driver=my.driver --scenario=full --upload-doc --auto-settle
```

### 4.2 Validation Checklist

Before deploying a new driver, verify:

- [ ] Driver ID follows `domain.purpose` naming
- [ ] Version is valid semver (`1.0.0`)
- [ ] All document types in checklist exist in registry
- [ ] All signal keys in checklist exist in definitions
- [ ] Payload schema covers all `payload_pointer` paths
- [ ] `settleable` gate is defined
- [ ] `settleable` gate is reachable (test it!)
- [ ] Review modes match business requirements
- [ ] Audit capture includes needed events

---

## 5. AI-Assisted Driver Scaffolding

### 5.1 Effective Prompts for AI Agents

When asking an AI (Claude, GPT, etc.) to generate a driver, provide:

1. **Business context** - What is being settled and why
2. **Required data** - What payload fields are needed
3. **Required documents** - What files must be uploaded
4. **Approval workflow** - Who needs to approve what
5. **Settlement criteria** - When can settlement proceed

### 5.2 Template Prompt: Basic Driver

```
I need a settlement envelope driver for [BUSINESS CONTEXT].

**Payload Requirements:**
- [field1]: [type] - [description]
- [field2]: [type] - [description]
- Required fields: [list]

**Document Requirements:**
- [DOC_TYPE]: [description], review: [none/optional/required]

**Approval Requirements:**
- [signal_name]: [who sets it and when]

**Settlement Criteria:**
The envelope should be settleable when [CONDITIONS].

Please generate a complete YAML driver following the Settlement Envelope Driver Schema.
Include the driver metadata, payload schema, documents registry, checklist template,
signals definitions, and gates with a reachable "settleable" gate.
```

### 5.3 Template Prompt: Copy Existing Pattern

```
I need a settlement envelope driver similar to [payable.default / vendor.pay-by-face / bank.home-loan-takeout] but with these modifications:

**Changes from base driver:**
1. [Change 1]
2. [Change 2]

**Additional requirements:**
- [Requirement]

Please generate the complete YAML driver.
```

### 5.4 Example Prompts

**Example 1: Vendor Invoice Payment**
```
I need a settlement envelope driver for vendor invoice payments.

**Payload Requirements:**
- vendor_name: string - Name of the vendor
- invoice_number: string - Invoice reference number
- amount: number - Invoice amount (required)
- due_date: string (date) - Payment due date
- Required fields: vendor_name, invoice_number, amount

**Document Requirements:**
- INVOICE: The vendor's invoice, review: none (auto-accept)
- RECEIPT: Payment receipt (optional), review: none

**Approval Requirements:**
- amount_confirmed: Finance team confirms the amount matches

**Settlement Criteria:**
Settleable when invoice is uploaded AND amount_confirmed signal is true.
```

**Example 2: KYC Verification**
```
I need a settlement envelope driver for KYC identity verification.

**Payload Requirements:**
- full_name: string - Customer's full legal name
- date_of_birth: string (date) - DOB for age verification
- id_type: string - Type of ID (passport, driver_license, national_id)
- id_number: string - ID document number
- Required fields: all of the above

**Document Requirements:**
- ID_FRONT: Front of ID document, review: required
- ID_BACK: Back of ID document (if applicable), review: optional
- SELFIE: Live selfie for face match, review: required

**Approval Requirements:**
- id_verified: Document reviewer confirms ID is valid
- face_matched: Face match between selfie and ID photo confirmed

**Settlement Criteria:**
Settleable when all documents reviewed AND both signals are true.
```

**Example 3: Multi-Level Approval**
```
I need a settlement envelope driver for high-value disbursements requiring multi-level approval.

**Payload Requirements:**
- beneficiary_name: string
- beneficiary_account: string
- amount: number (minimum: 100000)
- purpose: string
- Required fields: all

**Document Requirements:**
- AUTHORIZATION_LETTER: Signed authorization, review: required
- SUPPORTING_DOCS: Any supporting documents, review: optional, multiple: true

**Approval Requirements:**
- manager_approved: Direct manager approval
- finance_approved: Finance department approval
- compliance_approved: Compliance team approval (for amounts > 500000)

**Settlement Criteria:**
- For amounts <= 500000: manager_approved AND finance_approved
- For amounts > 500000: all three approvals required

Note: The gate logic should handle both scenarios.
```

### 5.5 AI Response Validation Checklist

After receiving an AI-generated driver, verify:

- [ ] YAML syntax is valid (no tabs, proper indentation)
- [ ] Driver ID matches requested naming
- [ ] All payload fields from requirements are in schema
- [ ] All required documents are in registry
- [ ] All signals are properly defined
- [ ] Checklist items match documents and signals
- [ ] `settleable` gate exists and logic matches requirements
- [ ] Review modes are appropriate
- [ ] No hardcoded test values

### 5.6 Common AI Mistakes and Fixes

| Mistake | Fix |
|---------|-----|
| Missing `settleable` gate | Add gate with settlement criteria |
| Document type in checklist doesn't match registry | Ensure exact match (case-sensitive) |
| Signal key mismatch between checklist and definitions | Use consistent naming |
| `payload_pointer` wrong syntax | Must start with `/`, use `/path/to/field` |
| `additionalProperties` missing | Add based on flexibility needs |
| Review mode wrong | `none` for auto-accept, `required` for manual review |
| Gate expression syntax error | Use `&&` not `and`, `==` not `=` |

---

## 6. Evolution of payable.default

### 6.1 The Journey

The `payable.default` driver evolved through real-world usage and iteration:

**Initial Requirements:**
- Auto-settle for payable vouchers
- Store external metadata (varying structure)
- Optional document attachments (invoices)
- No manual approval workflow

**Challenge 1: Schema Validation Blocking External Contributors**

*Problem:* External contributors updating payload via contribution links failed schema validation because they provided partial data.

*Solution:* Freeform schema with `additionalProperties: true`:
```yaml
payload:
  schema:
    inline:
      type: "object"
      additionalProperties: true  # Accept any structure
```

**Challenge 2: Documents Optional But Workflow Needed**

*Problem:* Documents (invoices) should be optional, but when uploaded, they shouldn't require human review.

*Solution:* `required: false` with `review: "none"`:
```yaml
checklist:
  template:
    - key: "reference_documents"
      kind: "document"
      doc_type: "REFERENCE_DOC"
      required: false      # Optional
      review: "none"       # Auto-accept when uploaded
```

**Challenge 3: Auto-Advance Needed**

*Problem:* Payable vouchers should auto-settle as soon as payload exists, without manual steps.

*Solution:* Simple gate rule:
```yaml
gates:
  definitions:
    - key: "settleable"
      rule: "payload != null"  # Settle immediately when data exists
```

### 6.2 Key Design Decisions

| Decision | Why |
|----------|-----|
| No signals | Payable vouchers are pre-approved - no manual step needed |
| Freeform payload | External metadata structure varies per voucher |
| Optional documents | Invoices are references, not required evidence |
| Auto-accept review | No human verification needed for references |
| Simple settleable gate | Minimize friction - settle when data present |
| Manifest disabled | No integrity proof needed for simple payments |

### 6.3 Lessons Learned

1. **Start permissive, add constraints later** - Freeform payload avoids validation issues
2. **Match review mode to business need** - `none` for references, `required` for evidence
3. **Empty signals array is valid** - Not every workflow needs manual approval
4. **Test with external contributors** - They have different access patterns
5. **Auto-advance is powerful** - Let the state machine do the work

---

## 7. Testing Drivers

### 7.1 Using test:envelope Command

```bash
# Basic test - create envelope and stop at draft
php artisan test:envelope --driver=my.driver --scenario=draft

# Evidence collection - upload documents
php artisan test:envelope --driver=my.driver --scenario=evidence --upload-doc

# Full flow with auto-settlement
php artisan test:envelope --driver=my.driver --scenario=full --upload-doc --auto-review --auto-settle

# Use existing voucher
php artisan test:envelope --voucher=ABC123 --scenario=evidence

# Verbose output
php artisan test:envelope --driver=my.driver --scenario=full --detailed
```

### 7.2 Scenario Testing

| Scenario | What It Tests |
|----------|--------------|
| `draft` | Envelope creation, initial state |
| `evidence` | Payload update, document upload |
| `signals` | Signal toggling |
| `lock` | Evidence + signals + lock attempt |
| `settle` | Full flow including settlement |
| `full` | Everything with verbose output |

### 7.3 Test Checklist

For each new driver, verify:

- [ ] Envelope creates successfully
- [ ] Payload updates work
- [ ] Documents upload to correct types
- [ ] Checklist items update status correctly
- [ ] Signals can be toggled
- [ ] Gates compute correctly
- [ ] State machine advances properly
- [ ] `settleable` becomes true when expected
- [ ] Lock and settle work when gates pass
- [ ] Audit log captures all actions

### 7.4 Edge Cases to Test

1. **Missing required payload fields** - Should block settlement
2. **Wrong document type** - Should reject upload
3. **Unsatisfied signals** - Should block settlement
4. **Upload limit exceeded** - Should enforce `multiple: false`
5. **Review rejection** - Should block if `review: required`

---

## 8. Best Practices

### 8.1 Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Driver ID | `{domain}.{purpose}` | `bank.home-loan-takeout` |
| Version | Semantic versioning | `1.0.0`, `2.1.0` |
| Document types | `UPPER_SNAKE_CASE` | `BORROWER_ID_FRONT` |
| Signal keys | `lower_snake_case` | `kyc_passed` |
| Checklist keys | `lower_snake_case` | `borrower_name_provided` |
| Gate keys | `lower_snake_case` | `evidence_ready` |

### 8.2 Schema Design

**Inline vs External:**
- **Inline** - Simple schemas, few fields, quick iteration
- **External** - Complex schemas, shared across drivers, strict validation

**Required Fields:**
```yaml
# Always specify required array
required: ["field1", "field2"]

# For flexible payloads
additionalProperties: true

# For strict payloads
additionalProperties: false
```

### 8.3 Gate Design

**Always Have:**
- A `settleable` gate (required for settlement)
- Intermediate gates for debugging

**Keep Simple:**
```yaml
# Good - readable
rule: "gate.evidence_ready && signal.approved"

# Avoid - too complex
rule: "payload.valid && checklist.required_accepted && signal.a && signal.b && !checklist.has_rejected"
```

**Use Gate Chaining:**
```yaml
gates:
  - key: "payload_valid"
    rule: "payload.valid"
  - key: "docs_ready"
    rule: "checklist.required_present"
  - key: "evidence_ready"
    rule: "gate.payload_valid && gate.docs_ready"
  - key: "settleable"
    rule: "gate.evidence_ready && signal.approved"
```

### 8.4 Backward Compatibility

When updating drivers:

- **Never remove** required payload fields
- **Never change** document type codes
- **Never rename** signal keys
- **Add** new optional fields
- **Create new version** for breaking changes

**Version Upgrade Path:**
```
v1.0.0 → v1.1.0  (add optional field)
v1.1.0 → v2.0.0  (breaking change - new required field)
```

### 8.5 Documentation

Include in your driver:
- Clear `title` and `description`
- Meaningful checklist `label` values
- Comments for complex gate rules (in external docs)

---

## Related Documentation

- [Settlement Envelope Architecture](../../architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md) - Technical reference
- [Settlement Envelope User Manual](../features/SETTLEMENT_ENVELOPE_USER_MANUAL.md) - End-user guide
- [DriverData.php](../../../packages/settlement-envelope/src/Data/DriverData.php) - Schema source code
