# Divisible Vouchers

**Status:** Implemented (branch `feature/divisible-vouchers`)
**Last updated:** 2026-03-16

## What It Is

Divisible vouchers allow a voucher's face value to be disbursed in multiple portions ("slices") rather than all at once. A ₱5,000 voucher can be split into five ₱1,000 withdrawals, or the redeemer can choose custom amounts per withdrawal.

Without this feature, a voucher is always fully consumed in a single disbursement at redemption time.

## Two-Operation Model

The feature introduces a clear separation:

- **Redeem** (existing flow) — first-time use. Full form flow, KYC, identity binding. Sets `redeemed_at`. For fixed mode, disburses the **first slice** automatically.
- **Withdraw** (new) — subsequent uses on an already-redeemed voucher. Identity verification only (mobile number match). Disburses the **next slice**.

The redeemer doesn't need to know two operations exist. Smart routing on `/disburse` automatically redirects already-redeemed divisible vouchers to `/withdraw`.

## Two Modes

### Fixed Slices

The issuer defines equal portions at generation time. The redeemer has no amount choice.

- **Config:** `slice_mode: 'fixed'`, `slices: 5`
- **Example:** ₱5,000 voucher with 5 slices → five withdrawals of ₱1,000 each
- **First slice** is auto-disbursed at redemption. Remaining 4 via `/withdraw`.
- **Constraint:** face amount must be evenly divisible by slice count

### Open Slices

The redeemer chooses the withdrawal amount each time, within constraints set by the issuer.

- **Config:** `slice_mode: 'open'`, `max_slices: 10`, `min_withdrawal: 100`
- **Example:** ₱5,000 voucher, max 10 withdrawals, minimum ₱100 each
- **No auto-disbursement** at redemption. Redeemer uses `/withdraw` to choose amount for every withdrawal (including the first).
- **Stranded balance:** if remaining balance < `min_withdrawal`, no more withdrawals are possible. This is an intentional business rule.
- **Default `min_withdrawal`:** ₱100 (configurable in `config/voucher.php`)

### Non-Divisible (Default)

`slice_mode: null` (or `slices: 1`) — current behavior. Full amount in one disbursement. Zero behavior change for existing vouchers.

## User Flows

### Issuer (Voucher Generation)

1. Go to Generate Vouchers page (portal or PWA)
2. In Disbursement Settings, select "Fixed Slices" or "Open Slices"
3. Configure: number of slices, or max slices + minimum withdrawal
4. Cost breakdown shows transaction fee × slice count (e.g., ₱15 × 5 = ₱75)
5. Generate vouchers

### Redeemer (Fixed Mode)

1. Enter voucher code on `/disburse` → normal redemption flow
2. Complete form flow (mobile, KYC, etc.)
3. First slice is auto-disbursed (e.g., ₱1,000 of ₱5,000)
4. Later: enter same code on `/disburse` → auto-redirected to `/withdraw`
5. Enter mobile number (must match original redeemer)
6. Confirm → next slice disbursed
7. Repeat until all slices consumed

### Redeemer (Open Mode)

1. Enter voucher code on `/disburse` → normal redemption flow
2. Complete form flow — **no auto-disbursement**
3. Redirected to `/withdraw` to choose first withdrawal amount
4. Enter mobile + desired amount (≥ minimum)
5. Confirm → funds disbursed
6. Return anytime to withdraw more (up to max slices or until balance exhausted)

## Pricing

Divisible vouchers are a **priced feature**. The transaction fee is multiplied by the effective slice count:

- Non-divisible: ₱15 × 1 = ₱15
- Fixed 5 slices: ₱15 × 5 = ₱75
- Open max 10 slices: ₱15 × 10 = ₱150 (charged upfront for max possible transfers)

Implemented in `app/Services/InstructionCostEvaluator.php`.

## Key Design Decisions

1. **`isRedeemed()` stays binary** — redemption is a one-time event. Subsequent uses are "withdrawals."
2. **Bank account reuse** — withdrawals reuse the bank account from the first redemption (stored on contact). No bank input needed for withdrawals.
3. **Same-redeemer enforcement** — only the contact bound during first redemption can withdraw. Verified by mobile number (last 10 digits comparison).
4. **Per-slice references** — `{code}-{mobile}-S{n}` format prevents idempotency collisions with the payment gateway.
5. **Open mode floor** — `min_withdrawal` prevents dust withdrawals. Stranded balance is an intentional business decision.
6. **No change to existing queries** — all reporting queries using `whereNotNull('redeemed_at')` continue to work. `display_status` is still "redeemed."

## Smart Routing

When a redeemer enters an already-redeemed divisible voucher code on `/disburse`:

- `isRedeemed() && canWithdraw()` → redirect to `/withdraw?code=CODE`
- `isRedeemed() && !canWithdraw()` → error "fully consumed"
- `!isRedeemed()` → normal redemption flow (unchanged)

Implemented in `DisburseController::start()`.

## PWA UI

- **Voucher list:** "Fixed Slices" or "Open Slices" outline badge on VoucherCard
- **Voucher detail:** Slice progress card with:
  - Progress bar (consumed/total slices)
  - Stats grid: slice amount, consumed, remaining slices, remaining balance
  - Status badge: "X Slices Remaining" or "Fully Consumed"
- **Show page badge:** "Fixed/Open Slices" badge next to the voucher type/state badges

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/withdraw?code=CODE` | Withdraw page (Inertia) |
| POST | `/withdraw/{voucher:code}` | Process withdrawal (web) |
| GET | `/withdraw/{voucher:code}/success` | Success page (Inertia) |
| POST | `/api/v1/vouchers/{code}/withdraw` | API withdraw endpoint |

All routes are public (no authentication). Identity is verified by mobile number match.

## Known Future Considerations

- **Label configurability:** The word "Slice" may not be intuitive to end users. Plan to make the label configurable (e.g., "Installment", "Portion", "Tranche") via config or per-voucher instructions.
- **SMS smart routing (Phase 3.4):** `REDEEM CODE` via SMS should auto-dispatch to withdraw handler for already-redeemed divisible vouchers. See `docs/implementation/active/DIVISIBLE_VOUCHERS_REMAINING.md`.
- **Dashboard stats (Phase 4.5):** Optional — show count of vouchers with remaining slices.
- **Gateway integration for withdrawals:** Currently `WithdrawFromVoucher` only withdraws from the internal wallet. Future: integrate with payment gateway for actual bank disbursement on each withdrawal (similar to `DisburseCash` pipeline).
