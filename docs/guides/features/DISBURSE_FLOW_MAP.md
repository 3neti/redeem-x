# /disburse Endpoint — UI Rendering Flow Map

**Version**: 1.1
**Last Updated**: 2025-03-13
**Audience**: Human developers + AI agents (Claude Code, Warp Oz, Junie)

This document maps the complete UI rendering sequence for the `/disburse` endpoint — from code entry to success page, including the post-redemption pipeline, events, listeners, and rollback behavior.

> **For form-flow package internals**, see `docs/guides/features/form-flow/`.
> **For env var reference**, see `docs/guides/features/form-flow/ENV_VARS.md`.
> **For pay flow comparison**, see `docs/guides/features/PAY_FLOW_MAP.md`.

---

## Flow Diagram

```
┌─────────────────────── PHASE 1: HOST APP ───────────────────────┐
│                                                                 │
│  disburse/Start.vue ──▶ RedeemWidget.vue                        │
│       │                    (code input form)                    │
│       │ GET /disburse?code=X                                    │
│       ▼                                                         │
│  DisburseController::start()                                    │
│       │ validates voucher                                       │
│       │ calls initiateFlow()                                    │
│       │   1. DriverService::buildContext(voucher)               │
│       │   2. DriverService::transform() via YAML driver         │
│       │   3. FormFlowService::startFlow()                       │
│       ▼                                                         │
│  redirect to /form-flow/{flow_id}                               │
│                                                                 │
└──────────────────────────────┬──────────────────────────────────┘
                               │
┌──────────────────── PHASE 2: FORM-FLOW PACKAGE ─────────────────┐
│                              │                                  │
│  FormFlowController::show() ◀┘                                  │
│       │ resolves handler for current step                       │
│       │ calls handler->render() → Inertia page                  │
│       ▼                                                         │
│  ┌─ Steps (conditional, from YAML driver) ──────────────────┐   │
│  │ 0. Splash.vue .............. (if splash_enabled)         │   │
│  │ 1. GenericForm.vue ......... wallet_info (always)        │   │
│  │ 2. KYCInitiatePage.vue ..... (if has_kyc)                │   │
│  │    └─ KYCStatusPage.vue .... (polls until approved)      │   │
│  │ 3. GenericForm.vue ......... bio_fields (if any bio)     │   │
│  │ 4. OtpCapturePage.vue ...... (if has_otp)                │   │
│  │ 5. LocationCapturePage.vue . (if has_location)           │   │
│  │ 6. SelfieCapturePage.vue ... (if has_selfie)             │   │
│  │ 7. SignatureCapturePage.vue  (if has_signature)          │   │
│  └──────────────────────────────────────────────────────────┘   │
│       │ each step: POST /form-flow/{flow_id}/step/{index}       │
│       │ when all done:                                          │
│       ▼                                                         │
│  Complete.vue (summary + confirm button)                        │
│       │ triggers on_complete callback                           │
│       │ POST /disburse/{code}/complete                          │
│                                                                 │
└──────────────────────────────┬──────────────────────────────────┘
                               │
┌─────────────────────── PHASE 3: HOST APP ───────────────────────┐
│                               │                                 │
│  DisburseController::complete() ◀┘                              │
│       │ fires FormFlowCompleted event                           │
│       │ stores collected data in session                        │
│       │                                                         │
│  Complete.vue confirm button                                    │
│       │ POST /disburse/{code}/redeem                            │
│       ▼                                                         │
│  DisburseController::redeem()                                   │
│       │ retrieves collected data from session                   │
│       │ VoucherRedemptionService::redeem()                      │
│       │   → ProcessRedemption::handle()                         │
│       │     → DB::transaction()                                 │
│       │       → RedeemVoucher::run()                            │
│       │         → VoucherObserver::redeemed()                   │
│       │           → HandleRedeemedVoucher (pipeline)            │
│       │                                                         │
│       │ ┌─ Post-Redemption Pipeline ─────────────────────┐      │
│       │ │ 1. ValidateRedeemerAndCash (package)           │      │
│       │ │ 2. PersistInputs (host)                        │      │
│       │ │ 3. ClearOgMetaCache (host)                     │      │
│       │ │ 4. SyncEnvelopeData (host) → async job         │      │
│       │ │ 5. DisburseCash (package, if enabled)          │      │
│       │ │ 6. SendFeedbacks (host)                        │      │
│       │ └────────────────────────────────────────────────┘      │
│       │ fires DisbursementRequested event                       │
│       ▼                                                         │
│  redirect to /disburse/{code}/success                           │
│       │                                                         │
│  disburse/Success.vue                                           │
│       │ shows rider message (markdown/HTML/SVG/URL/text)        │
│       │ countdown redirect to rider URL                         │
│       ▼                                                         │
│  DisburseSuccessRedirectController                              │
│       └─ GET /disburse/{code}/redirect → rider URL or fallback  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Host App — Code Entry

**Route**: `GET /disburse` → `app/Http/Controllers/Disburse/DisburseController.php::start()`
**Vue**: `resources/js/pages/disburse/Start.vue` → wraps `resources/js/components/RedeemWidget.vue`

### Theme Initialization

`Start.vue` calls `initializeTheme()` from `resources/js/composables/useTheme.ts` on mount. This reads the saved theme from `localStorage` (`pwa-theme` key) and applies the corresponding `theme-{id}` class to `<html>`. Three themes are available: **default** (neutral), **steampunk** (brass & parchment), **amber** (sunlit gold). Theme choice persists across sessions.

All disburse flow pages (`Start.vue`, `Splash.vue`, `GenericForm.vue`, `Complete.vue`, `Success.vue`, `RedeemWidget.vue`) use CSS variable-based theme-aware classes (e.g., `bg-primary/5`, `text-primary`, `border-primary/20`) instead of hardcoded color classes. The theme picker is accessible in `RedeemWidget.vue` under the System Info debug tab (Palette icon).

### Code Entry & Voucher Preview

`RedeemWidget.vue` renders a single code-input form with live voucher preview (via `useVoucherPreview` composable, 500ms debounce, minimum 4 characters). On submit it fires `GET /disburse?code=X`.

### Non-Active Voucher State

When the voucher preview returns a **redeemed** or **expired** status, `RedeemWidget.vue` switches to a compact non-active state designed to fit within iMessage link preview fold:

- Logo, title, code input, and submit button are **hidden** (`v-if="!isNonActive"`)
- `VoucherStatusStamp.vue` renders: status icon (checkmark/clock), formatted amount, voucher code badge, and human-readable relative date ("3 hours ago" for <7 days, absolute date for older)
- Tilted passport-style stamp overlay with status text ("REDEEMED" / "EXPIRED")
- For **returning redeemers** (detected via `form_flow_persist_wallet_info` in localStorage): shows rider splash content (rendered as markdown/HTML/SVG) and OG preview card
- `OgPreviewCard.vue` displays: OG image (2.4:1 aspect crop), title, single-line description, domain with favicon
- All shown in tighter spacing (`space-y-2.5`, compact padding) for mobile/embed contexts

**Key new components**:
- `resources/js/components/voucher/VoucherStatusStamp.vue` — status stamp with relative time
- `resources/js/components/voucher/OgPreviewCard.vue` — link preview card from OG meta
- `resources/js/composables/useTheme.ts` — theme management (3 themes, localStorage persistence)

### Flow Initiation

`DisburseController::start()` receives the code, validates the voucher exists and is redeemable, then calls `initiateFlow()`:

1. **Build context** — `DriverService::buildContext($voucher)` reads `VoucherInstructionsData` and produces template variables (see [Context Building](#context-building))
2. **Transform** — `DriverService::transform()` applies the YAML driver at `config/form-flow-drivers/voucher-redemption.yaml`, evaluates step conditions, processes templates
3. **Start flow** — `FormFlowService::startFlow()` creates a session-based flow with a unique `flow_id`
4. **Redirect** — returns `redirect("/form-flow/{flow_id}")`

**Key files (host app)**:
- `routes/disburse.php` — route definitions
- `app/Http/Controllers/Disburse/DisburseController.php` — controller
- `resources/js/pages/disburse/Start.vue` — code entry page (calls `initializeTheme()`)
- `resources/js/components/RedeemWidget.vue` — reusable code input widget (theme-aware, non-active state)
- `resources/js/components/voucher/VoucherStatusStamp.vue` — non-active voucher status stamp
- `resources/js/components/voucher/OgPreviewCard.vue` — OG meta link preview card
- `resources/js/composables/useTheme.ts` — theme management composable
- `config/form-flow-drivers/voucher-redemption.yaml` — YAML driver (host-app config)

---

## Phase 2: Form-Flow Package — Multi-Step Data Collection

**Ownership**: `3neti/form-flow` package. Host app has no control during this phase.

**Route**: `GET /form-flow/{flow_id}` → `FormFlowController::show()` (vendor package)

The controller resolves the current step's handler, calls `handler->render()`, which returns an Inertia page. Each step submission goes to `POST /form-flow/{flow_id}/step/{index}` → validates → advances.

When all steps are done, the controller:
1. Triggers the `on_complete` callback (`POST /disburse/{code}/complete`)
2. Renders `resources/js/pages/form-flow/core/Complete.vue`

**Vue pages** (published to host app at `resources/js/pages/form-flow/`):

- `core/Splash.vue` — splash/welcome screen (theme-aware: uses CSS variable classes)
- `core/GenericForm.vue` — dynamic form, wallet & bio steps (theme-aware: hero field, badges, cards all use `bg-primary/*`, `text-primary`, `border-primary/*` instead of hardcoded amber)
- `core/Complete.vue` — summary + confirm button (theme-aware)
- `core/MissingHandler.vue` — fallback for uninstalled handler packages

**Note**: `Splash.vue`, `GenericForm.vue`, and `Complete.vue` are published from the `3neti/form-flow` package (v1.7.11+). After editing these files in the host app, stubs must be synced back to the package. See `docs/guides/ai-development/FORM_FLOW_UI_UPDATE_SOP.md`.
- `kyc/KYCInitiatePage.vue` — starts HyperVerge flow
- `kyc/KYCStatusPage.vue` — polls KYC result
- `otp/OtpCapturePage.vue` — SMS code entry
- `location/LocationCapturePage.vue` — GPS + map capture
- `selfie/SelfieCapturePage.vue` — front camera capture
- `signature/SignatureCapturePage.vue` — drawing pad

---

## Phase 2→3 Handoff: Callback + Confirmation

1. `on_complete` callback POSTs to `DisburseController::complete()` — fires `App\Events\FormFlowCompleted` event, stores collected data in session
2. `Complete.vue` displays a summary of all collected data (grouped by step), user reviews
3. User clicks "Confirm & Process" → POSTs to `/disburse/{code}/redeem` (back to host app)

`Complete.vue` detects it's a disburse flow by checking `reference_id.startsWith('disburse-')`, extracts the voucher code from the reference ID pattern `disburse-{CODE}-{timestamp}`.

---

## Phase 3: Host App — Redemption + Success

### Redemption

**Route**: `POST /disburse/{code}/redeem` → `DisburseController::redeem()`

1. Retrieves collected data from session using `flow_id` and `reference_id`
2. Maps collected data to contact/redemption format (mobile, inputs, bank_account)
3. Calls `app/Services/VoucherRedemptionService.php::redeem()`
4. `VoucherRedemptionService` runs the `RedemptionGuard` with these specifications (in order):
   - `SecretSpecification` — validates secret code if voucher requires one
   - `MobileSpecification` — validates mobile restriction if voucher is restricted
   - `PayableSpecification` — validates merchant payee if voucher is payable
   - `InputsSpecification` — validates all required input fields are present
   - `KycSpecification` — validates KYC approval if required
   - `LocationSpecification` — validates GPS proximity if location validation is configured
   - `TimeWindowSpecification` — validates time-of-day window if configured
   - `TimeLimitSpecification` — validates redemption duration limit if configured
5. On validation pass, calls `app/Actions/Voucher/ProcessRedemption.php::handle()`
6. On success, redirects to `/disburse/{code}/success`

### Post-Redemption Pipeline

The pipeline fires **synchronously inside `DB::transaction()`** via the Eloquent observer chain:

```
ProcessRedemption::handle()
  → DB::transaction()
    → RedeemVoucher::run()                              [monorepo-packages/voucher/]
      → Vouchers::redeem() (FrittenKeeZ package)
        → Eloquent save
          → VoucherObserver::redeemed()                  [monorepo-packages/voucher/]
            → HandleRedeemedVoucher::handle()            [monorepo-packages/voucher/]
              → Laravel Pipeline with config('voucher-pipeline.post-redemption')
```

**Pipeline stages** (defined in `config/voucher-pipeline.php` `post-redemption` key):

| # | Stage | Owner | File | What it does |
|---|-------|-------|------|-------------|
| 1 | ValidateRedeemerAndCash | package | `monorepo-packages/voucher/src/Pipelines/RedeemedVoucher/ValidateRedeemerAndCash.php` | Ensures voucher has a Contact redeemer and Cash entity. Returns `null` to abort pipeline if missing. |
| 2 | PersistInputs | host | `app/Pipelines/RedeemedVoucher/PersistInputs.php` | Saves form-flow collected data to voucher inputs table via `forceSetInput()`. Normalizes location arrays (`{lat, lng}` → `latitude`, `longitude`) and KYC arrays (prefixes with `kyc_`). |
| 3 | ClearOgMetaCache | host | `app/Pipelines/RedeemedVoucher/ClearOgMetaCache.php` | Deletes cached OG meta images (`{og_prefix}/disburse/{code}-*.png`) so social cards reflect redeemed status. Non-blocking. |
| 4 | SyncEnvelopeData | host | `app/Pipelines/RedeemedVoucher/SyncEnvelopeData.php` | If voucher has a settlement envelope, dispatches `app/Jobs/SyncEnvelopeAndAttachImages.php` on the `high` queue. Syncs form-flow data to envelope payload and attaches KYC/map images. |
| 5 | DisburseCash | package | `monorepo-packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php` | **Conditional**: only included when `DISBURSE_DISABLE=false`. Calls payment gateway to transfer funds. On success: withdraws from cash wallet via `WithdrawCash::run()`, stores disbursement metadata on voucher. On failure: catches exception, records `pending` status, fires `DisbursementFailed`, pipeline **continues**. |
| 6 | SendFeedbacks | host | `app/Pipelines/RedeemedVoucher/SendFeedbacks.php` | Sends notifications via configured feedback channels: `mail` (email), `engage_spark` (SMS), `webhook`. Routes resolved from `$voucher->instructions->feedback`. Also notifies voucher owner for audit copy. |

### Events and Listeners

**On pipeline success** — `HandleRedeemedVoucher` fires after pipeline completes:

| Event | Class | Listener | File | Behavior |
|-------|-------|----------|------|----------|
| `DisbursementRequested` | `LBHurtado\Voucher\Events\DisbursementRequested` | `UpdateContactKycStatus` | `app/Listeners/UpdateContactKycStatus.php` | Queued. If voucher inputs contain KYC data (`transaction_id` + `status` = `approved`), persists approval to Contact model (`kyc_status`, `kyc_completed_at`, `kyc_transaction_id`). |

**On disbursement failure** — fired from `DisburseCash` or `HandleRedeemedVoucher`:

| Event | Class | Listener | File | Behavior |
|-------|-------|----------|------|----------|
| `DisbursementFailed` | `LBHurtado\Wallet\Events\DisbursementFailed` | `NotifyAdminOfDisbursementFailure` | `app/Listeners/NotifyAdminOfDisbursementFailure.php` | Sends `DisbursementFailedNotification` to system user + admin-role users + config emails (`DISBURSEMENT_ALERT_EMAILS`). Throttled per error type: 30-minute cooldown (`DISBURSEMENT_ALERT_THROTTLE_MINUTES`). |

Event listeners are registered in `app/Providers/AppServiceProvider.php::boot()`.

### Rollback Behavior

The entire redemption is wrapped in `DB::transaction()`. Rollback depends on the failure type:

| Failure | What happens | Voucher state |
|---------|-------------|---------------|
| **Gateway timeout/error** | Caught by `DisburseCash`. Disbursement recorded as `pending` with `requires_reconciliation: true`. Pipeline continues. Transaction commits. | **Redeemed** (stands) |
| **EMI + PESONET mismatch** | `InvalidSettlementRailException` thrown by `DisburseCash`. `DisbursementFailed` fired. Exception rethrown by `HandleRedeemedVoucher`. Transaction rolls back. | **Unredeemed** (reverted) |
| **Missing contact/cash** | `ValidateRedeemerAndCash` returns `null`. Pipeline stops. `DisbursementRequested` not fired. No exception. | **Redeemed** (stands, but no disbursement) |
| **KYC validation failure** | `ProcessRedemption::validateKYC()` throws `RuntimeException` before `RedeemVoucher::run()`. Transaction rolls back. | **Unredeemed** (reverted) |

**Design principle**: "Redemption is sacred" — bank/gateway failures do NOT revert the user's redemption. Only pre-redemption validation errors (KYC, EMI rail) cause rollback.

### Bank Disbursement Deep-Dive

#### Design Philosophy

Bank disbursement is an **external side effect**. External payment systems are unreliable: networks fail, gateways time out, banks return ambiguous responses. A timeout does **not** mean the bank did not process the transaction — the money may already be in transit.

The system is designed around these principles:

1. **Redemption never rolls back due to bank failures.** The user completed the flow; their redemption stands.
2. **Disbursement attempts are recorded before calling the bank.** If the process crashes mid-call, the attempt record survives for reconciliation.
3. **Disbursement can enter a PENDING state.** Unknown outcomes are expected, not exceptional.
4. **Reconciliation resolves unknown outcomes.** A scheduled job queries the bank for real status.
5. **Internal ledger posting only happens after confirmed success.** `WithdrawCash::run()` is called only when the bank confirms delivery — either immediately (gateway success) or later (reconciliation confirms).

These principles prevent: double disbursement, lost redemption data, and inconsistent financial state.

#### DisbursementAttempt Lifecycle

**Model**: `monorepo-packages/payment-gateway/src/Models/DisbursementAttempt.php`
**Table**: `disbursement_attempts` (migration: `monorepo-packages/payment-gateway/database/migrations/2025_12_22_115516_create_disbursement_attempts_table.php`)

The attempt record is created **before** the bank API call in `OmnipayPaymentGateway::disburse()` (line 88) with `status: 'pending'`. This ensures a record exists even if the process crashes during the API call.

```
DisbursementAttempt created (status: pending)
       │
       ▼
  Call bank gateway API
       │
       ├─ Success response ──▶ status: success, gateway_transaction_id set
       │                        → DisburseCash calls WithdrawCash::run()
       │                        → voucher.metadata.disbursement updated
       │
       ├─ Failure response ──▶ status: failed, error_type + error_message set
       │                        → DisburseCash records pending on voucher metadata
       │                        → fires DisbursementFailed event
       │                        → pipeline continues (redemption stands)
       │
       └─ Exception/timeout ─▶ status: failed, error_type = class name
                                → same as failure path above
```

**Status values**:
- `pending` — attempt created, bank call not yet completed (or process crashed mid-call)
- `success` — bank confirmed the transfer was accepted
- `failed` — bank rejected or local error occurred
- `cancelled` — operator abandoned the attempt via `disbursement:cancel`

**Key fields**: `voucher_code`, `reference_id` (unique), `gateway_transaction_id`, `amount`, `bank_code`, `settlement_rail`, `error_type`, `error_message`, `request_payload` (JSON), `response_payload` (JSON), `attempted_at`, `completed_at`, `attempt_count`, `last_checked_at`

#### Idempotency

**Reference format**: `"{voucher_code}-{mobile}"` — built by `DisburseInputData::fromVoucher()` at `monorepo-packages/payment-gateway/src/Data/Disburse/DisburseInputData.php:99`.

The `reference_id` column has a UNIQUE constraint. This reference is sent to the bank as the transaction reference, ensuring:
- The bank can deduplicate if the same request arrives twice
- Reconciliation can match bank records back to voucher + contact
- Retry attempts use the same reference (no duplicate transfers)

#### Reconciliation System

When a disbursement fails at redemption time, the voucher is redeemed but `WithdrawCash` was never called (the cash wallet still has balance). Reconciliation queries the bank for the real outcome and completes the ledger.

**Scheduled job**: `disbursement:reconcile-pending` runs every 15 minutes (`routes/console.php:18`), processing up to 50 attempts per run. Skips attempts younger than 5 minutes (gives the bank time to settle). Flags attempts with >10 checks for manual review.

**Single voucher**: `disbursement:reconcile {code}` — queries the bank for one voucher's latest reconcilable attempt.

**Reconciliation outcomes** (handled by `app/Actions/Payment/ReconcileDisbursement.php`):

| Bank says | Action taken | DisbursementAttempt status |
|-----------|-------------|---------------------------|
| **Completed** | Calls `WithdrawCash::run()` to debit internal cash wallet. Updates attempt + voucher metadata. Fires `DisbursementConfirmed`. | `success` |
| **Failed/Rejected** | Marks attempt as failed. Updates voucher metadata. Operator can retry or cancel. | `failed` |
| **Pending/Processing** | Increments `attempt_count`, updates `last_checked_at`. Will check again next cycle. | unchanged |
| **Unreachable** | Logs warning. No changes. Will retry next cycle. | unchanged |

#### Operational Commands

| Command | File | What it does |
|---------|------|--------------|
| `disbursement:reconcile {code}` | `app/Actions/Payment/ReconcileDisbursement.php` | Check bank status for one voucher and complete ledger if confirmed |
| `disbursement:reconcile-pending` | `app/Actions/Payment/ReconcilePendingDisbursements.php` | Batch reconcile all pending/unknown attempts (scheduled every 15min) |
| `disbursement:check {code}` | `app/Actions/Payment/CheckDisbursementHealth.php` | Diagnostic: show voucher status, all attempts, live bank status, interpretation |
| `disbursement:cancel {code}` | `app/Actions/Payment/CancelDisbursement.php` | Abandon a pending attempt (local only — bank may still process). Checks bank status first; refuses if bank says completed. Use `--force` to override. |

#### Architectural Warnings

**DO NOT**:
- Move bank API calls inside `DB::transaction()` — if the transaction rolls back after the bank accepted, you get a disbursed transfer with no local record
- Assume timeout = failure — the bank may have processed the transfer; always reconcile
- Remove or bypass the `DisbursementAttempt` record creation before the API call — this is the safety net for crash recovery
- Skip the idempotency reference — without it, retries create duplicate bank transfers
- Call `WithdrawCash::run()` before bank confirmation — the internal ledger must reflect reality, not intent

**Risks of violating these rules**: double disbursement (real money sent twice), financial inconsistency (ledger doesn't match bank), unrecoverable state (no record of what happened).

### Success Page

**Route**: `GET /disburse/{code}/success` → `DisburseController`
**Vue**: `resources/js/pages/disburse/Success.vue`

Displays:
- Success confirmation with voucher code and amount (theme-aware: uses `text-primary`, `bg-primary/10` classes)
- Rider message rendered by content type (markdown → parsed, HTML/SVG → sanitized, URL → iframe, text → line breaks)
- Countdown redirect to rider URL (if `rider.url` is set with `rider.redirect_timeout`)

**Route**: `GET /disburse/{code}/redirect` → `app/Http/Controllers/Disburse/DisburseSuccessRedirectController.php`
Final redirect to `rider.url` or fallback to `/disburse`.

---

## Key Files Reference

### Routes & Controllers (host app)

| Route | Method | Controller | Vue Page |
|-------|--------|-----------|----------|
| `GET /disburse` | `start()` | `app/Http/Controllers/Disburse/DisburseController.php` | `resources/js/pages/disburse/Start.vue` |
| `POST /disburse/{code}/complete` | `complete()` | same | — (callback, no render) |
| `POST /disburse/{code}/redeem` | `redeem()` | same | — (redirect on success) |
| `GET /disburse/{code}/success` | — | same | `resources/js/pages/disburse/Success.vue` |
| `GET /disburse/{code}/redirect` | `__invoke()` | `app/Http/Controllers/Disburse/DisburseSuccessRedirectController.php` | — (HTTP redirect) |
| `GET /disburse/{code}/cancel` | `cancel()` | `DisburseController` | — (redirect to /disburse) |

### Form-Flow Package Routes (vendor)

| Route | Controller | Vue Page |
|-------|-----------|----------|
| `GET /form-flow/{flow_id}` | `FormFlowController::show()` | Handler-dependent (see steps below) |
| `POST /form-flow/{flow_id}/step/{index}` | `FormFlowController::updateStep()` | — (redirect to next step) |

### Pipeline & Events (host + package)

| File | Owner | Role |
|------|-------|------|
| `config/voucher-pipeline.php` | host | Pipeline stage registration |
| `app/Pipelines/RedeemedVoucher/PersistInputs.php` | host | Saves collected data |
| `app/Pipelines/RedeemedVoucher/ClearOgMetaCache.php` | host | Clears OG image cache |
| `app/Pipelines/RedeemedVoucher/SyncEnvelopeData.php` | host | Dispatches envelope sync job |
| `app/Pipelines/RedeemedVoucher/SendFeedbacks.php` | host | Sends notifications |
| `monorepo-packages/voucher/src/Pipelines/RedeemedVoucher/ValidateRedeemerAndCash.php` | package | Validates redeemer + cash |
| `monorepo-packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php` | package | Disburses funds |
| `monorepo-packages/voucher/src/Handlers/HandleRedeemedVoucher.php` | package | Orchestrates pipeline |
| `monorepo-packages/voucher/src/Observers/VoucherObserver.php` | package | Triggers handler on redeemed event |
| `app/Providers/AppServiceProvider.php` | host | Event listener registration |
| `app/Listeners/NotifyAdminOfDisbursementFailure.php` | host | Admin alert on failure |
| `app/Listeners/UpdateContactKycStatus.php` | host | KYC status persistence |

---

## YAML Driver Deep-Dive

**File**: `config/form-flow-drivers/voucher-redemption.yaml`

This declarative YAML config controls the entire `/disburse` UX. It defines which steps appear, in what order, and under what conditions. The `DriverService` (package) reads this file and produces `FormFlowInstructionsData`.

### Context Building

`vendor/3neti/form-flow/src/Services/DriverService.php::buildContext()` reads `$voucher->instructions` and produces these template variables:

**Scalar variables** (available as `{{ variable_name }}`):
- `code` — voucher code string
- `amount` — integer, from `instructions.cash.amount`
- `currency` — string, from `instructions.cash.currency` (default: `PHP`)
- `owner_name` — voucher owner's `name` (default: `Unknown`)
- `base_url` — `url('')` (e.g., `https://redeem-x.laravel.cloud`)
- `timestamp` — Unix epoch seconds
- `splash_enabled` — `"true"` or `"false"`, from `config('splash.enabled')` / `SPLASH_ENABLED` env

**Field presence flags** (boolean, derived from `$voucher->instructions->inputs->fields` array):
- `has_name`, `has_email`, `has_birth_date`, `has_address`
- `has_location`, `has_selfie`, `has_signature`
- `has_kyc`, `has_otp`
- `has_reference_code`, `has_gross_monthly_income`

Each flag maps to a checkbox on the Portal/Generate page. When the issuer toggles "KYC" on, the voucher's `inputs.fields` array includes `"kyc"`, which sets `has_kyc = true`, which includes `steps.kyc` in the flow.

**Nested rider context** (available as `{{ rider.splash }}`, etc.):
- `rider.message`, `rider.url`, `rider.redirect_timeout`
- `rider.splash`, `rider.splash_timeout`

### Step-by-Step: What, Why, UX, and Config

#### Step 0: Splash (`steps.splash`)

- **Handler**: `splash` (built-in)
- **YAML key**: `steps.splash`
- **What**: Welcome/intro screen before redemption begins
- **Why**: Brand presence, legal disclaimers, campaign messaging. The issuer controls the content via `rider.splash` when generating the voucher.
- **UX**: Renders rider splash content as HTML/Markdown/SVG/URL/text. Auto-advance countdown (default 5s). "Continue Now" button for manual advance.
- **Condition**: `{{ splash_enabled | default('true') }}` — shown by default
- **Env config**: `SPLASH_ENABLED` (default `true`), `SPLASH_DEFAULT_TIMEOUT` (default `5`), `SPLASH_BUTTON_LABEL`, `SPLASH_APP_AUTHOR`, `SPLASH_COPYRIGHT_HOLDER`, `SPLASH_COPYRIGHT_YEAR`
- **Vue**: `resources/js/pages/form-flow/core/Splash.vue`
- **Collected data key**: `splash_page`

#### Step 1: Wallet (`steps.wallet`)

- **Handler**: `form` (built-in generic form)
- **YAML key**: `steps.wallet`
- **step_name**: `wallet_info`
- **What**: Collects payment disbursement details — the only always-shown step (no condition)
- **Why**: Mobile number + bank + account number are required to send money via INSTAPAY/PESONET. This is the core of the redemption UX.
- **UX**:
  - `amount` — readonly badge (pill display, not editable), default from voucher amount
  - `settlement_rail` — hidden field, currently hardcoded to `INSTAPAY`
  - `mobile` — hero field (large, prominent), phone input with `+63` prefix
  - `bank_code` — bank/wallet dropdown (type `bank_account`), default `GXCHPHM2XXX` (GCash)
  - `account_number` — text input, grouped with `bank_code` as "endorsement" card
  - **Auto-sync**: when `settlement_rail` = `INSTAPAY`, mobile number auto-copies to `account_number` after 1.5s debounce. E.164 (`+639173011987`) converted to national format (`09173011987`).
- **Env config**: None external. Field defaults are in the YAML.
- **Known issue**: `settlement_rail` is hardcoded to `INSTAPAY`. TODO: read from `voucher.instructions.cash.settlement_rail`.
- **Vue**: `resources/js/pages/form-flow/core/GenericForm.vue` (field variants: `readonly-badge`, `hero`, `group: "endorsement"`)
- **Collected data key**: `wallet_info`

#### Step 2: KYC (`steps.kyc`)

- **Handler**: `kyc` (plugin: `lbhurtado/form-handler-kyc`)
- **YAML key**: `steps.kyc`
- **step_name**: `kyc_verification`
- **What**: Identity verification via HyperVerge — selfie + government ID matching
- **Why**: Compliance and anti-fraud for high-value vouchers. The issuer enables this by toggling the `kyc` input field when generating the voucher.
- **UX**: Redirects to HyperVerge mobile onboarding flow → user captures face + government ID → callback returns to `KYCStatusPage.vue` → auto-polls every 5 seconds until status is `approved` or `rejected`.
- **Condition**: `{{ has_kyc }}` — `true` when voucher has `kyc` in `instructions.inputs.fields`
- **Env config required**: `HYPERVERGE_BASE_URL`, `HYPERVERGE_APP_ID`, `HYPERVERGE_APP_KEY`, `HYPERVERGE_URL_WORKFLOW`
- **Package**: `lbhurtado/form-handler-kyc` (auto-registers handler via service provider)
- **Vue**: `resources/js/pages/form-flow/kyc/KYCInitiatePage.vue` → `resources/js/pages/form-flow/kyc/KYCStatusPage.vue`
- **Collected data key**: `kyc_verification` (contains: `status`, `transaction_id`, `name`, `date_of_birth`, `address`, `id_type`, `id_number`)
- **IMPORTANT**: Must come before `steps.bio` — KYC results auto-populate bio fields via `$variable` references.

#### Step 3: Bio (`steps.bio`)

- **Handler**: `form` (built-in generic form)
- **YAML key**: `steps.bio`
- **step_name**: `bio_fields`
- **What**: Personal information — name, email, birth_date, address, reference_code, gross_monthly_income
- **Why**: Issuer-defined identity data for audit trail and compliance. Each field is individually toggleable via voucher input field checkboxes.
- **UX**: Standard form. If KYC was completed in the previous step, fields auto-fill from KYC data. User can accept or edit auto-populated values.
- **Condition**: `{{ has_name or has_email or has_birth_date or has_address or has_reference_code or has_gross_monthly_income }}` — shown when ANY bio field is enabled
- **KYC auto-population**: The `config.variables` section maps named references to prior step data:
  - `$kyc_name` → `$kyc_verification.name`
  - `$kyc_email` → `$kyc_verification.email`
  - `$kyc_birth` → `$kyc_verification.date_of_birth`
  - `$kyc_addr` → `$kyc_verification.address`
  - Field defaults reference these: e.g., `full_name.default: "$kyc_name"`
- **Env config**: None external. Fields shown based on voucher input toggles.
- **Vue**: `resources/js/pages/form-flow/core/GenericForm.vue`
- **Collected data key**: `bio_fields`
- **Individual field conditions**: each field has its own `condition` (e.g., `{{ has_name }}` for `full_name`)

#### Step 4: OTP (`steps.otp`)

- **Handler**: `otp` (plugin: `lbhurtado/form-handler-otp`)
- **YAML key**: `steps.otp`
- **step_name**: `otp_verification`
- **What**: SMS-based phone number verification via EngageSpark
- **Why**: Ensures the person redeeming actually controls the mobile number they entered in the wallet step. Prevents fraudulent claims where someone enters another person's mobile.
- **UX**: Sends 6-digit SMS code to the mobile number → user enters code → validates → 5-minute expiry. Resend option available.
- **Condition**: `{{ has_otp }}` — `true` when voucher has `otp` in `instructions.inputs.fields`
- **Env config required**: `ENGAGESPARK_API_KEY`, `ENGAGESPARK_ORG_ID`
- **Optional env config**: `OTP_HANDLER_CODE_LENGTH` (default `6`), `OTP_HANDLER_EXPIRY_MINUTES` (default `5`)
- **Package**: `lbhurtado/form-handler-otp` (auto-registers handler via service provider)
- **Vue**: `resources/js/pages/form-flow/otp/OtpCapturePage.vue`
- **Collected data key**: `otp_verification` (contains: `otp_verified` boolean, `verified_at` timestamp)

#### Step 5: Location (`steps.location`)

- **Handler**: `location` (plugin: `lbhurtado/form-handler-location`)
- **YAML key**: `steps.location`
- **step_name**: `location_capture`
- **What**: GPS coordinates + reverse geocoding + static map snapshot
- **Why**: Proof of presence for audit. The issuer can verify the redeemer was physically at a specific location. Used for field disbursements, event attendance, etc.
- **UX**: Browser geolocation prompt → shows map pin on interactive map → reverse geocodes coordinates to readable address via OpenCage → captures static map image via Mapbox.
- **Condition**: `{{ has_location }}` — `true` when voucher has `location` in `instructions.inputs.fields`
- **YAML config**: `require_address: true`, `capture_snapshot: true`
- **Env config required**: `VITE_OPENCAGE_KEY` (reverse geocoding API), `VITE_MAPBOX_TOKEN` (static map images)
- **Package**: `lbhurtado/form-handler-location`
- **Vue**: `resources/js/pages/form-flow/location/LocationCapturePage.vue`
- **Collected data key**: `location_capture` (contains: `latitude`, `longitude`, `accuracy`, `timestamp`, `address.formatted`, `address.components`, `map` base64 PNG)

#### Step 6: Selfie (`steps.selfie`)

- **Handler**: `selfie` (plugin: `lbhurtado/form-handler-selfie`)
- **YAML key**: `steps.selfie`
- **step_name**: `selfie_capture`
- **What**: Front camera photo capture
- **Why**: Visual proof of identity. Cheaper alternative to full KYC for low-risk vouchers. Photo stored for audit trail.
- **UX**: Opens front camera via `getUserMedia` → user takes photo → preview → confirm or retake.
- **Condition**: `{{ has_selfie }}` — `true` when voucher has `selfie` in `instructions.inputs.fields`
- **YAML config**: `width: 640`, `height: 480`, `quality: 0.9`
- **Optional env config**: `SELFIE_HANDLER_WIDTH`, `SELFIE_HANDLER_HEIGHT`, `SELFIE_HANDLER_QUALITY`, `SELFIE_HANDLER_ALLOWED_FORMATS`
- **Package**: `lbhurtado/form-handler-selfie`
- **Vue**: `resources/js/pages/form-flow/selfie/SelfieCapturePage.vue`
- **Collected data key**: `selfie_capture` (contains: `selfie` base64 JPEG/PNG)

#### Step 7: Signature (`steps.signature`)

- **Handler**: `signature` (plugin: `lbhurtado/form-handler-signature`)
- **YAML key**: `steps.signature`
- **step_name**: `signature_capture`
- **What**: Digital signature via canvas drawing pad
- **Why**: Legal acknowledgment. Redeemer signs to confirm receipt of funds. Used for compliance and dispute resolution.
- **UX**: Canvas-based signature pad → user draws signature → exports as PNG. Clear button to restart.
- **Condition**: `{{ has_signature }}` — `true` when voucher has `signature` in `instructions.inputs.fields`
- **YAML config**: `width: 600`, `height: 256`, `quality: 0.85`, `line_width: 2`
- **Optional env config**: `SIGNATURE_HANDLER_WIDTH`, `SIGNATURE_HANDLER_HEIGHT`, `SIGNATURE_HANDLER_QUALITY`
- **Package**: `lbhurtado/form-handler-signature`
- **Vue**: `resources/js/pages/form-flow/signature/SignatureCapturePage.vue`
- **Collected data key**: `signature_capture` (contains: `signature` base64 PNG)

### Handler Registration

Handlers auto-register via Laravel package service providers (package auto-discovery). The `DriverService` checks handler availability at runtime. If a required handler package is not installed, it renders `resources/js/pages/form-flow/core/MissingHandler.vue` with an install hint:

| Handler name | Package | Install command |
|-------------|---------|----------------|
| `form` | `3neti/form-flow` (built-in) | — |
| `splash` | `3neti/form-flow` (built-in) | — |
| `kyc` | `lbhurtado/form-handler-kyc` | `composer require lbhurtado/form-handler-kyc` |
| `otp` | `lbhurtado/form-handler-otp` | `composer require lbhurtado/form-handler-otp` |
| `location` | `lbhurtado/form-handler-location` | `composer require lbhurtado/form-handler-location` |
| `selfie` | `lbhurtado/form-handler-selfie` | `composer require lbhurtado/form-handler-selfie` |
| `signature` | `lbhurtado/form-handler-signature` | `composer require lbhurtado/form-handler-signature` |

### Callback URL Patterns

Defined in the YAML driver under `callbacks:`:
- `on_complete`: `{{ base_url }}/disburse/{{ code }}/complete` — POST with collected data
- `on_cancel`: `{{ base_url }}/disburse` — redirect back to code entry page

### Reference ID Pattern

`reference_id: "disburse-{{ code }}-{{ timestamp }}"` — used by `Complete.vue` to detect disburse flows and extract the voucher code.

---

## Theming

### CSS Variable-Based Theme System

All disburse flow pages use CSS variable-based classes that respond to theme selection. The `useTheme` composable manages theme state.

**Available themes** (defined in `resources/js/composables/useTheme.ts`):

| Theme ID | Name | CSS class | Description |
|----------|------|-----------|-------------|
| `default` | Default | (none — base variables) | Clean, neutral interface |
| `steampunk` | Steampunk | `theme-steampunk` | Warm brass & aged parchment |
| `amber` | Amber | `theme-amber` | Sunlit gold, quiet warmth |

**Theme-aware class patterns** (used throughout the flow):
- Backgrounds: `bg-primary/5`, `bg-primary/10`, `bg-primary/20`
- Text: `text-primary`, `text-primary-foreground`
- Borders: `border-primary/20`, `border-primary/30`
- These resolve to different actual colors depending on the active theme's CSS variables.

**Theme picker**: Located in `RedeemWidget.vue` → System Info tab (toggle via Palette icon). Includes visual previews for each theme.

**Persistence**: Theme ID stored in `localStorage` key `pwa-theme`. Applied on mount via `initializeTheme()` which adds `theme-{id}` class to `<html>`.

**Package sync note**: When editing theme classes in `Splash.vue`, `GenericForm.vue`, or `Complete.vue`, stubs must be synced to `3neti/form-flow` package. See `docs/guides/ai-development/FORM_FLOW_UI_UPDATE_SOP.md`.

---

## Related Documentation

- Pay flow map: `docs/guides/features/PAY_FLOW_MAP.md`
- Form-flow package internals: `docs/guides/features/form-flow/`
- Form-flow env vars: `docs/guides/features/form-flow/ENV_VARS.md`
- Form-flow UI update SOP: `docs/guides/ai-development/FORM_FLOW_UI_UPDATE_SOP.md`
- Notification templates: `docs/NOTIFICATION_TEMPLATES.md`
- Disbursement failure alerts: `docs/DISBURSEMENT_FAILURE_ALERTS.md`
- Settlement envelope architecture: `docs/architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md`
- Omnipay integration: `docs/OMNIPAY_INTEGRATION_PLAN.md`
- Payment gateway config: see `WARP.md` → Payment Gateway Configuration
