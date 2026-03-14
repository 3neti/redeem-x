# /pay Endpoint — UI Rendering Flow Map

**Version**: 1.0
**Last Updated**: 2025-03-13
**Audience**: Human developers + AI agents (Claude Code, Warp Oz, Junie)

This document maps the complete UI rendering sequence for the `/pay` endpoint — from code entry to payment confirmation, including the QR generation pipeline, deposit classification, SMS confirmation loop, and wallet ledger mechanics.

> **For disburse flow comparison**, see `docs/guides/features/DISBURSE_FLOW_MAP.md`.
> **For settlement envelope architecture**, see `docs/architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md`.

---

## Flow Diagram

```
┌─────────────────────── PHASE 1: CODE ENTRY ──────────────────────┐
│                                                                   │
│  pay/Index.vue ──▶ PayWidget.vue                                  │
│       │                (code input form)                          │
│       │ On submit: POST /pay/quote                                │
│       ▼                                                           │
│  PayVoucherController::quote()                                    │
│       │ validates voucher (canAcceptPayment)                      │
│       │ computes remaining = target_amount - getPaidTotal()        │
│       │ returns JSON: quote data + external_metadata + attachments │
│       ▼                                                           │
│  PayWidget emits 'quote-loaded' → Index.vue shows Step 2          │
│                                                                   │
└──────────────────────────────┬────────────────────────────────────┘
                               │
┌──────────────────── PHASE 2: QR GENERATION ───────────────────────┐
│                              │                                    │
│  pay/Index.vue (Step 2) ◀───┘                                     │
│       │ shows payment details card                                │
│       │ amount input (default = remaining)                        │
│       │ "Generate QR" button                                      │
│       │                                                           │
│       │ POST /api/v1/pay/generate-qr                              │
│       ▼                                                           │
│  GeneratePaymentQr action                                         │
│       │ validates voucher + amount ≤ remaining                    │
│       │ resolves owner account number                             │
│       │ calls gateway->generate() for InstaPay QR                 │
│       │ creates PaymentRequest (status: pending)                  │
│       │ caches QR data (5 min TTL)                                │
│       │ returns: qr_code, qr_id, payment_request_id, expires_at  │
│       ▼                                                           │
│  pay/Index.vue (Step 3)                                           │
│       │ renders QrDisplay.vue with InstaPay QR image              │
│       │ download button, mobile instructions                      │
│       │ "Payment Done" button (green, prominent)                  │
│       │                                                           │
│       │ POST /api/v1/pay/mark-done                                │
│       ▼                                                           │
│  MarkPaymentDone action                                           │
│       │ transitions PaymentRequest: pending → awaiting_confirmation│
│       │ stores optional payer_info                                │
│       ▼                                                           │
│  pay/Index.vue shows "Payment marked as done" confirmation        │
│                                                                   │
└──────────────────────────────┬────────────────────────────────────┘
                               │
┌────────────── PHASE 3: ASYNC PAYMENT CONFIRMATION ────────────────┐
│                              │                                    │
│  Two parallel confirmation paths:                                 │
│                                                                   │
│  ┌─ PATH A: InstaPay QR (deposit classification) ─────────────┐  │
│  │ Bank deposit lands in system account                        │  │
│  │       │                                                     │  │
│  │ OmnipayPaymentGateway::handleDeposit()                      │  │
│  │       ▼                                                     │  │
│  │ CustomOmnipayPaymentGateway::afterDepositConfirmed()        │  │
│  │       │ DepositClassificationService::classify()            │  │
│  │       │   1. checkMerchantMetadata (95%+ confidence)        │  │
│  │       │   2. matchByAmountAndTime (80% confidence)          │  │
│  │       │   3. matchBySenderAndAmount / FIFO (70% confidence) │  │
│  │       │                                                     │  │
│  │       ├─ type=payment → creates UNCONFIRMED transfer        │  │
│  │       │   System wallet → Voucher cash wallet               │  │
│  │       │   deposit.confirmed = false (held until payer ACK)  │  │
│  │       │   stores transaction_uuid in PaymentRequest.meta    │  │
│  │       │                                                     │  │
│  │       │ fires PaymentDetectedButNotConfirmed event           │  │
│  │       │       ▼                                             │  │
│  │       │ SendPaymentConfirmationSms job (queued)             │  │
│  │       │   sends SMS with signed URL (24h expiry)            │  │
│  │       │                                                     │  │
│  │       │ Payer clicks signed URL in SMS                      │  │
│  │       │       ▼                                             │  │
│  │       │ GET /pay/confirm/{paymentRequest} (signed)          │  │
│  │       │ ConfirmPaymentViaSms action                         │  │
│  │       │   confirms unconfirmed transaction                  │  │
│  │       │   (voucher cash wallet credited)                    │  │
│  │       │   marks PaymentRequest: awaiting_confirmation       │  │
│  │       │       ▼                                             │  │
│  │       │ redirect → PaymentConfirmed.vue                     │  │
│  │       │                                                     │  │
│  │       └─ type=topup → normal top-up flow (not pay)          │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌─ PATH B: Direct Checkout webhook ───────────────────────────┐  │
│  │ POST /webhooks/netbank/payment                              │  │
│  │       ▼                                                     │  │
│  │ NetBankWebhookController::handleVoucherPayment()            │  │
│  │   validates voucher exists + canAcceptPayment()             │  │
│  │   idempotency check via payment_id in tx meta               │  │
│  │   DB::transaction:                                          │  │
│  │     deposits amountInCents to cash wallet (CONFIRMED)       │  │
│  │     meta: { flow: 'pay', payment_id, gateway: 'netbank' }  │  │
│  │     auto-closes voucher if remaining ≤ ₱0.01               │  │
│  │     state → CLOSED, closed_at = now()                       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                   │
└───────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Code Entry

**Route**: `GET /pay` → `app/Http/Controllers/Pay/PayVoucherController.php::index()`
**Vue**: `resources/js/pages/pay/Index.vue` → wraps `resources/js/components/PayWidget.vue`

### Feature Gate

`PayVoucherController::index()` checks:
```
enabled = app()->environment('local', 'staging') || config('pay.enabled', false)
```
If disabled, returns 404. No per-user feature flag (payers are unauthenticated).

### OG Meta

Route uses `og-meta:pay` middleware which resolves via `App\OgResolvers\PayVoucherOgResolver`:

- **Landing page** (no `?code`): title "Pay here", subtitle "Scan to pay", generates QR for `/pay` URL
- **With `?code=X`**: resolves voucher, shows code as headline, formatted target amount as subtitle, status-dependent title (active/redeemed/expired), type and payee badges
- **Cache TTL**: 10 min (active), 7 days (redeemed/expired)

### PayWidget.vue

`PayWidget.vue` is the `/pay` equivalent of `RedeemWidget.vue`. A single code-input form that:
1. Reads config from `page.props.pay.widget` (backend `config/pay.php`) with prop fallbacks
2. On submit: `POST /pay/quote` with `{ code }` (uppercased, trimmed)
3. On success: emits `quote-loaded` event with full quote data to parent

Configurable elements: logo, app name, title, description, label, placeholder, button text. All controllable via `PAY_WIDGET_*` env vars.

### Quote Endpoint

**Route**: `POST /pay/quote` → `PayVoucherController::quote()`

Validates the voucher code, checks `canAcceptPayment()` (must be PAYABLE or SETTLEMENT type, ACTIVE state, not expired, not closed), then computes:

- `target_amount` — stored in minor units, accessor converts to major (centavos → pesos)
- `paid_total` — sum of confirmed `deposit` transactions where `meta->flow = 'pay'`
- `remaining` — `target_amount - paid_total`
- `min_amount` / `max_amount` — from `voucher.rules` or defaults (₱1 / remaining)
- `external_metadata` — prefers envelope payload, falls back to voucher `external_metadata`
- `attachments` — prefers envelope documents, falls back to voucher `voucher_attachments` media

**Key files (Phase 1)**:
- `routes/pay.php` — route definitions
- `app/Http/Controllers/Pay/PayVoucherController.php` — controller
- `resources/js/pages/pay/Index.vue` — page component
- `resources/js/components/PayWidget.vue` — code entry widget
- `app/OgResolvers/PayVoucherOgResolver.php` — OG meta resolver
- `config/pay.php` — feature toggle + widget config

---

## Phase 2: QR Generation & Display

**Ownership**: All in host app (no form-flow package). The `pay/Index.vue` page manages steps 2-3 via reactive refs (`quote`, `showQrStep`, `paymentQr`, `paymentMarkedDone`).

### Step 2: Payment Details

Rendered inline in `pay/Index.vue` when `quote` is set and `showQrStep` is false.

**UI elements**:
- Summary card: voucher code, type, target amount, paid total, remaining (blue bold)
- External metadata: collapsible JSON dump (hidden by default, `ChevronDown` toggle)
- Attachments: clickable file links with `FileText` icon, download button, size display
- Amount input: `<input type="number">` with min/max from quote, defaults to remaining
- "Generate QR" button → calls `generatePaymentQR()`
- "Back" button → `resetFlow()` returns to code entry

### GeneratePaymentQr Action

**Route**: `POST /api/v1/pay/generate-qr` → `app/Actions/Api/Pay/GeneratePaymentQr.php`

1. Validates voucher exists, has owner, `canAcceptPayment()`, amount ≤ remaining
2. Resolves owner's `accountNumber` and merchant profile (`getOrCreateMerchant()`)
3. Checks cache (`payment_qr:{code}:{amount}`, 5 min TTL)
4. Calls `PaymentGatewayInterface::generate($account, $money, $merchantData)` — produces InstaPay QR
5. Creates `PaymentRequest` record:
   - `reference_id`: `"PAYMENT-QR-{uniqid()}"`
   - `amount`: stored in **minor units** (cents) — `$amountValue * 100`
   - `status`: `pending`
6. Returns: `qr_code` (data URL), `qr_id`, `account`, `amount`, `payment_request_id`, `expires_at` (+5 min)

### Step 3: QR Code Display

Rendered in `pay/Index.vue` when `showQrStep` is true.

**UI elements**:
- Blue info banner: "Scan this QR code to make a payment of {amount}"
- `QrDisplay.vue` — shared component with loading/error/empty states
- "Download QR Code" button → `useQrShare().downloadQr()`
- Mobile usage instructions (blue info card): "Download QR, upload in GCash/Maya"
- QR ID display + expiration time
- **"Payment Done" button** — amber card with green button, `animate-pulse`, prominent CTA
  - Calls `markPaymentDone()` → `POST /api/v1/pay/mark-done`
  - On success: replaces with green "Payment marked as done" message
- "Back" / "New Payment" buttons

### MarkPaymentDone Action

**Route**: `POST /api/v1/pay/mark-done` → `app/Actions/Api/Pay/MarkPaymentDone.php`

1. Validates `payment_request_id` exists in DB
2. Guards: only `pending` status allowed
3. Updates status to `awaiting_confirmation`
4. Optionally merges `payer_info` (name, mobile)

---

## Phase 3: Async Payment Confirmation

Two independent payment confirmation paths exist. Both result in funds credited to the voucher's cash wallet, but via different mechanisms.

### Path A: InstaPay QR → Deposit Classification → SMS Confirmation

This path handles incoming bank deposits to the system account (from QR code scans).

**Trigger**: Omnipay gateway processes a deposit notification from the bank.

**Flow**:

1. `OmnipayPaymentGateway::handleDeposit()` processes the incoming deposit notification
2. `CustomOmnipayPaymentGateway::afterDepositConfirmed()` fires after the deposit hits the system account
3. `DepositClassificationService::classify()` determines if it's a voucher payment or top-up (see [Deposit Classification Deep-Dive](#deposit-classification-deep-dive))
4. If `type=payment`: creates an **UNCONFIRMED** transfer:
   - Source: System wallet (withdraw, **confirmed**)
   - Target: Voucher cash wallet (deposit, **unconfirmed** — held until payer acknowledges)
   - Stores `transaction_uuid` in `PaymentRequest.meta` for later confirmation
5. Fires `PaymentDetectedButNotConfirmed` event
6. Event listener dispatches `SendPaymentConfirmationSms` job (queued, 3 retries with backoff 10s/30s/60s)
7. Job sends SMS via EngageSpark with a **signed URL** (24-hour expiry):
   ```
   "Payment received! ₱{amount} for voucher {code}. Confirm here: {signed_url}"
   ```
   Template from `lang/en/notifications.php` key `payment_confirmation.sms`.
8. Payer clicks the signed URL
9. `GET /pay/confirm/{paymentRequest}` → `ConfirmPaymentViaSms` action:
   - Finds the unconfirmed transaction by `PaymentRequest.meta.transaction_uuid`
   - Safety check: verifies transaction belongs to voucher's cash wallet
   - Calls `$voucher->cash->confirm($transaction)` — **credits the voucher wallet**
   - Marks PaymentRequest as `awaiting_confirmation`
   - Persists a `PaymentConfirmationNotification` on the PaymentRequest model (audit trail)
10. Redirects to `GET /pay/confirmed/{paymentRequest}` → `PaymentConfirmed.vue`

**Important**: Path A uses a **two-phase commit** — the deposit is unconfirmed until the payer clicks the SMS link. This prevents crediting funds before the payer acknowledges.

### Path B: Direct Checkout Webhook

This path handles Direct Checkout payment notifications from NetBank (reference-based matching).

**Trigger**: `POST /webhooks/netbank/payment` — NetBank sends a payment notification.

**Flow**:

1. `NetBankWebhookController::handlePayment()` receives the webhook
2. Detects voucher payment (reference_no doesn't start with `TOPUP-`) + settlement feature active
3. Routes to `handleVoucherPayment()`:
   - Finds voucher by code (`reference_no` = voucher code)
   - Validates `canAcceptPayment()`
   - Idempotency: checks for existing transaction with same `payment_id` in meta
   - `DB::transaction()`:
     - Deposits amount to voucher cash wallet (**confirmed immediately**)
     - Meta: `{ flow: 'pay', payment_id, gateway: 'netbank', type: 'voucher_payment' }`
     - **Auto-close**: if `remaining ≤ ₱0.01`, updates `state → CLOSED`, `closed_at = now()`
   - Returns success with `paid_total`, `remaining`, `auto_closed` flag

**Important**: Path B deposits are **immediately confirmed** — no two-phase commit. The bank webhook is the authoritative confirmation.

### PaymentConfirmed.vue

**Route**: `GET /pay/confirmed/{paymentRequest}`
**Vue**: `resources/js/pages/PaymentConfirmed.vue`

Static success page showing:
- Green checkmark icon
- "Payment Confirmed!" heading
- Amount (formatted PHP currency), voucher code, confirmed timestamp
- "What happens next?" info card
- Transaction ID: `{voucherCode}-{timestamp}`

---

## PaymentRequest Lifecycle

**Model**: `app/Models/PaymentRequest.php`
**Table**: `payment_requests`

### Status Machine

```
                   ┌─────────┐
                   │ pending  │ ← created by GeneratePaymentQr
                   └────┬─────┘
                        │
          ┌─────────────┼─────────────┐
          │             │             │
          ▼             ▼             ▼
  MarkPaymentDone  SMS Confirm   (expires/abandoned)
          │             │
          ▼             ▼
  ┌───────────────────────────┐
  │  awaiting_confirmation    │
  └───────────┬───────────────┘
              │
              ▼
       ┌────────────┐
       │  confirmed  │ ← owner final confirmation (future)
       └────────────┘
