# Settlement Envelope Architecture

A driver-based evidence envelope system for settlement gating. This document provides a comprehensive technical reference for the Settlement Envelope package and its integration with the host application.

## 1. Overview

### Purpose
The Settlement Envelope system provides a structured way to collect and validate evidence before allowing settlement of financial transactions. It enforces business rules through configurable drivers, ensuring all required documents, data, and approvals are in place before funds are disbursed.

### Core Concepts

| Concept | Description |
|---------|-------------|
| **Envelope** | A container bound to a settlement reference (e.g., voucher code). Tracks status, payload, attachments, and signals. |
| **Driver** | A YAML configuration defining schema, checklist, documents, signals, and gates for a specific workflow. |
| **Payload** | Versioned JSON metadata attached to the envelope (e.g., payment details, reference IDs). |
| **Attachments** | Typed document uploads with review workflow (e.g., invoices, ID cards, face photos). |
| **Signals** | Boolean flags set by owner or system (e.g., `face_verified`, `kyc_passed`). Cannot be set by external contributors. |
| **Gates** | Computed readiness states that determine when settlement is allowed. Evaluated via expression rules. |
| **Checklist** | A list of items (documents, payload fields, signals) that must be satisfied before settlement. |

### Package Location
```
packages/settlement-envelope/
├── config/                    # Package configuration
├── database/migrations/       # 7 migration files
├── resources/stubs/drivers/   # Default driver YAML files
├── src/
│   ├── Console/              # Artisan commands
│   ├── Data/                 # Data Transfer Objects (Spatie Laravel Data)
│   ├── Enums/                # Status enums
│   ├── Events/               # Domain events
│   ├── Exceptions/           # Custom exceptions
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   └── Traits/               # HasEnvelopes trait
└── tests/                    # Package tests
```

## 2. Package Structure

### 2.1 Models

| Model | Table | Purpose |
|-------|-------|---------|
| `Envelope` | `envelopes` | Main envelope entity with status, payload, gates cache |
| `EnvelopeAttachment` | `envelope_attachments` | Uploaded documents with review status |
| `EnvelopeChecklistItem` | `envelope_checklist_items` | Items from driver template with completion status |
| `EnvelopeSignal` | `envelope_signals` | Boolean flags for external approvals |
| `EnvelopePayloadVersion` | `envelope_payload_versions` | Payload history for audit trail |
| `EnvelopeAuditLog` | `envelope_audit_logs` | All actions logged with before/after state |
| `EnvelopeContributionToken` | `envelope_contribution_tokens` | Signed URL tokens for external contributors |

### 2.2 Services

| Service | Purpose |
|---------|---------|
| `EnvelopeService` | Main service for all envelope operations (create, update, upload, lock, settle) |
| `DriverService` | Loads and caches YAML driver configurations |
| `GateEvaluator` | Evaluates gate expressions against envelope context |
| `PayloadValidator` | Validates payload against JSON schema, handles merge patches |

### 2.3 Enums

**EnvelopeStatus** (10 states):
- `DRAFT` - Initial state
- `IN_PROGRESS` - Evidence collection started
- `READY_FOR_REVIEW` - All required items present (needs review)
- `READY_TO_SETTLE` - All requirements satisfied
- `LOCKED` - Frozen for settlement
- `SETTLED` - Settlement complete (terminal)
- `CANCELLED` - Cancelled by user (terminal)
- `REJECTED` - Rejected by reviewer (terminal)
- `REOPENED` - Reopened from locked state
- `ACTIVE` - Legacy, maps to IN_PROGRESS

**ChecklistItemKind** (4 types):
- `document` - Requires file upload
- `payload_field` - Requires data in payload JSON
- `attestation` - Requires user attestation (future)
- `signal` - Requires boolean signal to be true

**ChecklistItemStatus** (5 states):
- `MISSING` - Not yet provided
- `UPLOADED` - Document uploaded, awaiting review
- `NEEDS_REVIEW` - Requires human review
- `ACCEPTED` - Approved
- `REJECTED` - Rejected (blocks settlement)

**ReviewMode** (3 modes):
- `none` - Auto-accept on upload
- `optional` - Review available but not required
- `required` - Must be reviewed before acceptance

### 2.4 Traits

