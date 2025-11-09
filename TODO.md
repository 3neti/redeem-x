# TODO

## Package Issues

### Fix the Contact/bank_account issue in the package

**Issue**: When loading VoucherData DTOs, if the voucher has a `contact` relationship (e.g., after redemption), the ContactData transformation fails because it expects a `bank_account` string but receives `null`.

**Current Workaround**: In `SubmitWallet` action, we return a simplified array instead of VoucherData DTO after redemption to avoid loading the contact relationship.

**Affected Files**:
- `app/Actions/Api/Redemption/SubmitWallet.php` (lines 92-97)
- `packages/contact/src/Data/ContactData.php`
- `packages/contact/src/Classes/BankAccount.php`

**Root Cause**: 
- `Contact::fromPhoneNumber()` creates contacts with mobile and country only
- The `booted()` method should set default bank_account, but it seems to fail in some edge cases
- `ContactData::fromModel()` tries to call `BankAccount::fromBankAccount()` which requires a non-null string

**Potential Solutions**:
1. Ensure `Contact::booted()` always sets bank_account before model is saved
2. Make `BankAccount::fromBankAccount()` accept nullable string and return null
3. Add null check in ContactData before transforming bank_account
4. Make bank_account optional in ContactData

**Impact**: Medium - Currently prevents full DTO usage in redemption responses, requires manual property access in some places.

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