```

**Status values**:
- `pending` — QR generated, waiting for payer to complete bank transfer
- `awaiting_confirmation` — payer signaled payment done (via button or SMS link)
- `confirmed` — voucher owner confirmed receipt (via `markAsConfirmed()`)

### Amount Storage

Amounts stored in **minor units** (centavos): `$amountValue * 100`.
Accessor `getAmountInMajorUnits()` returns `$this->amount / 100` for display.

### Route Model Binding

Uses `reference_id` as route key (`getRouteKeyName()`), not `id`. This gives cleaner URLs for signed routes: `/pay/confirm/PAYMENT-QR-ABC123` instead of `/pay/confirm/42`.

### Key Fields

| Field | Type | Description |
|-------|------|-------------|
| `reference_id` | string (unique) | `"PAYMENT-QR-{uniqid()}"` — URL-safe identifier |
| `voucher_id` | FK | Links to voucher being paid |
| `amount` | integer | Payment amount in **centavos** |
| `currency` | string | Default `PHP` |
| `status` | string | pending / awaiting_confirmation / confirmed |
| `payer_info` | JSON | Optional: `{ name, mobile }` from mark-done |
| `meta` | JSON | `{ transaction_uuid, transfer_uuid }` from deposit classification |
| `confirmed_at` | datetime | Set when owner confirms |

---

## Deposit Classification Deep-Dive

**Service**: `app/Services/DepositClassificationService.php`

When a bank deposit arrives, the system must determine: is this a voucher payment or a regular top-up? Three strategies run in priority order:

### Strategy 1: Merchant Metadata (95%+ confidence)

Checks `deposit.merchant_details.payment_request_reference` for a matching `PaymentRequest.reference_id`. This is the most reliable method — the bank echoes back the reference embedded in the QR code.

### Strategy 2: Amount + Time (80% confidence)

Finds a `pending` PaymentRequest with exact `amount` (in cents) created in the last **10 minutes**. Confidence degrades to `medium` if multiple pending requests share the same amount.

### Strategy 3: FIFO by Amount (70% confidence)

Same as Strategy 2 but extends window to **30 minutes** and uses FIFO ordering (oldest pending first). Lowest confidence — fallback only.

### Classification Outcome

| Result | Action |
|--------|--------|
| `type=payment` + model found | Creates unconfirmed transfer, fires SMS event |
| `type=topup` | Normal top-up handling (no voucher involvement) |
| No match | Logged as top-up, no payment classification |

### Architectural Note

The classification happens inside `CustomOmnipayPaymentGateway::afterDepositConfirmed()` — a host-app override of the package gateway. Errors are caught and logged but **never fail the deposit** — the system wallet credit always succeeds regardless of classification outcome.

---

## Wallet Ledger Mechanics

### Path A: QR/InstaPay (Two-Phase)

```
1. Deposit arrives → System wallet credited (confirmed)
2. Classification matches PaymentRequest
3. Transfer: System → Voucher cash wallet
   - System withdraw: confirmed = true
   - Voucher deposit:  confirmed = false ← HELD