**HasEnvelopes** - Add to any model that needs envelope functionality:
```php
use LBHurtado\SettlementEnvelope\Traits\HasEnvelopes;

class Voucher extends Model
{
    use HasEnvelopes;
}

// Usage
$voucher->createEnvelope('payable.default', '1.0.0', $payload);
$voucher->envelope;                    // Get primary envelope
$voucher->isEnvelopeSettleable();     // Check settleable gate
$voucher->setEnvelopeSignal('approved', true);
```

### 2.5 Events

| Event | Fired When |
|-------|------------|
| `EnvelopeCreated` | New envelope created |
| `PayloadUpdated` | Payload patched |
| `AttachmentUploaded` | Document uploaded |
| `AttachmentReviewed` | Document accepted/rejected |
| `SignalChanged` | Signal value changed |
| `GateChanged` | Gate value changed (e.g., settleable) |

## 3. Envelope State Machine

### State Flow Diagram
```
                                    ┌─────────────┐
                                    │  CANCELLED  │ (terminal)
                                    └─────────────┘
                                          ▲
                                          │ cancel()
    ┌───────┐    auto     ┌─────────────┐ │    ┌──────────────────┐
    │ DRAFT │ ─────────▶  │ IN_PROGRESS │ ├───▶│ READY_FOR_REVIEW │
    └───────┘             └─────────────┘      └──────────────────┘
                                                        │
                                                        │ auto (all accepted)
                                                        ▼
    ┌─────────┐   settle()   ┌────────┐   lock()   ┌─────────────────┐
    │ SETTLED │ ◀─────────── │ LOCKED │ ◀───────── │ READY_TO_SETTLE │
    └─────────┘              └────────┘            └─────────────────┘
    (terminal)                   │ ▲                       │
                        reopen() │ │                       │
                                 ▼ │                       │
                            ┌──────────┐                   │
                            │ REOPENED │ ──────────────────┘
                            └──────────┘       (re-evaluate)

    ┌──────────┐
    │ REJECTED │ (terminal - can be reached from any non-terminal state)
    └──────────┘
```

### Auto-Advance Logic
The `recomputeGates()` method automatically advances state based on computed flags:

1. **DRAFT → IN_PROGRESS**: When any evidence is added
2. **IN_PROGRESS → READY_FOR_REVIEW**: When `required_present` is true (all required items have status ≠ missing)
3. **READY_FOR_REVIEW → READY_TO_SETTLE**: When `required_accepted` is true AND all required signals are satisfied
4. **REOPENED → READY_TO_SETTLE**: Same conditions as above

### Status Methods
```php
$envelope->status->canEdit();      // Can modify payload/attachments?
$envelope->status->canLock();      // Can lock for settlement?
$envelope->status->canSettle();    // Can settle? (only LOCKED)
$envelope->status->canCancel();    // Can cancel?
$envelope->status->canReopen();    // Can reopen? (only LOCKED)
$envelope->status->isTerminal();   // Is final state?
```

## 4. Driver System

### Driver Location
Drivers are YAML files stored in `storage/app/envelope-drivers/`:
```
storage/app/envelope-drivers/
├── payable.default/
│   └── v1.0.0.yaml
├── vendor.pay-by-face/
│   └── v1.0.0.yaml
├── bank.home-loan-takeout/
│   └── v1.0.0.yaml
├── simple.envelope/
│   └── v1.0.0.yaml
└── simple.test/
    └── v1.0.0.yaml
```

### Driver Structure
```yaml
driver:
  id: "payable.default"
  version: "1.0.0"
  title: "Default Payable Driver"
  description: "Auto-settle driver for payable vouchers"
  domain: "payment"
  issuer_type: "voucher"

payload:
  schema:
    id: "payable.default.v1"
    format: "json_schema"
    inline:
      type: "object"
      additionalProperties: true
  storage:
    mode: "versioned"
    patch_strategy: "merge"

documents:
  registry:
    - type: "REFERENCE_DOC"
      title: "Reference Document"
      allowed_mimes: ["application/pdf", "image/jpeg", "image/png"]
      max_size_mb: 5
      multiple: true

checklist:
  template:
    - key: "payload_present"
      label: "Payment details provided"
      kind: "payload_field"
      payload_pointer: "/"
      required: true
      review: "none"
    - key: "reference_documents"
      label: "Reference documents uploaded"
      kind: "document"
      doc_type: "REFERENCE_DOC"
      required: false
      review: "none"

signals:
  definitions:
    - key: "face_verified"
      type: "boolean"
      source: "host"
      default: false
      required: true

gates:
  definitions:
    - key: "settleable"
      rule: "payload != null && signal.face_verified"
```

