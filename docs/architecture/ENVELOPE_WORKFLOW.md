# Settlement Envelope Workflow Architecture

This document defines the workflow, roles, and state machine for the settlement envelope system used in voucher-based disbursements (PhilHealth claims, bank loans, vendor payouts).

## Overview

A **Settlement Envelope** is a structured evidence collection container attached to a voucher. It tracks:
- Required documents and their review status
- Payload data (structured fields)
- Approval signals from various parties
- Gate conditions that must pass before settlement

## Roles & Permissions

### Role Definitions

| Role | Description | Example Users |
|------|-------------|---------------|
| **Submitter** | Beneficiary/redeemer of the voucher | Patient, borrower, vendor |
| **Provider Staff** | Organization collecting evidence | Hospital billing, loan desk, developer |
| **Reviewer** | Payer/lender approving settlement | PhilHealth, bank underwriter, finance |
| **Admin** | Platform operator | Support staff, system admin |
| **System** | Automated processes | Validation rules, gate computation |

### Permission Matrix

| Action | Submitter | Provider Staff | Reviewer | Admin | System |
|--------|-----------|----------------|----------|-------|--------|
| Complete attestations (consent, selfie, signature) | ✅ | ❌ | ❌ | ✅ | ❌ |
| View checklist status | ✅ (read-only) | ✅ | ✅ | ✅ | ✅ |
| Upload documents | ❌ | ✅ | ❌ | ✅ | ❌ |
| Patch payload fields | ❌ | ✅ | ❌ | ✅ | ✅* |
| Accept/reject attachments | ❌ | ❌ | ✅ | ✅ | ❌ |

\* **System payload patches**: Only for derived/computed fields (e.g., `computed.*`, `system.*`). Never for user-submitted data like names or amounts.
| Set approval signals | ❌ | ❌ | ✅ | ✅ | ✅ |
| Lock envelope | ❌ | ❌ | ✅ | ✅ | ✅ |
| Settle envelope | ❌ | ❌ | ✅ | ✅ | ❌ |
| Cancel envelope | ❌ | ❌ | ❌ | ✅ | ❌ |
| Reopen locked envelope | ❌ | ❌ | ❌ | ✅ | ❌ |

### Key Policies

1. **Separation of Duties**: Uploader ≠ Reviewer (at minimum for `review=required` items)
2. **Audit Trail**: All actions are logged with actor, timestamp, before/after state
3. **Immutability**: Once locked, no changes without explicit reopen (with reason)

## State Machine

### States

| State | Description |
|-------|-------------|
| `DRAFT` | Envelope created, not yet activated |
| `IN_PROGRESS` | Evidence collection active |
| `READY_FOR_REVIEW` | All required items uploaded, pending review |
| `READY_TO_SETTLE` | All gates pass, awaiting settlement action |
| `LOCKED` | Frozen for settlement, no further changes |
| `SETTLED` | Settlement complete, funds disbursed |
| `REJECTED` | Reviewer denied, hard stop |
| `REOPENED` | Unlocked for corrections (audit required) |
| `CANCELLED` | Aborted by admin |

### State Transitions

```
                                    ┌──────────────┐
                                    │   REJECTED   │
                                    └──────────────┘
                                           ▲
                                           │ reject()
┌─────────┐     ┌─────────────┐     ┌──────┴───────┐     ┌────────────────┐
│  DRAFT  │────▶│ IN_PROGRESS │────▶│READY_FOR_    │────▶│ READY_TO_      │
└─────────┘     └─────────────┘     │   REVIEW     │     │   SETTLE       │
  activate()    auto when first     └──────────────┘     └───────┬────────┘
                upload/patch         auto when all                │
                                     required items               │ auto-lock when
                                     uploaded                     │ all gates pass
                                                                  ▼
┌───────────┐                       ┌──────────────┐     ┌────────────────┐
│ CANCELLED │◀────────────────────┐ │   REOPENED   │◀───▶│    LOCKED      │
└───────────┘   cancel()          │ └──────────────┘     └───────┬────────┘
                (admin only)      │   reopen()                   │
                                  │   (admin, needs reason)      │ settle()
                                  │                              ▼
                                  │                       ┌────────────────┐
                                  └───────────────────────│    SETTLED     │
                                                          └────────────────┘
```

### Transition Rules

| From | To | Trigger | Actor |
|------|----|---------|-------|
| DRAFT | IN_PROGRESS | First payload patch or attachment upload | Provider Staff |
| IN_PROGRESS | READY_FOR_REVIEW | All required items are present (`required_present = true`) | System |
| READY_FOR_REVIEW | IN_PROGRESS | Any required item becomes missing (e.g., removed/expired) | System |
| READY_FOR_REVIEW | READY_TO_SETTLE | All gates pass (`required_accepted + signals_satisfied`) | System |
| READY_TO_SETTLE | LOCKED | Auto-lock when settleable, or manual lock | System/Reviewer |