4. PaymentRequest.meta.transaction_uuid = deposit.uuid
5. Payer clicks SMS link → ConfirmPaymentViaSms
6. $voucher->cash->confirm($transaction)
   - Voucher deposit: confirmed = true ← RELEASED
7. getPaidTotal() now includes this deposit
   (counts only confirmed deposits where meta->flow = 'pay')
```

### Path B: Direct Checkout

```
1. Webhook arrives with payment_status=PAID
2. Direct deposit to voucher cash wallet
   - confirmed = true (immediate)
   - meta: { flow: 'pay', payment_id: '...' }
3. getPaidTotal() includes immediately
4. If remaining ≤ ₱0.01 → auto-close (state=CLOSED)
```

### Auto-Close Behavior

Only Path B (Direct Checkout) auto-closes vouchers. Path A does not check remaining after confirmation — the voucher stays ACTIVE even if fully paid via QR/InstaPay. This is a known gap.

---

## Key Files Reference

### Routes & Controllers (host app)

| Route | Method | Controller/Action | Vue Page |
|-------|--------|-------------------|----------|
| `GET /pay` | `index()` | `app/Http/Controllers/Pay/PayVoucherController.php` | `resources/js/pages/pay/Index.vue` |
| `POST /pay/quote` | `quote()` | same | — (JSON response) |
| `POST /pay/qr` | `generateQr()` | same | — (JSON, **TODO: stub**) |
| `POST /api/v1/pay/generate-qr` | `__invoke()` | `app/Actions/Api/Pay/GeneratePaymentQr.php` | — (JSON response) |
| `POST /api/v1/pay/mark-done` | `__invoke()` | `app/Actions/Api/Pay/MarkPaymentDone.php` | — (JSON response) |
| `GET /pay/confirm/{paymentRequest}` | `__invoke()` | `app/Actions/Pay/ConfirmPaymentViaSms.php` | — (redirect) |
| `GET /pay/confirmed/{paymentRequest}` | closure | `routes/web.php:57` | `resources/js/pages/PaymentConfirmed.vue` |
| `POST /webhooks/netbank/payment` | `handlePayment()` | `app/Http/Controllers/Webhooks/NetBankWebhookController.php` | — (JSON) |
| `GET /api/v1/vouchers/{code}/payments` | `__invoke()` | `app/Actions/Api/Vouchers/GetPaymentHistory.php` | — (JSON) |

### Vue Components

| Component | Location | Role |
|-----------|----------|------|
| `pay/Index.vue` | `resources/js/pages/pay/Index.vue` | Main page — 3-step wizard |
| `PayWidget.vue` | `resources/js/components/PayWidget.vue` | Code entry form (like RedeemWidget) |
| `PaymentConfirmed.vue` | `resources/js/pages/PaymentConfirmed.vue` | Post-confirmation success page |
| `QrDisplay.vue` | `resources/js/components/shared/QrDisplay.vue` | Shared QR renderer (loading/error/image) |

### Models & Events

| File | Role |
|------|------|
| `app/Models/PaymentRequest.php` | Payment request tracking (status machine, amount in cents) |
| `app/Events/PaymentDetectedButNotConfirmed.php` | Fired when deposit classified as payment |
| `app/Jobs/SendPaymentConfirmationSms.php` | Queued SMS with signed confirmation URL |
| `app/Notifications/PaymentConfirmationNotification.php` | SMS content via EngageSpark + database audit |
| `app/Services/DepositClassificationService.php` | 3-strategy deposit → payment matching |
| `app/Gateways/CustomOmnipayPaymentGateway.php` | Host-app gateway override (classification + unconfirmed transfer) |

### OG Meta

| File | Role |
|------|------|
| `app/OgResolvers/PayVoucherOgResolver.php` | OG meta for `/pay` (landing + per-voucher) |
| `config/og-meta.php` | Resolver registration (`'pay' => PayVoucherOgResolver::class`) |

---

## Config & Environment Variables

### Feature Toggle

| Variable | Default | Description |
|----------|---------|-------------|
| `PAY_ENABLED` | `true` | Enable/disable `/pay` routes globally |

In `local`/`staging`, pay is always enabled regardless of this flag.

### Widget Configuration (`config/pay.php`)

| Variable | Default | Description |
|----------|---------|-------------|
| `PAY_WIDGET_SHOW_LOGO` | `true` | Show app logo icon |
| `PAY_WIDGET_SHOW_APP_NAME` | `false` | Show app name text |
| `PAY_WIDGET_SHOW_LABEL` | `true` | Show "code" label |
| `PAY_WIDGET_SHOW_TITLE` | `false` | Show "Pay Voucher" title |
| `PAY_WIDGET_SHOW_DESCRIPTION` | `true` | Show description text |
| `PAY_WIDGET_TITLE` | `"Pay Voucher"` | Title text |
| `PAY_WIDGET_DESCRIPTION` | `null` | Description text |
| `PAY_WIDGET_LABEL` | `"code"` | Input label |
| `PAY_WIDGET_PLACEHOLDER` | `"x x x x"` | Input placeholder |
| `PAY_WIDGET_BUTTON_TEXT` | `"pay"` | Submit button text |
| `PAY_WIDGET_BUTTON_PROCESSING_TEXT` | `"Checking..."` | Button text while loading |

### Gateway (for QR generation)

QR generation uses the same `PaymentGatewayInterface` as wallet/disbursement. Key env vars:

| Variable | Description |
|----------|-------------|
| `USE_OMNIPAY` | `true`/`false` — gateway implementation switch |
| `NETBANK_*` | NetBank API credentials (see WARP.md → Payment Gateway) |

---

## Key Differences from /disburse

| Aspect | /disburse | /pay |
|--------|-----------|------|
| **Package involvement** | form-flow package (YAML driver, handlers) | None — all host app |
| **Step management** | Multi-page via FormFlowController | Single page, reactive refs |
| **Data collection** | 0-7 conditional steps (wallet, KYC, bio, etc.) | Code + amount only |
| **Payment direction** | System → Redeemer (disbursement) | Payer → Voucher (collection) |
| **Confirmation** | Synchronous (redemption pipeline) | Async (webhook + SMS loop) |
| **Wallet operation** | Withdraw from cash wallet | Deposit to cash wallet |
| **Voucher types** | REDEEMABLE, SETTLEMENT | PAYABLE, SETTLEMENT |
| **Terminal state** | Redeemed (`redeemed_at` set) | Closed (`state=CLOSED`) |
| **Authentication** | Public (redeemer) | Public (payer) |

---

## Known TODOs and Gaps

1. **`POST /pay/qr` route is a stub** — `PayVoucherController::generateQr()` returns mock data. Real QR generation uses the API route `POST /api/v1/pay/generate-qr` instead. The web route is unused.

2. **No auto-close on Path A** — QR/InstaPay payments confirmed via SMS do not check if the voucher is fully paid. Only Direct Checkout (Path B) auto-closes.

3. **No payment history in UI** — `GET /api/v1/vouchers/{code}/payments` API exists but is not consumed by the `/pay` frontend. Payers cannot see prior payments.

4. **External metadata displayed as raw JSON** — The `quote.external_metadata` is rendered as a collapsible `<pre><code>` dump. No structured/human-friendly rendering.

5. **No real-time status polling** — After marking payment done, the UI shows a static confirmation. No polling for backend confirmation status (unlike the disburse flow's success page).

6. **Hardcoded styles** — `pay/Index.vue` uses hardcoded Tailwind classes (gray, blue, green, amber) instead of CSS variable-based theme classes. Not aligned with the amber theme system used in `/disburse`.

---

## Related Documentation

- Disburse flow map: `docs/guides/features/DISBURSE_FLOW_MAP.md`
- Settlement envelope architecture: `docs/architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md`
- Notification templates: `docs/NOTIFICATION_TEMPLATES.md`
- Omnipay integration: `docs/OMNIPAY_INTEGRATION_PLAN.md`
- Payment gateway config: see `WARP.md` → Payment Gateway Configuration
