# Settlement Voucher System - Remaining Tasks

## 🎯 Core Functionality (Must-Have)

### UI/UX Polish
- [ ] Hide or default "Amount" field to 0 for PAYABLE/SETTLEMENT vouchers (not needed since target_amount is used)
- [ ] Add helpful tooltips/descriptions for each voucher type in the radio selector
- [ ] Show settlement fields in JSON preview on generation form (if enabled)
- [ ] Update cost breakdown preview to show settlement voucher pricing charges

### Voucher Display/Management
- [ ] Update Voucher Show page (`/vouchers/{code}`) to display settlement fields:
  - [ ] Show voucher type badge (Redeemable/Payable/Settlement)
  - [ ] Show state badge (Active/Locked/Closed/Cancelled/Expired) with color coding
  - [ ] Show target amount for PAYABLE/SETTLEMENT types
  - [ ] Show payment progress: paid vs target (e.g., "₱1,250 / ₱1,500 paid")
  - [ ] Show remaining amount to collect
  - [ ] Add "Payment History" section listing all deposits via /pay
- [ ] Update Vouchers List page (`/vouchers`) to show settlement type column/badge
- [ ] Add filtering by voucher type in vouchers list

### Transaction/Payment Views
- [ ] Update Transaction History page to show payment transactions with `flow: pay`
- [ ] Add transaction type badge (Deposit via /pay vs Withdrawal via /redeem)
- [ ] Link payment transactions back to voucher code
- [ ] Show settlement voucher details in transaction modal/details

### QR Code for Payments
- [ ] Generate payment QR code for PAYABLE/SETTLEMENT vouchers (like wallet QR)
- [ ] QR encodes: `http://domain/pay?code={CODE}`
- [ ] Add QR display to Voucher Show page (similar to redemption QR)
- [ ] Add QR share panel with copy/download/email/SMS/WhatsApp options
- [ ] Reuse `useVoucherQr.ts` and `QrDisplay.vue` components

### Payment Page Enhancements
- [ ] Show voucher details on `/pay` page after code entry:
  - [ ] Target amount
  - [ ] Amount already paid (progress)
  - [ ] Remaining amount
  - [ ] Payment history (last 5 transactions)
- [ ] Add "Amount Paid" badge/indicator
- [ ] Show "Voucher Closed" message if fully paid
- [ ] Prevent overpayment if rules don't allow it

### JSON Preview Updates
- [ ] Add settlement fields to Instructions JSON Preview in generation form:
  - [ ] `voucher_type`
  - [ ] `target_amount`
  - [ ] `rules` (if configured)
- [ ] Update Deductions JSON Preview to show settlement voucher charges

## 🔧 Admin Features (Should-Have)

### Voucher State Management
- [ ] Add admin action: Lock voucher (prevent payments/redemptions)
- [ ] Add admin action: Unlock voucher
- [ ] Add admin action: Close voucher manually (mark as complete)
- [ ] Add admin action: Cancel voucher
- [ ] Create admin routes: `POST /api/v1/vouchers/{code}/lock`, `/unlock`, `/close`, `/cancel`
- [ ] Add state change audit logging
- [ ] Add permissions check (only owner or admin can change state)

### Bulk Operations
- [ ] Bulk close vouchers (select multiple from list)
- [ ] Bulk lock/unlock vouchers
- [ ] Export settlement vouchers to CSV/Excel with payment details

## 📊 Reporting & Analytics (Nice-to-Have)

### Dashboard Widgets
- [ ] Add settlement vouchers widget to dashboard:
  - [ ] Total PAYABLE vouchers created
  - [ ] Total amount collected via /pay
  - [ ] Active vs Closed settlement vouchers
- [ ] Add chart: Payment collection trend over time
- [ ] Add chart: Top settlement vouchers by amount collected

### Reports
- [ ] Settlement Voucher Report page:
  - [ ] List all settlement vouchers with payment status
  - [ ] Filter by: type, state, date range
  - [ ] Show: target amount, paid amount, remaining, % complete
  - [ ] Export to CSV/PDF
- [ ] Payment Collection Report:
  - [ ] All payments received via `/pay`
  - [ ] Group by voucher code
  - [ ] Show payer details (if available)

## 🧪 Testing & Quality

