# Divisible Vouchers — AI Development Guide

**Audience:** AI agents (Warp Oz, Claude Code, Junie)
**Last updated:** 2026-03-16
**Feature docs:** `docs/guides/features/DIVISIBLE_VOUCHERS.md`
**Remaining work:** `docs/implementation/active/DIVISIBLE_VOUCHERS_REMAINING.md`

## Core Concept

A divisible voucher's face value can be disbursed in multiple portions. The system has two operations:

- **Redeem** — first-time use, sets `redeemed_at`, runs full pipeline
- **Withdraw** — subsequent uses, identity check only, disburses next portion

The word "slice" is used internally to mean one withdrawal portion. This label is likely to become configurable in the future (see "Known Future Tweaks" below).

## Key Files — Where Things Live

### Data Layer (voucher package)
- `monorepo-packages/voucher/src/Data/CashInstructionData.php` — Slice fields: `slice_mode`, `slices`, `max_slices`, `min_withdrawal`
- `monorepo-packages/voucher/src/Data/VoucherData.php` — DTO with slice fields for API/frontend consumption
- `monorepo-packages/voucher/src/Data/DisbursementData.php` — Reads both singular and plural metadata formats

### Model (voucher package)
- `monorepo-packages/voucher/src/Models/Voucher.php` (lines 287–368) — All slice computed properties:
  - `getSliceMode()`, `isDivisible()`, `getSliceAmount()`, `getMaxSlices()`
  - `getMinWithdrawal()`, `getConsumedSlices()`, `getRemainingSlices()`
  - `getRemainingBalance()`, `hasRemainingSlices()`, `canWithdraw()`

### Pipeline (voucher package)
- `monorepo-packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php` — Slice-aware first disbursement:
  - Fixed mode: auto-disburses `getSliceAmount()` with `sliceNumber: 1`
  - Open mode: skips auto-disbursement entirely (redeemer uses `/withdraw`)

### Wallet (wallet package)
- `monorepo-packages/wallet/src/Actions/WithdrawCash.php` — Accepts optional `?int $amount` param (centavos). When null, drains full balance (backward compat).

### Payment Gateway (payment-gateway package)
- `monorepo-packages/payment-gateway/src/Data/Disburse/DisburseInputData.php` — Accepts optional `?float $amount` and `?int $sliceNumber`. Reference format: `{code}-{mobile}-S{n}` for divisible.

### Reporting (voucher package)
- `monorepo-packages/voucher/src/Reports/Resolvers/VoucherListResolver.php` — Includes `slice_mode` in list output

### Application Layer (host app)
- `app/Actions/Voucher/WithdrawFromVoucher.php` — Core withdraw action (validates state, resolves amount, withdraws from wallet)
- `app/Http/Controllers/Withdraw/WithdrawController.php` — Web controller (show, process, success)
- `app/Http/Controllers/Disburse/DisburseController.php` — Smart routing logic in `start()` method
- `app/Actions/Api/Vouchers/GenerateVouchers.php` — API field mapping for slice instructions
- `app/Services/InstructionCostEvaluator.php` — Pricing: transaction fee × slice count

### Frontend (host app + pwa-ui package)
- `resources/js/pages/pwa/Vouchers/Generate.vue` — Slice mode controls in Disbursement Settings
- `resources/js/pages/pwa/Vouchers/Show.vue` — Slice progress card + badges
- `resources/js/components/pwa/VoucherCard.vue` — "Fixed/Open Slices" badge in list
- `resources/js/pages/withdraw/Withdraw.vue` — Withdrawal page (public)
- `resources/js/pages/withdraw/Success.vue` — Withdrawal success page
- `monorepo-packages/pwa-ui/src/Http/Controllers/PwaVoucherController.php` — Feeds slice data to Show page

### Routes
- `routes/withdraw.php` — Web withdraw routes (registered in `bootstrap/app.php`)
- `routes/api/vouchers.php` — API withdraw endpoint

### Tests
All in `tests/Feature/DivisibleVouchers/`:
- `SliceInstructionsTest.php` — CashInstructionData, voucher accessors, pricing
- `SliceDisbursementTest.php` — WithdrawCash partial, DisburseInputData, references, metadata
- `SliceApiMappingTest.php` — API field mapping in GenerateVouchers
- `SmartRoutingTest.php` — DisburseController smart routing
- `WithdrawFromVoucherTest.php` — Core action: fixed/open modes, validation, edge cases
- `WithdrawHttpTest.php` — Web and API endpoint tests
- `VoucherDataDtoTest.php` — DTO slice fields, backward compat