**Invariant**: Once `LOCKED`, `settleable` must remain `true`. If any condition would invalidate settleable, admin must `REOPEN` first.
| LOCKED | SETTLED | Manual settle action | Reviewer/Admin |
| LOCKED | REOPENED | Reopen with reason | Admin |
| REOPENED | IN_PROGRESS | After corrections begin | System |
| Any (except SETTLED) | CANCELLED | Cancel with reason | Admin |
| Any (except SETTLED) | REJECTED | Hard rejection by reviewer (no longer eligible) | Reviewer |

### Computed Flags for Transitions

| Flag | Definition | Used For |
|------|------------|----------|
| `required_present` | All required checklist items have status ≠ `missing` | IN_PROGRESS → READY_FOR_REVIEW |
| `required_accepted` | All required items with `review=required` have status = `accepted` | READY_FOR_REVIEW → READY_TO_SETTLE |
| `signals_satisfied` | All required decision signals are `true` | READY_FOR_REVIEW → READY_TO_SETTLE |
| `blocking_signals` | Array of integration signals still pending (e.g., `[kyc_passed]`) | UI display, ops clarity |

## Settlement Flow

### Default Behavior (v1)

**Two-phase settlement:**
1. **Auto-lock**: When all gates pass (`settleable = true`), system automatically transitions to `LOCKED`
2. **Manual settle**: Authorized user clicks "Settle" to trigger disbursement

### Why Manual Settlement?

- Avoids accidental settlements from mistaken uploads
- Provides a human "moment of accountability"
- Matches how banks/insurers operate today
- System does the hard work (readiness), human confirms intent

### Future: Auto-Settlement (Opt-in)

Auto-settlement may be enabled only when:
- Driver explicitly enables it (`settlement.mode: auto`)
- Gates include a "final approval" signal
- Optional cooling-off delay (`settlement.auto_delay_seconds: 300`)

```yaml
# Driver configuration for auto-settlement
settlement:
  mode: auto  # or 'manual' (default)
  auto_delay_seconds: 300
  auto_requires_signal: final_approval
```

## Document Upload Rules

### Upload Locations

| Location | Primary Users | Use Case |
|----------|---------------|----------|
| **Dashboard** | Provider Staff, Admin | Back-office operations, primary path |
| **Portal** | Submitter (selective) | Self-service for borrower-owned docs only |

### Portal Upload Restrictions

Submitters can only upload via portal when:
1. Driver explicitly allows it per `doc_type`
2. Document type is inherently submitter-owned:
   - Government IDs (front/back)
   - Proof of income
   - Civil status documents
   - Consent forms

### Driver-Level Configuration

```yaml
documents:
  - key: government_id
    label: "Government ID"
    allowed_upload_roles: [submitter, provider_staff]
    review: required
    
  - key: billing_statement
    label: "Billing Statement"
    allowed_upload_roles: [provider_staff]  # Default, submitter cannot upload
    review: required
```

### Provider Attestation for Submitter Uploads

Optionally require provider to "attach" submitter uploads:
```yaml
documents:
  - key: proof_of_income
    allowed_upload_roles: [submitter, provider_staff]
    require_provider_attestation_on_submitter_upload: true
```

## Gates

Gates are boolean conditions computed from envelope state:

| Gate | Description | Auto-computed |
|------|-------------|---------------|
| `payload_valid` | All required payload fields present and valid | ✅ |
| `checklist_complete` | All required checklist items accepted | ✅ |
| `documents_reviewed` | All `review=required` documents accepted | ✅ |
| `signals_satisfied` | Required decision signals are true | ✅ |
| `settleable` | All gates pass, envelope can be settled | ✅ |
| `blocking_signals` | List of pending integration signals | ✅ |

Gates are recomputed whenever:
- Payload is patched
- Attachment is uploaded/reviewed
- Signal is set

## Signal Types

Signals are split into two categories with different permission models:

### Integration Signals (System may set)
These come from external system callbacks:
| Signal | Source | Description |
|--------|--------|-------------|
| `kyc_passed` | HyperVerge callback | Identity verification complete |
| `account_created` | Core banking callback | Settlement account ready |
| `debit_mandate_registered` | Bank callback | Auto-debit authorized |

### Decision Signals (Reviewer only)
These require human judgment:
| Signal | Actor | Description |
|--------|-------|-------------|
| `underwriting_approved` | Reviewer | Loan/claim approved |
| `final_approval` | Reviewer | Ready for settlement |
| `documents_verified` | Reviewer | All docs manually verified |

