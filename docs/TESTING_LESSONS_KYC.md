# Testing Lessons: KYC Input Field Bug

## The Bug

**Issue**: When a voucher with KYC enabled was redeemed, the Inputs page showed "Kyc" as a text input field, asking the user to type a value instead of redirecting them to the HyperVerge identity verification flow.

**Expected Behavior**: KYC should be handled like `location`, `selfie`, and `signature` - as special flows with dedicated pages/buttons, not as text inputs.

**Root Cause**: `Inputs.vue` line 36 filtered out `['location', 'selfie', 'signature']` but forgot to include `'kyc'`.

```typescript
// ❌ BUG - KYC shown as text input
.filter((field: string) => !['location', 'selfie', 'signature'].includes(field));

// ✅ FIX - KYC skipped on Inputs page
.filter((field: string) => !['location', 'selfie', 'signature', 'kyc'].includes(field));
```

## Why Our Tests Didn't Catch This

### Backend Tests ✅ (What We Had)

Our integration tests in `tests/Feature/Integration/KYCRedemptionFlowTest.php` validated:
- ✅ KYC data stored correctly in Contact model (schemaless attributes)
- ✅ `ProcessRedemption` blocks redemption without KYC approval
- ✅ `ProcessRedemption` allows redemption with KYC approval
- ✅ KYC validation logic works correctly
- ✅ KYC reuse across multiple voucher redemptions

**Why they passed despite the bug**: These tests called `ProcessRedemption::run()` directly, bypassing the entire frontend UI flow.

```php
// Our tests did this - skips frontend completely
$result = ProcessRedemption::run($voucher, $phoneNumber, [], []);
// ✅ Backend logic works perfectly
// ❌ Frontend bug not detected
```

### Frontend Tests ❌ (What We Missed)

**Missing**: Browser-based E2E tests that actually navigate through the UI:

```php
// What we SHOULD have tested (Laravel Dusk)
$browser->visit('/redeem')
    ->type('code', $voucher->code)
    ->press('Continue')
    ->type('mobile', '09171234567')
    ->press('Continue')
    // ⚠️ This would have caught the bug!
    ->assertDontSee('Identity Verification (KYC)') // Should NOT see on Inputs page
    ->assertSee('Email Address')
    ->type('email', 'test@example.com')
    ->press('Continue')
    // Should see KYC on Finalize page
    ->assertSee('Start Identity Verification');
```

## The Testing Gap

### Test Pyramid

```
        /\
       /UI\        ← Missing! (Frontend E2E tests)
      /────\
     /  API \      ← Partial (didn't test full flow)
    /────────\
   / Unit/Int \   ← Good! (Backend logic tests)
  /────────────\
```

**What happened**: We had solid **backend tests** (Unit/Integration) but no **UI tests** (E2E with browser).

## Lessons Learned

### 1. Backend Tests ≠ Full Coverage

Backend tests validate **business logic** but miss **UX bugs**:
- Does the UI show the right fields?
- Does navigation flow correctly?
- Are buttons enabled/disabled properly?
- Does the user experience match the design?

### 2. Test What The User Sees

The user doesn't call `ProcessRedemption::run()` - they:
1. Visit `/redeem`
2. Type voucher code
3. Navigate through pages
4. Click buttons
5. See success/error messages

**Our tests should mirror this journey.**

### 3. Critical User Journeys Need E2E Tests

For critical flows like redemption, add browser tests that verify:
- ✅ Correct pages shown in correct order
- ✅ Required fields appear/disappear based on voucher config
- ✅ Special inputs (location, selfie, signature, KYC) handled correctly
- ✅ Navigation works (back buttons, continue buttons)
- ✅ Error messages display properly

### 4. Integration Points Are Risky

The bug occurred at an **integration point** between:
- Backend: `VoucherInputField::KYC` enum
- Frontend: `Inputs.vue` filtering logic

**Risk areas**: Anywhere frontend and backend must agree on behavior but aren't type-checked together.

## Recommended Testing Strategy

### For Future Features

1. **Unit Tests** (backend) - Test business logic in isolation
2. **Integration Tests** (backend) - Test action/controller flows
3. **Component Tests** (frontend) - Test Vue components in isolation
4. **E2E Tests** (browser) - Test critical user journeys end-to-end

### Specifically for Input Fields

When adding new special input types (like KYC, location, selfie):

**Backend checklist**:
- [ ] Add enum to `VoucherInputField`
- [ ] Add label to `VoucherInputField::label()`
- [ ] Add validation in `ProcessRedemption` (if needed)

**Frontend checklist**:
- [ ] Add to exclusion list in `Inputs.vue` if NOT a text field
- [ ] Create dedicated page/handler if special flow (like `KYCStatus.vue`)
- [ ] Update `Finalize.vue` if special handling needed
- [ ] **Write browser test** to verify field doesn't show as text input

**E2E test**:
```php
public function test_new_special_field_not_shown_as_text_input()
{
    $voucher = createVoucherWith(['inputs' => ['fields' => ['new_field']]]);
    
    $this->browse(function (Browser $browser) use ($voucher) {
        $browser->visit('/redeem')
            ->fillRedemptionFlow($voucher)
            ->assertDontSee('New Field') // on Inputs page
            ->assertSee('Special Handler'); // on appropriate page
    });
}
```

## Browser Test Setup

To run the browser tests created in `tests/Browser/KYCRedemptionUiTest.php`:

```bash
# Install Laravel Dusk (if not already installed)
composer require --dev laravel/dusk

# Install ChromeDriver
php artisan dusk:chrome-driver

# Run browser tests
php artisan dusk
```

These tests would have immediately caught the KYC text input bug!

## Summary

**The Gap**: Backend tests validated logic, but didn't verify UI behavior.

**The Fix**: Add browser-based E2E tests for critical user journeys.

**The Lesson**: Test what the user actually experiences, not just what the code does internally.

**Action Item**: For any feature with user-facing behavior, add at least one E2E test that verifies the complete user journey.