### Available Drivers

| Driver | Use Case | Key Features |
|--------|----------|--------------|
| `payable.default` | Payment vouchers | Freeform payload, optional reference docs, auto-settle when payload exists |
| `vendor.pay-by-face` | Face verification payments | Face photo required, ID optional, face_verified signal required |
| `bank.home-loan-takeout` | Home loan settlements | Complex payload schema, multiple document types, KYC signals |
| `simple.envelope` | Basic testing | Minimal configuration |
| `simple.test` | Unit testing | Minimal configuration |

### Form Flow Mapping

Drivers can include a `form_flow_mapping` section that defines how form flow collected data maps to envelope payload and attachments. This enables declarative, per-driver configuration instead of hardcoded mapping logic.

```yaml
form_flow_mapping:
  payload:
    redeemer:
      name: "bio_fields.full_name | bio_fields.name"   # Fallback syntax
      mobile: "wallet_info.mobile"
      birth_date: "bio_fields.birth_date"
    location:
      latitude: "location_capture.latitude:float"      # Type casting
      longitude: "location_capture.longitude:float"
      formatted_address: "location_capture.address.formatted | location_capture.formatted_address"

  attachments:
    SELFIE:
      source: "selfie_capture.selfie"
      filename: "selfie.jpg"
      mime: "image/jpeg"
    SIGNATURE:
      source: "signature_capture.signature"
      filename: "signature.png"
      mime: "image/png"
```

**Mapping Syntax:**
- Simple path: `"bio_fields.name"` → `Arr::get($data, 'bio_fields.name')`
- Fallback: `"path1 | path2"` → tries path1 first, falls back to path2
- Type cast: `":float"`, `":int"`, `":bool"` suffixes
- Nested: `"step.field.subfield"` via dot notation

**Composition Support:**
When using `extends`, form_flow_mapping sections are deep-merged:
- Payload sections: child fields override parent fields within same section
- Attachments: merged by doc_type key (child overrides parent for same type)

### Loading Drivers
```php
$driverService = app(DriverService::class);

// Load specific version
$driver = $driverService->load('payable.default', '1.0.0');

// Load latest version
$driver = $driverService->load('payable.default');

// List all available drivers
$drivers = $driverService->list();

// Access form flow mapping (may be null)
$mapping = $driver->form_flow_mapping;
if ($mapping) {
    $redeemerFields = $mapping->getPayloadSection('redeemer');
    $selfieConfig = $mapping->getAttachmentMapping('SELFIE');
}
```

## 5. Checklist System

### Checklist Item Kinds

**payload_field** - Auto-completed when data exists at JSON pointer:
```yaml
- key: "amount_provided"
  kind: "payload_field"
  payload_pointer: "/amount"
  required: true
```

**document** - Requires file upload with optional review:
```yaml
- key: "face_photo"
  kind: "document"
  doc_type: "FACE_PHOTO"
  required: true
  review: "none"  # Auto-accept on upload
```

**signal** - Requires boolean signal to be true:
```yaml
- key: "face_verified"
  kind: "signal"
  signal_key: "face_verified"
  required: true
```

### Review Modes
- `none`: Document auto-accepted on upload (status → ACCEPTED)
- `optional`: Document goes to NEEDS_REVIEW, can be accepted/rejected
- `required`: Same as optional, but review is mandatory before settlement

### Status Computation
```php
// EnvelopeChecklistItem::computeStatus()
// Called after attachment upload

if ($this->kind === ChecklistItemKind::DOCUMENT) {
    $attachment = $this->getLatestAttachment();
    
    if (!$attachment) {
        // MISSING
    } elseif ($attachment->review_status === 'accepted') {
        // ACCEPTED
    } elseif ($attachment->review_status === 'rejected') {
        // REJECTED
    } elseif ($this->review_mode->allowsReview()) {
        // NEEDS_REVIEW
    } else {
        // ACCEPTED (review: none)
    }
}
```

## 6. Gate Evaluation

### Expression Syntax
Gates use simple boolean expressions with dot notation:

