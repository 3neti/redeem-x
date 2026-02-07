# Settle with Payment - Implementation Plan

**Status**: Planned  
**Created**: 2025-02-07  
**Branch**: TBD (create from `main` when starting)

## Problem Statement
Currently, the "Settle" action on envelopes only updates the status to SETTLED. It doesn't trigger any payment. The goal is to integrate payment collection (pay-in) when an envelope is settled, starting with QR Ph via NetBank Direct Checkout.

## Current Architecture

**Envelope → Voucher Relationship:**
- `Voucher` uses `HasEnvelopes` trait → `envelope()` is a `morphOne` relationship
- Envelope stores `reference_type` and `reference_id` for back-reference
- Envelope `payload` contains transaction details (amount, callback_url, etc.)

**Existing Payment Infrastructure:**
- **Pay-in (Collection)**: `CanCollect` trait in `NetbankPaymentGateway`
  - `initiateCollection()` → returns redirect URL for customer payment
  - Webhook confirms payment via `/webhooks/netbank/payment`
  - Used by top-up flow (`TopUpController`)
- **Pay-out (Disbursement)**: `DisbursementService`
  - `disburse()` → sends funds via InstaPay/PesoNet
  - Used by voucher redemption flow

**Current Settle Flow:**
- `EnvelopeActionController::settle()` → `EnvelopeService::settle()`
- Only updates status to SETTLED and `settled_at` timestamp
- No payment logic

## Proposed Solution

### Phase 1: Payment Details Extraction
Extract payment details from envelope payload for collection.

**Tasks:**
1. Define standard payload fields for payment:
   - `amount` (required) - payment amount
   - `currency` (optional, default: PHP)
   - `callback_url` (optional) - notify external system on completion
   - `payer_mobile` (optional) - for reference
2. Create `EnvelopePaymentData` DTO:
   - Location: `packages/settlement-envelope/src/Data/EnvelopePaymentData.php`
   - Properties: amount, currency, reference, callback_url, metadata
   - Factory method: `fromEnvelope(Envelope $envelope)`
3. Add validation in drivers:
   - Drivers requiring payment must have `amount` in payload schema
   - Gate `payment_required` evaluates if amount > 0

**Files to create/modify:**
- `packages/settlement-envelope/src/Data/EnvelopePaymentData.php` (new)
- `packages/settlement-envelope/src/Services/EnvelopeService.php` (add payment extraction)

### Phase 2: Bank Account Resolution
Determine where payment funds should be credited.

**Resolution Priority:**
1. Envelope `context.bank_account` (explicit override)
2. Voucher owner's default bank account (if envelope → voucher)
3. Host app's settlement account (fallback)

**Tasks:**
1. Add `getPaymentDestination()` to EnvelopeService
2. Create `PaymentDestinationData` DTO with account details
3. Support multiple destination types:
   - User wallet (credit to Bavix wallet)
   - Bank account (for later disbursement)
   - External callback (notify external system)

**Files to create/modify:**
- `packages/settlement-envelope/src/Data/PaymentDestinationData.php` (new)
- `packages/settlement-envelope/src/Services/EnvelopeService.php` (add destination resolution)

### Phase 3: Settle Action → Payment Collection
Integrate payment collection when settle is triggered.

**Flow:**
1. User clicks "Settle" button
2. Controller validates envelope is locked & settleable
3. Extract payment details from payload
4. Initiate collection via `CanCollect::initiateCollection()`
5. Return redirect URL for QR payment page
6. Customer completes payment
7. Webhook confirms → envelope marked SETTLED

**Tasks:**
1. Create `EnvelopePaymentController` for payment endpoints:
   - `POST /api/v1/envelopes/{envelope}/pay/initiate` - start collection
   - `GET /api/v1/envelopes/{envelope}/pay/status` - check payment status
   - `POST /webhooks/envelope-payment` - handle payment confirmation
2. Create `EnvelopePayment` model to track payment attempts:
   - `envelope_id`, `reference_no`, `amount`, `status`, `gateway_response`
3. Modify settle flow:
   - Option A: Two-step (initiate payment → confirm → settle)
   - Option B: Settle triggers async payment collection
4. Add payment status to envelope response:
   - `payment_status`: pending, processing, paid, failed
   - `payment_reference`: NetBank reference number
5. UI updates:
   - Show "Pay Now" button when envelope is locked
   - Display QR code modal for payment
   - Poll for payment status, auto-settle on confirmation

**Files to create/modify:**
- `app/Http/Controllers/Api/V1/EnvelopePaymentController.php` (new)
- `app/Models/EnvelopePayment.php` (new)
- `database/migrations/xxxx_create_envelope_payments_table.php` (new)
- `routes/api.php` (add routes)
- `resources/js/pages/vouchers/Show.vue` (add payment UI)
- `resources/js/components/envelope/EnvelopePaymentModal.vue` (new)

### Phase 4: QR Ph Payment Flow
Implement the complete QR-based payment experience.

**Tasks:**
1. Generate QR code for envelope payment:
   - Reuse `useVoucherQr.ts` composable pattern
   - QR encodes payment redirect URL
2. Create payment landing page:
   - `GET /pay/envelope/{reference}` - public payment page
   - Shows: amount, description, pay button
   - Redirects to NetBank checkout
3. Payment confirmation flow:
   - Customer pays → webhook fires
   - Update `EnvelopePayment` status
   - Trigger `EnvelopeSettled` event
   - Optional: Send callback to external URL
4. Add payment history to envelope UI:
   - List of payment attempts
   - Status, timestamp, amount, reference

**Files to create/modify:**
- `app/Http/Controllers/Pay/EnvelopePayController.php` (new)
- `resources/js/pages/pay/Envelope.vue` (new)
- `resources/js/composables/useEnvelopePayment.ts` (new)

### Phase 5: Error Handling, Rollback & Status Tracking
Robust error handling and auditability.

**Error Scenarios:**
1. Payment initiation fails → show error, allow retry
2. Payment timeout → allow re-initiation
3. Payment rejected → log reason, allow retry
4. Webhook processing fails → idempotent retry handling
5. Partial payment → track vs target amount

**Tasks:**
1. Add payment status to envelope:
   - `payment_status` enum: none, pending, processing, paid, failed
   - `payment_initiated_at`, `payment_completed_at` timestamps
2. Implement idempotent webhook handling:
   - Deduplicate by `payment_id`
   - Log all webhook attempts
3. Add audit log entries:
   - `payment_initiated`, `payment_completed`, `payment_failed`
4. Create retry mechanism:
   - Allow re-initiation if payment failed/expired
   - Track attempt count
5. Add notifications:
   - Notify envelope owner on payment received
   - Notify payer on successful payment

**Files to create/modify:**
- `packages/settlement-envelope/src/Enums/PaymentStatus.php` (new)
- `packages/settlement-envelope/database/migrations/xxxx_add_payment_status_to_envelopes.php` (new)
- `app/Http/Controllers/Webhooks/EnvelopePaymentWebhookController.php` (new)
- `app/Notifications/EnvelopePaymentReceived.php` (new)

## Future Considerations (Out of Scope)
- InstaPay/PesoNet as alternative collection rails (direct debit)
- Partial payments with settlement thresholds
- Recurring payments for subscriptions
- Multi-currency support

## Implementation Order
1. Phase 1 (½ day) - Payment data extraction
2. Phase 2 (½ day) - Destination resolution
3. Phase 3 (1 day) - Core payment integration
4. Phase 4 (1 day) - QR payment flow
5. Phase 5 (½ day) - Error handling & status

**Total estimated effort: 3-4 days**