**Rule**: System should NEVER auto-set decision signals unless explicitly configured in driver.

## Audit Log

All actions are recorded with:
- `action`: What happened (e.g., `payload_patch`, `attachment_upload`, `status_change`)
- `actor_type`: User, System, or API
- `actor_id`: User ID (if applicable)
- `before`: State before change (JSON)
- `after`: State after change (JSON)
- `reason`: Required for admin overrides (cancel, reopen)
- `created_at`: Timestamp

### Audit Actions

| Action | Description |
|--------|-------------|
| `envelope_created` | Envelope instantiated |
| `status_change` | State transition |
| `payload_patch` | Payload fields updated |
| `attachment_upload` | Document uploaded |
| `attachment_review` | Document accepted/rejected |
| `signal_set` | Signal value changed |
| `context_update` | Context data modified |
| `envelope_locked` | Envelope frozen for settlement |
| `envelope_settled` | Settlement completed |
| `envelope_cancelled` | Envelope aborted |
| `envelope_reopened` | Locked envelope reopened |

## Rejection Semantics

### Attachment Rejection vs Envelope Rejection

| Action | Effect | Recoverable? | Next State |
|--------|--------|--------------|------------|
| **Reject attachment** | Single doc marked rejected, checklist item reverts | ✅ Yes - re-upload | Stays in current state |
| **Reject envelope** | Entire envelope marked REJECTED | ❌ No - requires new envelope or admin reopen | REJECTED |

**REJECTED state means**: "No longer eligible for settlement unless admin overrides with documented reason."

### When to use each:
- **Reject attachment**: Document quality issue, wrong file, incomplete scan
- **Reject envelope**: Fraud detected, ineligible beneficiary, policy violation

## Locked State Semantics

### What's Frozen (immutable)
- Payload fields
- Attachments (no upload/delete)
- Checklist item statuses
- Required signals

### What's Allowed
- Settlement execution status updates (System)
- Settlement receipts and confirmations
- Payout reference numbers
- Audit log entries

This ensures settlement processing can complete without being blocked by immutability.

## Implementation Notes

### Critical Invariants (Don't-Miss Checklist)

1. **Separation of duties enforced by code, not convention**
   - If checklist item has `review=required`, block "uploader reviews own upload" at policy level
   - Check `attachment.uploaded_by !== current_user.id` before allowing accept/reject

2. **LOCKED means freeze anything that affects gates**
   - Payload: immutable
   - Attachments: no upload/delete
   - Checklist statuses: frozen
   - Required signals: cannot be changed
   - Only way out: `REOPEN` with documented reason

3. **State transitions derived from computed flags (idempotent)**
   - Never let controllers manually set `READY_FOR_REVIEW`
   - Recompute on every mutation: `required_present`, `required_accepted`, `signals_satisfied`, `settleable`
   - Let state machine advance deterministically based on flag values

4. **System signal restrictions**
   - System can set **integration signals only** (e.g., `kyc_passed`, `account_created`)
   - System must **never** set decision signals (`underwriting_approved`, `final_approval`) unless driver explicitly opts in via `system_settable: true`

### Package vs Host App

- **Package** (`3neti/settlement-envelope`): State machine, models, services, migrations
- **Host App**: UI components, API controllers, role resolution

### Role Resolution

The host app is responsible for mapping authenticated users to envelope roles:

```php
// Example: EnvelopePolicy or middleware
public function resolveEnvelopeRole(User $user, Envelope $envelope): string
{
    if ($user->isAdmin()) return 'admin';
    
    // IMPORTANT: "isOwnedBy" must map to the PROVIDER entity (hospital, developer, lender)
    // NOT the payer. If voucher ownership = payer, this logic needs adjustment.
    if ($envelope->voucher->isOwnedBy($user)) return 'provider_staff';
    
    if ($user->hasRole('reviewer')) return 'reviewer';
    if ($envelope->voucher->contact?->mobile === $user->mobile) return 'submitter';
    return 'guest';
}
```

### API Response Format

Include permissions in envelope API responses:

```json
{
  "data": {
    "id": 1,
    "status": "ready_to_settle",
    "gates_cache": { "settleable": true },
    "permissions": {
      "canUpload": true,
      "canReview": false,
      "canSetSignals": false,
      "canLock": false,
      "canSettle": false,
      "canCancel": false,
      "canReopen": false
    }
  }
}
```

## Related Documents

- [Settlement Envelope UI Plan](../implementation/active/SETTLEMENT_ENVELOPE_UI_PLAN.md)
- [Voucher Types & States](./VOUCHER_ARCHITECTURE.md)
- [Disbursement Flow](./DISBURSEMENT_FLOW.md)
