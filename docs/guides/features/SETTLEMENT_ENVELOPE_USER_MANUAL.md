# Settlement Envelope User Manual

This guide explains how to use Settlement Envelopes to manage evidence collection and approval workflows before disbursing funds.

## 1. Introduction

### What is a Settlement Envelope?

A Settlement Envelope is a digital container that collects all required evidence before allowing a financial transaction to complete. Think of it as a checklist that must be satisfied before money can be released.

**Key benefits:**
- **Compliance**: Ensures all required documents are collected
- **Audit Trail**: Every action is logged with timestamps
- **Workflow Control**: Automatic state progression based on rules
- **External Collaboration**: Share links with vendors/recipients to upload documents

### When to Use Settlement Envelopes

| Voucher Type | Envelope Created | Use Case |
|--------------|------------------|----------|
| **Redeemable** | Optional | Standard vouchers - no envelope needed |
| **Payable** | Automatic | Payment vouchers - collect invoices/receipts before paying |
| **Settlement** | Automatic | Complex settlements - collect evidence, verify identity |

## 2. Creating Envelopes

### Automatic Creation (Recommended)

When you generate a **payable** or **settlement** voucher via the Portal, an envelope is automatically created:

1. Go to **Portal** (`/portal`)
2. Select voucher type: **Payable** or **Settlement**
3. Enter amount and payment details
4. Click **Generate**

The envelope is created with the `payable.default` driver, which auto-settles when payment details are provided.

### Manual Creation

For existing vouchers without an envelope:

1. Go to **Vouchers** → Click on a voucher
2. Navigate to the **Envelope** tab
3. If no envelope exists, you'll see **Create Envelope**
4. Select a driver from the dropdown
5. Click **Create**

### Via API

```bash
POST /api/v1/vouchers/{code}/envelope
Content-Type: application/json

{
    "driver_id": "vendor.pay-by-face",
    "driver_version": "1.0.0",
    "initial_payload": {
        "reference_id": "INV-001",
        "amount": 1000
    }
}
```

## 3. Understanding the Envelope Tab

The Envelope tab on the Voucher Show page displays:

### Status Card
Shows current envelope status with available actions:
- **Draft** → Waiting for evidence
- **In Progress** → Evidence collection underway
- **Ready for Review** → All items present, needs approval
- **Ready to Settle** → All requirements satisfied
- **Locked** → Frozen, ready for settlement
- **Settled** → Complete ✓

### Checklist Card
Visual progress indicator showing:
- Required items (must be completed)
- Optional items (recommended but not blocking)
- Status of each item: Missing, Needs Review, Accepted, Rejected

### Signals Card
Boolean flags that require manual approval:
- Toggle switches for owner-controlled signals
- Shows which signals are blocking settlement

### Attachments Card
List of uploaded documents:
- Document type and filename
- Review status (Pending, Accepted, Rejected)
- Actions: Accept, Reject (for reviewers)
- Upload button to add new documents

### Payload Card
JSON data attached to the envelope:
- View current payload
- Edit payload (if envelope is editable)
- Version history

### Audit Log
Timeline of all actions:
- Who did what and when
- Before/after state changes
- Reasons for rejections or cancellations

## 4. Managing Documents

### Uploading Documents

1. In the **Attachments** section, click **Upload**
2. Select document type from dropdown (defined by driver)
3. Choose file (PDF, JPEG, PNG - max 5-10MB depending on driver)
4. Click **Upload**

The document will appear with status:
- **Accepted** (if `review: none` in driver)
- **Needs Review** (if review required)

### Reviewing Documents

For documents that need review:

1. Find the document in the Attachments card
2. Click **Accept** (✓) or **Reject** (✗)
3. If rejecting, provide a reason

**Note:** Rejected documents can be re-uploaded by the contributor.

### Document Types

Each driver defines what documents can be uploaded:

| Driver | Document Types |
|--------|---------------|
| `payable.default` | REFERENCE_DOC (invoices, receipts) |
| `vendor.pay-by-face` | FACE_PHOTO (required), ID_FRONT, ID_BACK (optional) |

## 5. Working with Signals

### What are Signals?

Signals are boolean flags that represent manual approvals or external verifications:
- `face_verified` - Face matches ID photo
- `kyc_passed` - Identity verification complete
- `callback_sent` - Notification sent to callback URL

### Toggling Signals

1. In the **Signals** card, find the signal
2. Click the toggle switch to turn on/off
3. Change is saved immediately

**Important:** Only the voucher owner can toggle signals. External contributors cannot modify signals.

### Blocking Signals

If a signal is required but not set, it will appear in the "Blocking" list. The envelope cannot be settled until all required signals are satisfied.

## 6. Envelope State Transitions

### Automatic Transitions

The envelope automatically advances through states:

```
DRAFT
  ↓ (when first evidence is added)
IN_PROGRESS
  ↓ (when all required items have status ≠ missing)
READY_FOR_REVIEW
  ↓ (when all required items are accepted AND signals satisfied)
READY_TO_SETTLE
```

### Manual Actions

| Action | When Available | Effect |
|--------|----------------|--------|
| **Lock** | Ready to Settle | Freezes envelope for settlement |
| **Settle** | Locked | Marks envelope as settled (triggers disbursement) |
| **Cancel** | Any non-terminal state | Cancels envelope (requires reason) |
| **Reject** | Any non-terminal state | Rejects envelope (requires reason) |
| **Reopen** | Locked only | Reopens for additional edits (requires reason) |

### Performing Actions

1. In the **Status** card, find the action buttons
2. Click the desired action (Lock, Settle, Cancel, etc.)
3. For Cancel/Reject/Reopen, enter a reason in the modal
4. Confirm the action