```yaml
gates:
  definitions:
    - key: "payload_valid"
      rule: "payload.valid == true"
    - key: "face_captured"
      rule: "checklist.face_photo == true"
    - key: "evidence_ready"
      rule: "gate.payload_valid && gate.face_captured"
    - key: "settleable"
      rule: "gate.evidence_ready && signal.face_verified"
```

### Supported Operators
- `&&` - Logical AND
- `||` - Logical OR
- `==` - Equality
- `!=` - Inequality
- `!` - Negation
- Dot notation for nested values

### Context Object
```php
$context = [
    'payload' => [
        'valid' => true,           // Has non-empty payload
        'version' => 3,            // Current payload version
    ],
    'checklist' => [
        'total' => 5,
        'required_count' => 3,
        'required_present' => true,   // All required items have status ≠ missing
        'required_accepted' => true,  // All required items have status = accepted
        'all_accepted' => false,
        'has_rejected' => false,
        'pending_count' => 2,
    ],
    'signal' => [
        'face_verified' => true,
        'callback_sent' => false,
        '_blocking' => [],            // Keys of unsatisfied required signals
        '_all_satisfied' => true,     // All required signals are true
    ],
    'envelope' => [
        'status' => 'ready_to_settle',
        'payload_version' => 3,
    ],
    'gate' => [
        // Previous gate results for chaining
        'payload_valid' => true,
        'face_captured' => true,
    ],
];
```

## 7. Host App Integration

### Voucher Model Setup
```php
// app/Models/Voucher.php (or in voucher package)
use LBHurtado\SettlementEnvelope\Traits\HasEnvelopes;

class Voucher extends Model
{
    use HasEnvelopes;
    
    // Override reference code generation (optional)
    public function getEnvelopeReferenceCode(): string
    {
        return $this->code; // Use voucher code
    }
}
```

### Automatic Envelope Creation
In `GenerateVouchers` action, envelopes are auto-created for payable/settlement vouchers:

```php
// app/Actions/Api/Vouchers/GenerateVouchers.php
if ($isPayableOrSettlement && !$envelopeConfig) {
    $envelope = $voucher->createEnvelope(
        driverId: 'payable.default',
        driverVersion: '1.0.0',
        initialPayload: $externalMetadata,
        context: [
            'created_via' => 'voucher_generation_auto',
            'voucher_type' => $voucherType,
        ],
        actor: $request->user()
    );
}
```

### VoucherController Integration
```php
// app/Http/Controllers/Vouchers/VoucherController.php
public function show(Voucher $voucher): Response
{
    $envelope = $voucher->envelope;
    if ($envelope) {
        $envelope->load(['checklistItems', 'attachments', 'signals', 'auditLogs']);
        
        $envelopeService = app(EnvelopeService::class);
        $gates = $envelopeService->computeGates($envelope);
        
        $data['envelope'] = [
            'id' => $envelope->id,
            'status' => $envelope->status->value,
            'gates_cache' => $gates,
            'status_helpers' => [
                'can_edit' => $envelope->status->canEdit(),
                'can_lock' => $envelope->status->canLock(),
                // ...
            ],
            'checklist_items' => $envelope->checklistItems->map(...),
            'attachments' => $envelope->attachments->map(...),
            'signals' => $envelope->signals->map(...),
            'audit_logs' => $envelope->auditLogs->map(...),
        ];
    }
    
    return Inertia::render('vouchers/Show', $data);
}
```

## 8. Configuration

### Package Config (`config/settlement-envelope.php`)
```php
return [
    'driver_disk' => env('ENVELOPE_DRIVER_DISK', 'envelope-drivers'),
    'storage_disk' => env('ENVELOPE_STORAGE_DISK', 'public'),
    'audit' => [
        'enabled' => env('ENVELOPE_AUDIT_ENABLED', true),
        'capture' => [
            'payload_patch',
            'attachment_upload',
            'attachment_review',
            'attestation_submit',
            'signal_set',
            'gate_change',
            'status_change',
        ],
    ],
    'manifest' => [
        'enabled' => env('ENVELOPE_MANIFEST_ENABLED', true),
        'algorithm' => 'sha256',
    ],
    'actor_model' => env('ENVELOPE_ACTOR_MODEL', 'App\\Models\\User'),
];
```

