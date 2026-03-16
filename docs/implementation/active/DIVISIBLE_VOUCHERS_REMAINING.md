# Divisible Vouchers — Remaining Phases

**Branch:** `feature/divisible-vouchers` (6 commits ahead of main)
**Plan ID:** `696eec0b-4316-4898-b734-81de70d278b4`
**Last updated:** 2026-03-16

## Status

Core feature is fully functional end-to-end: generate → redeem (first slice) → withdraw (subsequent slices). PWA UI shows slice progress and divisible badges. 53 tests, 153 assertions, all green.

## Phase 3.4 — SMS Smart Routing

**Goal:** `REDEEM CODE` via SMS should dispatch to the withdraw handler for already-redeemed divisible vouchers, instead of returning "already redeemed."

**File:** `app/SMS/Handlers/SMSRedeem.php`

**Logic:**
- If voucher `isRedeemed() && canWithdraw()` → trigger withdraw flow (reuse bank account, disburse next slice)
- If voucher `isRedeemed() && !canWithdraw()` → reply "fully consumed"
- If voucher `!isRedeemed()` → normal redemption flow (unchanged)

**Tests needed:** `tests/Feature/DivisibleVouchers/SmsSmartRoutingTest.php`
- Redeemed divisible voucher + REDEEM command → next slice disbursed
- Fully consumed voucher + REDEEM command → "fully consumed" reply
- Non-divisible redeemed voucher + REDEEM command → "already redeemed" (unchanged)
- Unredeemed voucher → normal flow (unchanged)

**Notes:**
- No separate `WITHDRAW` SMS command — `REDEEM` handles both operations transparently
- Bank account reused from first redemption metadata
- Same-redeemer enforcement via mobile number matching

## Phase 4.5 — Dashboard Stats (Optional)

**Goal:** Show `has_remaining_slices` count on the dashboard for vouchers that still have withdrawable slices.

**Scope:** Additive only — no existing queries changed. Low priority.

**Approach:**
- Add a query/scope for divisible vouchers with remaining balance
- Display count on Portal or dashboard page
- Consider a "Divisible" filter in voucher list