### Automated Tests
- [ ] Integration test: Full payment flow (initiate → webhook → credit wallet)
- [ ] Test: Payment with amount > remaining (should reject if rules don't allow)
- [ ] Test: Payment when voucher is locked (should reject)
- [ ] Test: Payment when voucher is closed (should reject)
- [ ] Test: Payment when voucher is expired (should reject)
- [ ] Test: Auto-close when remaining ≤ ₱0.01
- [ ] Test: Voucher state transitions (active → locked → active → closed)

### Manual Testing Checklist
- [ ] Generate PAYABLE voucher via UI → verify DB columns
- [ ] Generate SETTLEMENT voucher via UI → verify DB columns
- [ ] Make payment via `/pay` → verify wallet credit
- [ ] Make multiple payments → verify progressive payment tracking
- [ ] Pay exact target amount → verify auto-close
- [ ] Test payment QR code → scan and pay
- [ ] Test webhook idempotency (send same payment_id twice)
- [ ] Test payment with expired voucher (should reject)
- [ ] Test voucher show page displays all settlement fields

## 📝 Documentation

### User Documentation
- [ ] Create user guide: "How to Create Settlement Vouchers"
- [ ] Create user guide: "How to Accept Payments via QR Code"
- [ ] Add settlement voucher examples to docs
- [ ] Document voucher state lifecycle diagram

### Technical Documentation
- [ ] Update API documentation with settlement voucher endpoints
- [ ] Document settlement voucher database schema
- [ ] Document payment webhook payload for settlement vouchers
- [ ] Add settlement voucher examples to Postman collection
- [ ] Update `docs/SETTLEMENT_TESTING_GUIDE.md` with new scenarios

### Code Documentation
- [ ] Add PHPDoc comments to settlement-related methods
- [ ] Document `PopulateSettlementFields` pipeline stage
- [ ] Document settlement rules structure in config
- [ ] Add inline comments explaining settlement logic

## 🚀 Deployment Prep

### Configuration
- [ ] Add settlement voucher config to `.env.example`
- [ ] Document feature flag activation process
- [ ] Create migration guide for existing installations
- [ ] Add default settlement rules to production config

### Rollout Plan
- [ ] Week 1: Internal testing with team
- [ ] Week 2: Beta release (25% of users with feature flag)
- [ ] Week 3: Gradual rollout (50% → 75% → 100%)
- [ ] Month 2-3: Monitor and gather feedback
- [ ] Month 4: Full release (remove feature flag option)

## 🐛 Known Issues / Edge Cases

### To Address
- [ ] Handle race condition: simultaneous payments to same voucher
- [ ] Handle partial payments (e.g., pay ₱100 towards ₱500 target)
- [ ] Handle overpayment scenarios (if rules allow)
- [ ] Handle negative remaining amount edge case
- [ ] Handle voucher expiration during active payment session
- [ ] Handle webhook timeout/retry logic for failed payments

## 🎨 UI/UX Improvements (Polish)

### Visual Enhancements
- [ ] Add progress bar showing payment completion percentage
- [ ] Add confetti animation when voucher reaches 100% paid
- [ ] Add color-coded state badges (green=active, yellow=locked, red=closed)
- [ ] Add icons for voucher types (💰 redeemable, 💳 payable, 📋 settlement)
- [ ] Improve mobile responsiveness of payment page

### User Experience
- [ ] Add payment confirmation modal before submitting
- [ ] Add "Recent Payments" widget on voucher show page
- [ ] Add "Share Payment Link" button with copy-to-clipboard
- [ ] Add payment success toast notification
- [ ] Add payment history timeline view

---

## Priority Levels

**🔴 Critical (Do First)**:
- Voucher Show page updates
- Payment QR code generation
- JSON preview updates
- Transaction view updates

**🟡 Important (Do Soon)**:
- Admin state management actions
- Payment page enhancements
- Testing suite completion

**🟢 Nice to Have (Can Wait)**:
- Dashboard widgets
- Reports
- Advanced analytics
- UI polish

---

## Estimated Effort

- **Critical tasks**: ~8-12 hours
- **Important tasks**: ~6-8 hours
- **Nice to have**: ~10-15 hours
- **Total**: ~24-35 hours of development work

---

## Completed (Phase 1)

- [x] Database migration with settlement columns
- [x] VoucherType and VoucherState enums
- [x] Domain guards (canAcceptPayment, canRedeem, etc.)
- [x] Computed methods (getPaidTotal, getRedeemedTotal, getRemaining)
- [x] PayVoucherController with quote/generateQr endpoints
- [x] Pay UI page with 2-step flow
- [x] NetBank webhook integration
- [x] VoucherInstructionsData with settlement fields
- [x] PopulateSettlementFields pipeline stage
- [x] Settlement voucher generation UI (type selector + target amount)
- [x] Backend API validation and processing
- [x] Settlement voucher pricing configuration
- [x] Feature flag integration (settlement-vouchers)
- [x] Comprehensive test suite (10/10 tests passing)
