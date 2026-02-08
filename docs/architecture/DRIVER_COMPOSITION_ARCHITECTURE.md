# Driver Composition Architecture

This document provides a deep dive into the settlement envelope driver composition system, including the CSV specification format, CSV-to-YAML transformation, and detailed analysis of the Bank Home Loan and Pag-IBIG driver families.

## Table of Contents
1. [Composition Chain Overview](#1-composition-chain-overview)
2. [CSV Specification Format](#2-csv-specification-format)
3. [CSV-to-YAML Mapping](#3-csv-to-yaml-mapping)
4. [Bank Home Loan Family Dissection](#4-bank-home-loan-family-dissection)
5. [Pag-IBIG Family Dissection](#5-pag-ibig-family-dissection)
6. [Runtime Composition](#6-runtime-composition)
7. [UI Visualization](#7-ui-visualization)

---

## 1. Composition Chain Overview

### Why Composition?

Traditional monolithic drivers require duplicating configuration across similar use cases. For example, a married borrower's home loan application shares 90% of requirements with a single borrower's application.

**Composition solves this by:**
- Defining a **base driver** with common requirements
- Creating **overlay drivers** that add or override specific items
- Combining overlays at runtime via `extends`

### The `extends` Directive

```yaml
extends:
  - "bank.home-loan.base@1.0.0"
  - "bank.home-loan.income.ofw@1.0.0"

driver:
  id: "my-custom-driver"
  # ... overlay-specific config
```

**Syntax:** `"driver-id@version"` or just `"driver-id"` (uses latest)

**Multiple extends:** Parents are processed left-to-right, overlay applied last.

### Merge Rules

| Section | Merge Strategy | Key Field |
|---------|---------------|-----------|
| `driver` | Overlay wins (shallow merge) | - |
| `payload` | Overlay wins | - |
| `documents.registry` | Union by key | `type` |
| `checklist.template` | Union by key | `key` |
| `signals.definitions` | Union by key | `key` |
| `gates.definitions` | Union by key (overlay overrides) | `key` |
| `audit`, `manifest`, `ui` | Overlay wins | - |

**Union-by-key:** Items with the same key are replaced; new items are added.

### Circular Dependency Detection

The system tracks resolved driver IDs and throws `CircularDependencyException` if a driver attempts to extend itself (directly or transitively).

```
A → B → C → A  ❌ CircularDependencyException
```

---

## 2. CSV Specification Format

The canonical driver specifications live in `docs/reference/driver-specs/`:

### home-loan-documents-signals.csv

Defines documents, signals, and payload fields for each driver.

**Row Types:**
- `DOC` - Document definition
- `SIG` - Signal definition  
- `PAYLOAD` - Payload field (checklist item)

**Key Columns:**

| Column | Description | Example |
|--------|-------------|---------|
| `row_type` | DOC, SIG, or PAYLOAD | `DOC` |
| `driver_id` | Target driver | `bank.home-loan.eligible.single` |
| `variant_family` | Family grouping | `bank_home_loan` |
| `category` | Logical category | `borrower`, `income`, `property` |
| `sub_category` | Sub-grouping | `identity`, `employed`, `title` |
| `item_key` | Unique key | `borrower_id_primary` |
| `item_kind` | document, signal, payload_field | `document` |
| `doc_type` | Document type code | `BORROWER_ID_PRIMARY` |
| `doc_title` | Human-readable title | `Borrower Government ID` |
| `doc_required` | true/false | `true` |
| `review_mode` | none, optional, required | `required` |
| `multiple` | Allow multiple uploads | `false` |
| `signal_key` | Signal identifier | `borrower_kyc_passed` |
| `signal_required` | true/false | `true` |
| `signal_source` | host or system | `host` |
| `payload_pointer` | JSON Pointer path | `/borrower/full_name` |

### home-loan-gates.csv

Defines gate expressions for each driver.

**Key Columns:**

| Column | Description | Example |
|--------|-------------|---------|
| `driver_id` | Target driver | `bank.home-loan.eligible.single` |
| `gate_key` | Gate identifier | `settleable` |
| `gate_category` | payload, checklist, signals, composite | `composite` |
| `gate_purpose` | Human description | `Loan may proceed to settlement` |
| `expression` | Gate rule | `gate.evidence_ready && gate.approvals_ready` |
| `blocking_if_false` | yes/no | `yes` |

---

## 3. CSV-to-YAML Mapping

### Document Row (DOC) → Two YAML Sections

**CSV Row:**
```csv
DOC,bank.home-loan.eligible.single,bank_home_loan,borrower,identity,borrower_id_primary,document,BORROWER_ID_PRIMARY,"Borrower Government ID",true,required,false,10,,,,,,
```

**→ documents.registry:**
```yaml
documents:
  registry:
    - type: "BORROWER_ID_PRIMARY"
      title: "Borrower Government ID"
      allowed_mimes: ["image/jpeg", "image/png", "application/pdf"]
      max_size_mb: 10
      multiple: false
```

**→ checklist.template:**
```yaml
checklist:
  template:
    - key: "borrower_id_primary"
      label: "Borrower Government ID uploaded"
      kind: "document"
      doc_type: "BORROWER_ID_PRIMARY"
      required: true
      review: "required"
```

### Signal Row (SIG) → Two YAML Sections

**CSV Row:**
```csv
SIG,bank.home-loan.eligible.single,bank_home_loan,approvals,kyc,borrower_kyc_passed,signal,,,,,,,borrower_kyc_passed,true,host,,,
```

**→ signals.definitions:**
```yaml
signals:
  definitions:
    - key: "borrower_kyc_passed"
      type: "boolean"
      source: "host"
      default: false
      signal_category: "decision"
```

**→ checklist.template:**
```yaml
checklist:
  template:
    - key: "borrower_kyc_passed_signal"
      label: "Borrower KYC passed"
      kind: "signal"
      signal_key: "borrower_kyc_passed"
      required: true
      review: "none"
```

### Payload Row (PAYLOAD) → Checklist Only

**CSV Row:**
```csv
PAYLOAD,bank.home-loan.eligible.single,bank_home_loan,payload,borrower,borrower_full_name,payload_field,,,,,,,,,,/borrower/full_name,true,
```

**→ checklist.template:**
```yaml
checklist:
  template:
    - key: "borrower_full_name"
      label: "Borrower name captured"
      kind: "payload_field"
      payload_pointer: "/borrower/full_name"
      required: true
      review: "none"
```

### Gate Row → gates.definitions

**CSV Row:**
```csv
bank.home-loan.eligible.single,bank_home_loan,settleable,composite,Loan may proceed,gate.evidence_ready && gate.approvals_ready && !checklist.has_rejected,yes,
```

**→ gates.definitions:**
```yaml
gates:
  definitions:
    - key: "settleable"
      rule: "gate.evidence_ready && gate.approvals_ready && !checklist.has_rejected"
```

### Transformation Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    CSV SPECIFICATION                             │
├─────────────────────────────────────────────────────────────────┤
│  home-loan-documents-signals.csv                                │
│  ┌─────┬─────────────┬──────────┬─────────────────────────────┐ │
│  │ DOC │ driver_id   │ doc_type │ doc_title, required, review │ │
│  │ SIG │ driver_id   │          │ signal_key, source          │ │
│  │ PAY │ driver_id   │          │ payload_pointer             │ │
│  └─────┴─────────────┴──────────┴─────────────────────────────┘ │
│                                                                  │
│  home-loan-gates.csv                                            │
│  ┌─────────────┬──────────┬────────────────────────────────────┐│
│  │ driver_id   │ gate_key │ expression                         ││
│  └─────────────┴──────────┴────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    YAML DRIVER FILE                              │
├─────────────────────────────────────────────────────────────────┤
│  driver:                                                         │
│    id: "bank.home-loan.base"                                    │
│    version: "1.0.0"                                             │
│                                                                  │
│  documents:                                                      │
│    registry:          ◄─── DOC rows (doc_type, title, etc.)     │
│      - type: "..."                                              │
│                                                                  │
│  checklist:                                                      │
│    template:          ◄─── DOC + SIG + PAYLOAD rows             │
│      - key: "..."                                               │
│        kind: "document|signal|payload_field"                    │
│                                                                  │
│  signals:                                                        │
│    definitions:       ◄─── SIG rows                             │
│      - key: "..."                                               │
│                                                                  │
│  gates:                                                          │
│    definitions:       ◄─── gates.csv rows                       │
│      - key: "..."                                               │
│        rule: "..."                                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Bank Home Loan Family Dissection

### Family Tree

```
bank.home-loan.base (Base)
├── bank.home-loan.eligible.married      (+2 docs, +1 signal)
├── bank.home-loan.eligible.widower      (+2 docs)
├── bank.home-loan.eligible.separated    (+1 doc, +1 signal, +1 gate)
├── bank.home-loan.eligible.with-co-borrower (+2 docs, +2 signals, +1 gate)
├── bank.home-loan.income.self-employed  (4 docs, 1 signal, +1 gate)
├── bank.home-loan.income.ofw            (3 docs, 1 signal, +1 gate)
├── bank.home-loan.property.rfo          (1 doc)
└── bank.home-loan.property.non-rfo      (2 docs, 1 signal, +1 gate)
```

### bank.home-loan.base

The foundation for all bank home loan take-outs. Assumes: single, employed, standard property.

**Documents (12):**

| Category | Document | Required |
|----------|----------|----------|
| Borrower Identity | Government ID (Primary) | ✓ |
| Civil Status | CENOMAR / Single Status | ○ |
| Contact | Proof of Residence | ✓ |
| Income | Certificate of Employment | ✓ |
| Income | Payslips (3 months) | ✓ |
| Income | BIR 2316 / ITR | ✓ |
| Property | Contract to Sell | ✓ |
| Property | Title Document (TCT/CCT) | ✓ |
| Property | Tax Declaration | ✓ |
| Property | Appraisal Report | ○ |
| Developer | License to Sell | ○ |
| Developer | Occupancy Permit | ○ |

**Signals (4):**
- `borrower_kyc_passed` - KYC reviewer decision
- `credit_approved` - Credit/underwriting decision
- `legal_cleared` - Legal/title review cleared
- `takeout_authorized` - Final authority to proceed

**Payload Fields (5):**
- `/borrower/full_name`
- `/loan/tcp` (Total Contract Price)
- `/loan/amount_requested`
- `/property/code`
- `/developer/code`

**Gates (6):**
```yaml
payload_valid       → payload.valid == true
required_present    → checklist.required_present == true
required_accepted   → checklist.required_accepted == true
approvals_ready     → signal._all_satisfied == true
evidence_ready      → gate.payload_valid && gate.required_accepted
settleable          → gate.evidence_ready && gate.approvals_ready && !checklist.has_rejected
```

### Eligibility Overlays

#### bank.home-loan.eligible.married

Adds spouse documentation requirements.

```yaml
extends:
  - "bank.home-loan.base@1.0.0"
```

**Additional Documents:**
- `MARRIAGE_CERT` - Marriage Certificate (required)
- `SPOUSE_ID` - Spouse Government ID (required)

**Additional Signal:**
- `spouse_consent_captured` - Spouse consent for encumbrance

**Use Case:** Married borrower where spouse must consent to loan/property encumbrance.

#### bank.home-loan.eligible.widower

Handles deceased spouse documentation.

**Additional Documents:**
- `DEATH_CERT` - Death Certificate (Spouse) (required)
- `MARRIAGE_CERT` - Marriage Certificate (optional, for linkage)

**Use Case:** Widowed borrower proving prior marital status.

#### bank.home-loan.eligible.separated

High-legal-risk variant for annulled/legally separated borrowers.

**Additional Documents:**
- `COURT_DECREE` - Court Decree (Annulment/Legal Separation) (required)

**Additional Signal:**
- `property_regime_cleared` - Legal confirms property regime is clear

**Additional Gate:**
- `high_risk_clearance` → `signal.property_regime_cleared == true`
- Modified `settleable` → adds `&& gate.high_risk_clearance`

**Use Case:** Complex property ownership situations requiring legal clearance.

### Income Overlays

#### bank.home-loan.income.self-employed

Replaces employed income docs with self-employed requirements.

**Documents (4):**
- `BUSINESS_REGISTRATION` - DTI/SEC Registration
- `BUSINESS_PERMIT` - Mayor's/Business Permit
- `FINANCIAL_STATEMENTS` - Financial Statements (multiple)
- `ITR` - Income Tax Return (multiple)

**Signal:**
- `income_validated` - Income verification complete

**Additional Gate:**
- `income_validated` → `signal.income_validated == true`

#### bank.home-loan.income.ofw

For overseas Filipino workers.

**Documents (3):**
- `POEA_CONTRACT` - POEA Contract / Employment Contract
- `OFW_PAYSLIPS` - Overseas Payslips/Remittance Proof (multiple)
- `SPA` - Special Power of Attorney

**Signal:**
- `ofw_income_validated` - OFW income verification

**Additional Gate:**
- `ofw_income_validated` → `signal.ofw_income_validated == true`

### Property Overlays

#### bank.home-loan.property.rfo

Ready-for-Occupancy properties.

**Documents:**
- `OCCUPANCY_PERMIT` - Required (upgraded from optional in base)

#### bank.home-loan.property.non-rfo

Pre-selling properties.

**Documents:**
- `LICENSE_TO_SELL` - Required (upgraded from optional)
- `DEVELOPMENT_PERMIT` - Development Permit (required)

**Signal:**
- `developer_accredited` - Bank confirms developer accreditation

**Additional Gate:**
- `developer_ok` → `signal.developer_accredited == true`

### Composition Example

A married OFW buying an RFO property:

```yaml
extends:
  - "bank.home-loan.base@1.0.0"
  - "bank.home-loan.eligible.married@1.0.0"
  - "bank.home-loan.income.ofw@1.0.0"
  - "bank.home-loan.property.rfo@1.0.0"

driver:
  id: "bank.home-loan.married-ofw-rfo"
  title: "Married OFW - RFO Property"
```

**Composed Result:**
- Base docs (12) + married docs (2) + ofw docs (3) + rfo docs (1) = **18 documents**
- Base signals (4) + spouse_consent (1) + ofw_income_validated (1) = **6 signals**
- Combined gates with all clearances

---

## 5. Pag-IBIG Family Dissection

### Family Tree

```
pagibig.home-loan.base (Base)
└── pagibig.home-loan.takeout.enhanced (+1 signal, +1 gate, modified settleable)
```

### pagibig.home-loan.base

The Pag-IBIG (HDMF) home loan is **signal-heavy** because the fund has its own multi-step approval workflow that doesn't map to document uploads.

**Documents (1):**
- `PAGIBIG_MID` - Pag-IBIG MID / Membership Proof

**Signals (5):**
- `membership_verified` - Membership eligibility verified
- `pagibig_eligibility_confirmed` - Pag-IBIG loan eligibility confirmed
- `appraisal_completed` - Pag-IBIG appraisal completion
- `credit_approved` - Credit approval step
- `takeout_authorized` - Final authority before settlement

**Why So Signal-Heavy?**

Pag-IBIG has a prescribed workflow where each step is a manual approval in their system:
1. Membership verification (MID lookup)
2. Eligibility check (contribution history, loan limits)
3. Property appraisal (by Pag-IBIG-accredited appraiser)
4. Credit evaluation
5. Final take-out authorization

These steps produce **decisions**, not **documents**, hence signals.

**Gates (9):**
```yaml
payload_valid         → payload.valid == true
required_present      → checklist.required_present == true
required_accepted     → checklist.required_accepted == true
pagibig_membership_ok → signal.membership_verified == true
pagibig_eligibility_ok → signal.pagibig_eligibility_confirmed == true
pagibig_appraisal_done → signal.appraisal_completed == true
approvals_ready       → signal._all_satisfied == true
evidence_ready        → gate.payload_valid && gate.required_accepted
settleable            → gate.evidence_ready && gate.pagibig_membership_ok && 
                        gate.pagibig_eligibility_ok && gate.pagibig_appraisal_done && 
                        signal.takeout_authorized && !checklist.has_rejected
```

### pagibig.home-loan.takeout.enhanced

For high-value or exception cases requiring additional senior review.

```yaml
extends:
  - "pagibig.home-loan.base@1.0.0"
```

**Additional Signal:**
- `senior_approval` - Enhanced/senior review completed

**Additional Gate:**
- `enhanced_review_done` → `signal.senior_approval == true`

**Modified Gate:**
```yaml
settleable: >
  gate.evidence_ready && gate.pagibig_membership_ok && 
  gate.pagibig_eligibility_ok && gate.pagibig_appraisal_done && 
  gate.enhanced_review_done &&  # ← Added
  signal.takeout_authorized && !checklist.has_rejected
```

**Use Case:** Loans exceeding a threshold (e.g., ₱6M) or policy exceptions requiring manager/senior approval.

---

## 6. Runtime Composition

### DriverService::resolveComposition()

Located in `packages/settlement-envelope/src/Services/DriverService.php`.

**Algorithm:**

```
1. Parse extends array from overlay YAML
2. For each parent reference (left-to-right):
   a. Check for circular dependency
   b. Load parent YAML (raw, not parsed)
   c. Recursively resolve parent's extends (if any)
   d. Merge parent into accumulated result
3. Merge overlay on top of accumulated result
4. Return final merged data
```

**Pseudocode:**

```php
function resolveComposition(array $data, array $resolved = []): array
{
    $extends = $data['extends'] ?? [];
    unset($data['extends']);

    if (empty($extends)) {
        return $data;
    }

    $merged = [];

    foreach ($extends as $parentRef) {
        [$parentId, $parentVersion] = parseDriverRef($parentRef);

        // Circular check
        if (in_array($parentId, $resolved)) {
            throw new CircularDependencyException();
        }

        // Load parent
        $parentData = loadRawYaml($parentId, $parentVersion);

        // Recursive resolution
        if (isset($parentData['extends'])) {
            $parentData = resolveComposition($parentData, [...$resolved, $parentId]);
        }

        // Merge
        $merged = mergeDrivers($merged, $parentData);
    }

    // Overlay last
    return mergeDrivers($merged, $data);
}
```

### mergeDrivers()

```php
function mergeDrivers(array $base, array $overlay): array
{
    if (empty($base)) return $overlay;

    $result = $base;

    // Scalar sections: overlay wins
    foreach (['driver', 'payload', 'audit', 'manifest', 'ui'] as $section) {
        if (isset($overlay[$section])) {
            $result[$section] = array_merge($result[$section] ?? [], $overlay[$section]);
        }
    }

    // Registry sections: union by key
    $result['documents']['registry'] = mergeByKey(
        $result['documents']['registry'] ?? [],
        $overlay['documents']['registry'] ?? [],
        'type'
    );

    $result['checklist']['template'] = mergeByKey(
        $result['checklist']['template'] ?? [],
        $overlay['checklist']['template'] ?? [],
        'key'
    );

    $result['signals']['definitions'] = mergeByKey(
        $result['signals']['definitions'] ?? [],
        $overlay['signals']['definitions'] ?? [],
        'key'
    );

    $result['gates']['definitions'] = mergeByKey(
        $result['gates']['definitions'] ?? [],
        $overlay['gates']['definitions'] ?? [],
        'key'
    );

    return $result;
}
```

### mergeByKey()

```php
function mergeByKey(array $base, array $overlay, string $keyField): array
{
    $indexed = [];

    // Index base items
    foreach ($base as $item) {
        $indexed[$item[$keyField]] = $item;
    }

    // Overlay items override or add
    foreach ($overlay as $item) {
        $indexed[$item[$keyField]] = $item;
    }

    return array_values($indexed);
}
```

### Order Matters

```yaml
extends:
  - "A@1.0.0"  # Processed first
  - "B@1.0.0"  # Merged on top of A
  - "C@1.0.0"  # Merged on top of A+B
# Overlay merged last on top of A+B+C
```

If A, B, and C all define a document with type `FOO`, the final definition comes from:
1. C's `FOO` (if defined), else
2. B's `FOO` (if defined), else
3. A's `FOO`

Unless the overlay itself defines `FOO`, which would win.

---

## 7. UI Visualization

### Settings > Envelope Drivers (Index)

The drivers are grouped by family prefix and displayed hierarchically:

```
▼ bank.home-loan (10)
  ┌─────────────────────────────────────────────────┐
  │ [Base] Bank Home Loan Base                      │
  │ bank.home-loan.base@1.0.0                       │
  │ 12 docs · 21 checklist · 4 signals · 6 gates   │
  └─────────────────────────────────────────────────┘
      ┌─────────────────────────────────────────────┐
      │ Bank Home Loan - Married Borrower           │
      │ bank.home-loan.eligible.married@1.0.0       │
      │ extends: bank.home-loan.base@1.0.0          │
      │ 14 docs · 24 checklist · 5 signals · 6 gates│
      └─────────────────────────────────────────────┘
      ... (other overlays indented)

▼ pagibig.home-loan (2)
  ┌─────────────────────────────────────────────────┐
  │ [Base] Pag-IBIG Home Loan Base                  │
  │ pagibig.home-loan.base@1.0.0                    │
  │ 1 doc · 7 checklist · 5 signals · 9 gates      │
  └─────────────────────────────────────────────────┘
      ┌─────────────────────────────────────────────┐
      │ Pag-IBIG Home Loan - Enhanced Review        │
      │ extends: pagibig.home-loan.base@1.0.0       │
      │ 1 doc · 8 checklist · 6 signals · 10 gates │
      └─────────────────────────────────────────────┘
```

**Visual Cues:**
- **Base drivers:** Left accent border, "Base" badge
- **Overlays:** Indented, "extends →" with clickable parent links
- **Families:** Collapsible sections with driver count

### Driver Show Page

The "Composition" card shows inheritance relationships:

```
┌─────────────────────────────────────────────────┐
│ 🔀 Composition                                  │
├─────────────────────────────────────────────────┤
│                                                 │
│ Extends:                                        │
│   [📄 bank.home-loan.base@1.0.0]               │
│                                                 │
│ Extended By:                                    │
│   [📄 bank.home-loan.eligible.married@1.0.0]   │
│   [📄 bank.home-loan.eligible.widower@1.0.0]   │
│   [📄 bank.home-loan.income.ofw@1.0.0]         │
│   ... (7 more)                                  │
│                                                 │
└─────────────────────────────────────────────────┘
```

All driver references are clickable links for easy navigation.

---

## Related Documentation

- [Settlement Envelope Driver Guide](../guides/ai-development/SETTLEMENT_ENVELOPE_DRIVER_GUIDE.md) - Full driver development reference
- [Driver Specs README](../reference/driver-specs/README.md) - CSV specification format
- [Settlement Envelope Architecture](SETTLEMENT_ENVELOPE_ARCHITECTURE.md) - Core envelope architecture