## 7. External Document Contribution

### Generating a Contribution Link

Share a link with external parties (vendors, suppliers) to upload documents:

1. Go to the **Envelope** tab
2. Find the **Contribution Links** card
3. Click **Generate Link**
4. Configure options:
   - **Label**: Descriptive name (e.g., "Invoice from ABC Corp")
   - **Recipient Name**: Who will receive the link
   - **Recipient Email/Mobile**: Contact info (optional)
   - **Password**: Optional password protection
   - **Expiration**: 7 days (default)
5. Click **Generate**
6. Copy the link and share with the recipient

### Contribution Page Features

External contributors see a simplified page:

- Voucher details (code, amount)
- Document upload area with type selection
- Payload editing (limited to allowed fields)
- Cannot modify signals
- Cannot perform envelope actions (lock, settle, etc.)

### Upload Limits

Each document type has a `max_files` limit:
- `multiple: false` → 1 file per type
- `multiple: true` → Multiple files allowed

Contributors can:
- Upload until the limit is reached
- Delete their pending uploads
- Re-upload if a document was rejected

### Password Protection

If you set a password:
1. Recipient opens the link
2. Enters the password
3. Gains access to the contribution page

Passwords are securely hashed and never logged.

### Audit Trail

All contribution activity is logged:
- Token ID used
- IP address
- User agent
- Uploaded files
- Payload changes

## 8. Driver Selection Guide

### `payable.default` - Simple Payment Vouchers

**Best for:** Bills, invoices, vendor payments

**Requirements:**
- Payload data (amount, reference_id, callback_url)
- Optional: Reference documents (invoices)

**Auto-settles when:** Payload exists

### `vendor.pay-by-face` - Face Verification Payments

**Best for:** High-value payments requiring identity verification

**Requirements:**
- Payload: reference_id, amount, callback_url
- Face photo (required)
- ID card front/back (optional)
- Signal: `face_verified` must be toggled on

**Settles when:** Face photo uploaded AND face_verified signal is true

### Custom Drivers

Create custom drivers for specific workflows by adding YAML files to:
```
storage/app/envelope-drivers/{driver-id}/v{version}.yaml
```

## 9. Troubleshooting

### Envelope Won't Advance to "Ready to Settle"

**Check:**
1. **Checklist**: Are all required items Accepted?
2. **Signals**: Are all required signals toggled on?
3. **Rejected items**: Were any documents rejected? Re-upload needed.

**Solution:**
- Review the checklist card for missing/rejected items
- Check blocking signals in the signals card
- Upload missing documents or toggle required signals

### Document Upload Fails

**Possible causes:**
- File too large (check max_size_mb in driver)
- Invalid file type (check allowed_mimes)
- Envelope not editable (status is Locked/Settled/etc.)

**Solution:**
- Compress or convert the file
- Check driver configuration for allowed types
- Reopen envelope if needed (requires reason)

### "Cannot Lock Envelope" Error

**Cause:** Envelope is not in "Ready to Settle" state

**Check:**
- All required checklist items must be Accepted
- All required signals must be true

### Contribution Link Not Working

**Possible causes:**
- Link expired (default 7 days)
- Link revoked
- Invalid signature (link tampered)

**Solution:**
- Generate a new contribution link
- Check token status in Contribution Links card

### Payload Validation Error

**Cause:** Payload doesn't match driver's JSON schema

**Solution:**
- For external contributors: Bypass is automatic (partial data allowed)
- For owner: Ensure payload matches required schema fields

### Audit Log Missing Actions

**Cause:** Audit logging may be disabled

**Check:** `ENVELOPE_AUDIT_ENABLED=true` in `.env`

## 10. Quick Reference

### Status Flow
```
DRAFT → IN_PROGRESS → READY_FOR_REVIEW → READY_TO_SETTLE → LOCKED → SETTLED
```

### Checklist Item Kinds
- `payload_field` - Data in payload JSON
- `document` - Uploaded file
- `signal` - Boolean flag (owner-controlled)
- `attestation` - User attestation (future)

### Review Statuses
- `pending` - Awaiting review
- `accepted` - Approved ✓
- `rejected` - Rejected ✗

### Common Actions
| I want to... | Do this... |
|--------------|------------|
| Add a document | Envelope tab → Attachments → Upload |
| Approve a document | Attachments → Click ✓ on the document |
| Enable a signal | Signals → Toggle the switch |
| Lock for settlement | Status card → Lock Envelope |
| Share with vendor | Contribution Links → Generate Link |
| Cancel envelope | Status card → Cancel (provide reason) |

## 11. API Quick Reference

### Create Envelope
```bash
POST /api/v1/vouchers/{code}/envelope
{
    "driver_id": "payable.default",
    "initial_payload": { "amount": 1000 }
}
```

### Upload Document
```bash
POST /api/v1/vouchers/{code}/envelope/attachments
Content-Type: multipart/form-data

doc_type=REFERENCE_DOC
file=@invoice.pdf
```

### Toggle Signal
```bash
POST /api/v1/vouchers/{code}/envelope/signals/face_verified/toggle
```

### Lock Envelope
```bash
POST /api/v1/vouchers/{code}/envelope/lock
```

### Settle Envelope
```bash
POST /api/v1/vouchers/{code}/envelope/settle
```

### Generate Contribution Link
```bash
POST /api/v1/vouchers/{code}/contribution-links
{
    "label": "Invoice Upload",
    "recipient_name": "ABC Vendor",
    "password": "secret123",
    "expires_days": 7
}
```

---

For technical details and architecture documentation, see:
[Settlement Envelope Architecture](../../architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md)
