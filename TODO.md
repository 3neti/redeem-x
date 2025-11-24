# TODO

## Package Issues

### ✅ RESOLVED: Contact/bank_account issue in the package

**Issue**: When loading VoucherData DTOs, if the voucher has a `contact` relationship (e.g., after redemption), the ContactData transformation fails because it expects a `bank_account` string but receives `null`.

**Solution Implemented**:
1. **Phase 1**: Added defensive null checks in `ContactData::fromModel()` - only accesses `bank_code` and `account_number` accessors if `bank_account` is not null
2. **Phase 2**: Added null guards in Contact model accessors (`getBankCodeAttribute()` and `getAccountNumberAttribute()`)
3. **Phase 3**: Created migration to backfill null `bank_account` values with default format (`{BANK_CODE}:{mobile}`)
4. **Phase 4**: Removed workarounds:
   - `SubmitWallet.php` now returns full `VoucherData` DTO
   - `ShowVoucher.php` now returns `ContactData` DTO for `redeemed_by`

**Files Modified**:
- `packages/contact/src/Data/ContactData.php` - Added null checks
- `packages/contact/src/Models/Contact.php` - Made accessors nullable with guards
- `database/migrations/2025_11_24_110956_backfill_contact_bank_accounts.php` - Backfill migration
- `app/Actions/Api/Redemption/SubmitWallet.php` - Now uses VoucherData DTO
- `app/Actions/Api/Vouchers/ShowVoucher.php` - Now uses ContactData DTO

**Status**: ✅ Complete - All tests passing, DTOs used consistently across API responses

---

## API Enhancements

### Phase 5 Remaining Stub Actions

The following stub actions exist but are not needed for the current stateless redemption flow:
- `StartRedemption` - Not needed (SubmitWallet handles complete redemption)
- `SubmitPlugin` - Not needed for basic flow
- `ConfirmRedemption` - Not needed (SubmitWallet executes immediately)
- `FinalizeRedemption` - Not needed (SubmitWallet is final)
- `GetRedemptionStatus` - Could be useful for checking redemption history

**Decision**: Keep stubs for future multi-step or plugin-based redemption flows.

---

## Code Consistency

### Use DTOs Consistently in All API Responses

**Current State**: 90% of API responses use lbhurtado package DTOs (VoucherData, ContactData)

**Exceptions** (due to bank_account issue):
- `SubmitWallet.php` - Returns array instead of VoucherData
- `ShowVoucher.php` - Returns redeemed_by as array instead of ContactData

**Goal**: Once contact/bank_account issue is fixed, update these to use DTOs consistently.

---

## Testing

### Add Integration Tests for Complete Flows

**Current**: Unit/feature tests for individual API endpoints (49 tests passing)

**Needed**: 
- End-to-end flow tests (generate → redeem → verify)
- Multi-voucher redemption scenarios
- Payment gateway integration tests
- Webhook/feedback notification tests
