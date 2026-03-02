# Disbursement Robustness — Phase E2 & E3 TODO

Remaining items from the Disbursement Robustness plan. Phases A–D, B1–B3, and E1 are complete.

## E2: Admin Dashboard — Reconcile Button
**Goal**: Let operators trigger reconciliation from the voucher detail page.

### Tasks
- Add API endpoint: `POST /api/v1/vouchers/{code}/reconcile` (authenticated, admin-only)
- Wire endpoint to `ReconcileDisbursement::run($code)` and return the result JSON
- Voucher Show page: if `metadata.disbursement.status === 'pending'` and `requires_reconciliation === true`, show a "Reconcile" button
- Button calls the API endpoint, shows result (success/failure/still-pending) in a toast or modal
- Also show `attempt_count` and `last_checked_at` in the disbursement details section so the operator can see how many times it's been checked

### Files likely affected
- `app/Http/Controllers/Api/V1/VoucherController.php` (or new dedicated controller)
- `routes/api.php` — new route
- `resources/js/pages/vouchers/Show.vue` — reconcile button + disbursement status display
- `resources/js/components/` — possible new `ReconcileButton.vue` component

## E3: User-Facing "Pending Settlement" Messaging
**Goal**: When disbursement fails at redemption time, show the user a clear message instead of generic success.

### Tasks
- Redemption success page: detect `metadata.disbursement.requires_reconciliation` flag
- Show: "Your voucher has been redeemed. Settlement is being processed and may take a few minutes."
- Dashboard/transactions page: show "Pending Settlement" badge on vouchers where `disbursement.status === 'pending'`
- Differentiate from normal "Pending" (bank is processing) vs "Pending + requires_reconciliation" (gateway error, needs retry)

### Files likely affected
- `resources/js/pages/redeem/Success.vue` — conditional messaging
- `resources/js/pages/transactions/Index.vue` or transaction list component — badge variant
- `resources/js/components/TransactionDetailModal.vue` — pending settlement indicator
- Backend: ensure `requires_reconciliation` flag is passed to frontend via Inertia props