### Filesystem Disk (`config/filesystems.php`)
```php
'disks' => [
    'envelope-drivers' => [
        'driver' => 'local',
        'root' => storage_path('app/envelope-drivers'),
        'throw' => false,
    ],
],
```

### Environment Variables
```bash
ENVELOPE_DRIVER_DISK=envelope-drivers
ENVELOPE_STORAGE_DISK=public
ENVELOPE_AUDIT_ENABLED=true
ENVELOPE_MANIFEST_ENABLED=true
```

## 9. Database Schema

### Tables Overview

**envelopes**
```sql
- id, reference_code (unique), reference_type, reference_id
- driver_id, driver_version
- payload (JSON), payload_version, context (JSON), gates_cache (JSON)
- status (enum)
- locked_at, settled_at, cancelled_at
- created_at, updated_at
```

**envelope_checklist_items**
```sql
- id, envelope_id (FK)
- key, label, kind (enum), status (enum)
- doc_type, payload_pointer, attestation_type, signal_key
- required (bool), review_mode (enum)
```

**envelope_attachments**
```sql
- id, envelope_id (FK), checklist_item_id (FK nullable)
- doc_type, original_filename, file_path, disk, mime_type, size, hash
- metadata (JSON)
- uploaded_by, review_status, reviewed_by, reviewed_at, rejection_reason
```

**envelope_signals**
```sql
- id, envelope_id (FK)
- key, type, value, source
```

**envelope_payload_versions**
```sql
- id, envelope_id (FK)
- version, payload (JSON), patch (JSON)
- changed_by, created_at
```

**envelope_audit_logs**
```sql
- id, envelope_id (FK)
- action, actor_type, actor_id, actor_role
- before (JSON), after (JSON), reason, metadata (JSON)
- created_at
```

**envelope_contribution_tokens**
```sql
- id, envelope_id (FK)
- token (UUID, unique), label
- recipient_name, recipient_email, recipient_mobile
- password_hash, expires_at, used_at
- created_at, updated_at
```

## 10. UI Components

### Component Location
```
resources/js/components/envelope/
├── index.ts                    # Exports all components
├── EnvelopeStatusCard.vue      # Status display with action buttons
├── EnvelopeChecklistCard.vue   # Checklist progress visualization
├── EnvelopeAttachmentsCard.vue # Document list with review actions
├── EnvelopeSignalsCard.vue     # Signal toggles
├── EnvelopePayloadCard.vue     # JSON payload editor
├── EnvelopeAuditLog.vue        # Activity timeline
├── ContributionLinkCard.vue    # Generate/manage contribution links
├── DocumentUploadModal.vue     # File upload dialog
├── ReasonModal.vue             # Reason input for cancel/reject/reopen
├── EnvelopeConfigCard.vue      # Create envelope with driver selection
└── DriverSelector.vue          # Driver dropdown
```

### Composables
```
resources/js/composables/
├── useEnvelope.ts              # Envelope state types
└── useEnvelopeActions.ts       # API actions (lock, settle, etc.)
```

### Usage in Voucher Show Page
```vue
<template>
  <div v-if="hasEnvelope && envelope">
    <EnvelopeStatusCard :envelope="envelope">
      <template #actions="{ canLock, canSettle }">
        <Button v-if="canLock" @click="handleLock">Lock</Button>
        <Button v-if="canSettle" @click="handleSettle">Settle</Button>
      </template>
    </EnvelopeStatusCard>
    
    <EnvelopeChecklistCard :items="envelope.checklist_items" />
    <EnvelopeSignalsCard :signals="envelope.signals" @toggle="handleSignalToggle" />
    <EnvelopeAttachmentsCard :attachments="envelope.attachments" />
    <EnvelopePayloadCard :payload="envelope.payload" />
    <ContributionLinkCard :voucher-code="voucher.code" :tokens="contributionTokens" />
    <EnvelopeAuditLog :entries="envelope.audit_logs" />
  </div>
</template>
```

## 11. Pages Integration

### `/vouchers/{code}` - Voucher Show Page
- **Envelope Tab**: Full envelope management UI
- Shows: status, checklist, attachments, signals, payload, audit log
- Actions: upload, review, toggle signals, lock, settle, cancel, reopen
- Contribution links: generate, copy, revoke