Total: 53 tests, 153 assertions.

## How Consumed Slices Are Counted

Slices are counted from **wallet transactions**, not a counter column:

```php
$this->cash->wallet->transactions()
    ->where('type', 'withdraw')
    ->whereJsonContains('meta->flow', 'redeem')
    ->where('confirmed', true)
    ->count();
```

Each withdrawal records `meta->flow = 'redeem'` and `meta->slice_number = N`. This means the wallet ledger is the source of truth — no separate slice counter to keep in sync.

## Patterns to Follow

### Adding a new slice mode
1. Add fields to `CashInstructionData`
2. Add validation in `GenerateVouchers.php` (API mapping)
3. Handle in `Voucher::getMaxSlices()` and `Voucher::getMinWithdrawal()` (match expression)
4. Handle in `DisburseCash::handle()` (pipeline step)
5. Handle in `WithdrawFromVoucher::resolveAmount()` (amount resolution)
6. Update `VoucherData` DTO
7. Update frontend (Generate.vue, Show.vue, VoucherCard.vue)
8. Add tests in `tests/Feature/DivisibleVouchers/`

### Modifying slice UI
PWA files are published stubs — **always sync back to package** per `docs/guides/ai-development/PWA_UI_STUB_SYNC_SOP.md`:
- `resources/js/pages/pwa/Vouchers/Show.vue` → `monorepo-packages/pwa-ui/resources/js/pages/pwa/Vouchers/Show.vue`
- `resources/js/components/pwa/VoucherCard.vue` → `monorepo-packages/pwa-ui/resources/js/components/VoucherCard.vue`

### Mobile number comparison
The system normalizes mobiles by comparing last 10 digits (strips all non-digits, takes suffix). This handles `+639xx`, `09xx`, and `9xx` formats interchangeably.

## Known Future Tweaks

### 1. Label Configurability (Priority: Medium)
The word "Slice" is used throughout the UI (badges: "Fixed Slices", "Open Slices"; progress card: "Slices Consumed", "Slices Remaining"). This may not be intuitive to common users.

**Plan:** Make the label configurable, likely via:
- `config/voucher.php` for a system-wide default (e.g., `'slice_label' => 'Installment'`)
- Or per-voucher via `CashInstructionData` (e.g., `slice_label: 'Portion'`)

**Files to update when implementing:**
- `resources/js/pages/pwa/Vouchers/Show.vue` — progress card labels
- `resources/js/components/pwa/VoucherCard.vue` — badge text
- `resources/js/pages/pwa/Vouchers/Generate.vue` — mode selector labels
- `resources/js/pages/withdraw/Withdraw.vue` — page copy
- `resources/js/pages/withdraw/Success.vue` — confirmation copy
- `monorepo-packages/pwa-ui/src/Http/Controllers/PwaVoucherController.php` — pass label to frontend
- `app/Http/Controllers/Withdraw/WithdrawController.php` — pass label to frontend
- Plus pwa-ui stub sync for all Vue files

### 2. SMS Smart Routing (Phase 3.4 — Next Session)
`REDEEM CODE` via SMS should auto-dispatch to withdraw for already-redeemed divisible vouchers.
See `docs/implementation/active/DIVISIBLE_VOUCHERS_REMAINING.md` for full spec.
File: `app/SMS/Handlers/SMSRedeem.php`

### 3. Gateway Integration for Withdrawals
Currently `WithdrawFromVoucher` only withdraws from the internal Bavix wallet. It does **not** call the payment gateway to actually disburse to a bank account. Future work: integrate gateway disbursement into the withdraw action (similar to `DisburseCash` pipeline).

### 4. Dashboard Stats (Phase 4.5 — Optional)
Show count of vouchers with `canWithdraw() === true` on the dashboard. Additive only.

## Backward Compatibility

- `slice_mode: null` (default) = current behavior. No code path changes for non-divisible vouchers.
- `isRedeemed()`, `isRedeemable()`, `canRedeem()`, `redeemed_at` — completely untouched
- All reporting queries using `whereNotNull('redeemed_at')` still work
- `display_status` for redeemed vouchers is still "redeemed" (UI shows slice context separately)
- `WithdrawCash::run()` with null amount = full balance drain (original behavior)
- `DisburseInputData::fromVoucher()` with null amount = full `$cash->amount` (original behavior)