### `/portal` - Voucher Generation
- Voucher type selection: redeemable, payable, settlement
- Auto-creates envelope for payable/settlement types
- External metadata passed as initial payload
- Attachments uploaded to envelope (not voucher)

### `/pay` - Payment Page
- Shows voucher quote with external metadata from envelope
- Displays payment details for payer reference
- QR code generation for InstaPay payments

### `/contribute` - External Contribution Page
- Accessed via signed URL with token
- Optional password protection
- Document upload with type selection
- Payload editing (limited fields)
- Tracks uploads per document type (respects max_files)
- Delete pending attachments

## 12. API Endpoints

### Envelope Management (`routes/api/vouchers.php`)
```
POST   /api/v1/vouchers/{code}/envelope                    # Create envelope
POST   /api/v1/vouchers/{code}/envelope/lock               # Lock envelope
POST   /api/v1/vouchers/{code}/envelope/settle             # Settle envelope
POST   /api/v1/vouchers/{code}/envelope/cancel             # Cancel with reason
POST   /api/v1/vouchers/{code}/envelope/reopen             # Reopen with reason
POST   /api/v1/vouchers/{code}/envelope/reject             # Reject with reason
```

### Attachment Management
```
POST   /api/v1/vouchers/{code}/envelope/attachments        # Upload document
POST   /api/v1/vouchers/{code}/envelope/attachments/{id}/accept
POST   /api/v1/vouchers/{code}/envelope/attachments/{id}/reject
```

### Signal Management
```
POST   /api/v1/vouchers/{code}/envelope/signals/{key}/toggle
```

### Contribution Tokens
```
POST   /api/v1/vouchers/{code}/contribution-links          # Generate link
GET    /api/v1/vouchers/{code}/contribution-links          # List links
DELETE /api/v1/vouchers/{code}/contribution-links/{token}  # Revoke link
```

### Public Contribution Routes (`routes/contribute.php`)
```
GET    /contribute                     # Show contribution page (signed URL)
POST   /contribute/verify-password     # Verify password
POST   /contribute/upload              # Upload document
POST   /contribute/payload             # Update payload
POST   /contribute/delete              # Delete pending attachment
```

## 13. Artisan Commands

### `envelope:install-drivers`
Install default drivers from package stubs to storage:
```bash
php artisan envelope:install-drivers
php artisan envelope:install-drivers --force  # Overwrite existing
```

### `test:envelope`
Test envelope workflow end-to-end:
```bash
php artisan test:envelope --upload-doc --auto-review --auto-settle
php artisan test:envelope --scenario=evidence --upload-doc
php artisan test:envelope --scenario=settle --upload-doc --auto-review
```

### `test:contribution-link`
Generate test contribution link:
```bash
php artisan test:contribution-link VOUCHER-CODE
php artisan test:contribution-link VOUCHER-CODE --recipient="Vendor ABC"
php artisan test:contribution-link VOUCHER-CODE --password=secret123
```

### `vouchers:migrate-to-envelopes`
Migrate legacy vouchers to use envelopes:
```bash
php artisan vouchers:migrate-to-envelopes --dry-run
php artisan vouchers:migrate-to-envelopes --force
php artisan vouchers:migrate-to-envelopes --code=XXXX
```

## 14. Security Considerations

### Signal Tamper Protection
- Signals can only be set by authenticated voucher owners or system
- External contributors cannot modify signals via contribution page
- Signals represent attestations/approvals that require authorization

### Contribution Link Security
- Signed URLs with cryptographic signatures
- Optional password protection (bcrypt hashed)
- Expiration timestamps
- Audit trail with IP address and user agent
- Documents uploaded via contribution have `pending` review status

### Payload Validation
- Schema validation for typed drivers
- External contributor payload updates bypass schema (partial data allowed)
- Full validation enforced when envelope owner finalizes

## 15. Extension Points

### Custom Drivers
Create new YAML files in `storage/app/envelope-drivers/{driver-id}/v{version}.yaml`

### Custom Events
Listen to envelope events for integration:
```php
Event::listen(EnvelopeCreated::class, function ($event) {
    // Notify stakeholders
});

Event::listen(GateChanged::class, function ($event) {
    if ($event->gate === 'settleable' && $event->newValue === true) {
        // Trigger settlement workflow
    }
});
```

### Custom Gate Logic
Extend `GateEvaluator` for complex business rules beyond expression syntax.
