# Step 7: Integration Tests - Complete End-to-End Flows

## Overview

Comprehensive integration tests covering complete voucher generation and redemption workflows.

**Total Tests**: 30 test cases across 2 files
- **VoucherGenerationFlowTest.php**: 13 tests (279 lines)
- **VoucherRedemptionFlowTest.php**: 17 tests (514 lines)

---

## Test Files

### 1. VoucherGenerationFlowTest.php (13 tests)

Tests for voucher generation using `GenerateVouchers` action and `Vouchers` facade.

#### Core Generation Tests
- ✅ **GenerateVouchers action returns LBHurtado Voucher instances**
  - Verifies facade returns correct model type (`LBHurtado\Voucher\Models\Voucher`)
  
- ✅ **Generate vouchers with minimal configuration**
  - 10 vouchers with basic cash config
  - Validates amount, currency, expiry dates

- ✅ **Generate vouchers with all configuration options**
  - Full configuration: cash limits, input fields, feedback channels, rider
  - Validates all instruction data

- ✅ **Generate vouchers with custom prefix and mask**
  - Tests code format (e.g., `TEST****-****`)
  - Validates regex pattern matching

- ✅ **Generate vouchers ensures unique codes**
  - Creates 100 vouchers
  - Verifies no duplicate codes

- ✅ **Generate vouchers with zero TTL creates non-expiring vouchers**
  - `ttl_days: 0` → `expires_at: null`

- ✅ **Generate vouchers requires authenticated user**
  - Throws exception when no user authenticated

- ✅ **Generate vouchers stores instructions in metadata**
  - Verifies instructions accessible via `$voucher->instructions`

#### Advanced Generation Tests
- ✅ **Generate vouchers that start in the future**
  - `withStartTime(now()->addDays(7))`
  - Validates `starts_at` is future date
  - Expires 30 days after start date

- ✅ **Generate vouchers with extended expiry date**
  - `ttl_days: 90` → 90 days expiry
  - Validates expiry calculation

- ✅ **Generate vouchers with success message configuration**
  - Custom rider message
  - Validates message in instructions

- ✅ **Generate vouchers with custom rider redirect URL**
  - Custom redirect URL in rider config
  - Validates URL in instructions

- ✅ **Generate vouchers with default rider configuration from config**
  - Uses system defaults when no rider specified
  - Validates rider object exists

---

### 2. VoucherRedemptionFlowTest.php (17 tests)

Tests for complete redemption workflows including validation, session management, and edge cases.

#### Helper Function
```php
createTestVoucher(User $user, array $fields = [], array $cash = null): Voucher
```
- Creates test voucher with specified fields
- Verifies facade returns correct model type
- Default: 500 PHP, no input fields

#### Complete Flow Tests
- ✅ **Complete redemption flow with all plugins**
  - All input fields + signature
  - 5-step flow: start → wallet → inputs → signature → finalize → confirm → success
  - Validates voucher marked as redeemed
  - Validates all inputs stored in metadata

- ✅ **Complete redemption flow with minimal fields**
  - No additional input fields
  - 3-step flow: wallet → finalize → confirm → success
  - Skips plugin steps

- ✅ **Redemption flow with only email field**
  - Single input field (EMAIL)
  - Validates email stored correctly

#### Validation Tests
- ✅ **Redemption flow validates required fields only**
  - Dynamic validation based on voucher requirements
  - Missing required field → validation error
  - Extra fields ignored gracefully

- ✅ **Redemption flow prevents double redemption**
  - Already redeemed voucher redirects with error

- ✅ **Redemption flow validates expired vouchers** (original test with sleep)
  - Creates voucher with 1-second TTL
  - Waits 2 seconds
  - Wallet submission fails

- ✅ **Redemption flow validates wrong secret**
  - Correct secret: `correct_secret`
  - Wrong secret submission → validation error

- ✅ **Redemption flow validates mobile number format**
  - Invalid format → error
  - Valid formats: `+639171234567`, `639171234567`, `09171234567`

#### Session Management Tests
- ✅ **Redemption flow session data persists across steps**
  - Validates session keys: mobile, wallet, account_number, inputs, signature
  - Data persists from wallet → inputs → signature → finalize

- ✅ **Redemption flow clears session after successful completion**
  - Session marked as redeemed: `redeem.{code}.redeemed = true`

#### Advanced Redemption Tests
- ✅ **Cannot redeem voucher that starts in the future**
  - `withStartTime(now()->addDays(7))`
  - Start redemption → redirect with error

- ✅ **Cannot redeem expired voucher**
  - `withExpireTime(now()->subDay())`
  - Start redemption → redirect with error

- ✅ **Expired voucher returns error when submitting wallet info**
  - Expired voucher
  - Wallet submission → validation error on `code` field

- ✅ **Success page displays custom rider message**
  - Custom message in rider config
  - Success page displays message via Inertia props

- ✅ **Success page redirects to custom rider URL when configured**
  - Custom redirect URL in rider config
  - Success page passes URL to frontend

- ✅ **Success page uses default configuration when no rider specified**
  - No rider config
  - Success page renders with defaults

- ✅ **Voucher with extended expiry remains valid**
  - 90 days expiry
  - Can start redemption successfully

---

## Key Patterns

### 1. Facade Verification
All tests verify that `Vouchers` facade returns `LBHurtado\Voucher\Models\Voucher`:
```php
expect($voucher)->toBeInstanceOf(Voucher::class);
expect($voucher)->toBeInstanceOf(LBHurtado\Voucher\Models\Voucher::class);
```

### 2. Instructions Data Creation
```php
$base = VoucherInstructionsData::generateFromScratch()->toArray();
$base['cash'] = ['amount' => 500, 'currency' => 'PHP'];
$base['inputs'] = ['fields' => ['email', 'name']];
$base['rider'] = ['message' => '...', 'redirect_url' => '...'];
$instructions = VoucherInstructionsData::from($base);
```

### 3. Voucher Creation with Facade
```php
$voucher = Vouchers::withMetadata([
    'instructions' => $instructions->toCleanArray(),
    'secret' => 'test-secret',
])
->withOwner($user)
->withStartTime(now()->addDays(7))
->withExpireTimeIn(\Carbon\CarbonInterval::days(30))
->create();
```

### 4. Dynamic Plugin Flow
- System determines required plugins based on voucher fields
- Routes dynamically generated: `/redeem/{code}/{plugin}`
- Session keys structured: `redeem.{code}.{plugin}`

---

## Test Coverage

### Voucher Generation
- ✅ Basic generation (count, prefix, mask)
- ✅ Unique code generation
- ✅ TTL configuration (0, 30, 90 days)
- ✅ Future start dates
- ✅ Extended expiry dates
- ✅ Rider messages
- ✅ Rider redirect URLs
- ✅ Default configuration
- ✅ Authentication requirement
- ✅ Metadata storage

### Voucher Redemption
- ✅ Complete multi-step flows
- ✅ Minimal flows (wallet only)
- ✅ Dynamic plugin selection
- ✅ Field validation
- ✅ Secret validation
- ✅ Mobile number validation
- ✅ Double redemption prevention
- ✅ Expired voucher handling
- ✅ Future voucher handling
- ✅ Session management
- ✅ Success messages
- ✅ Redirect URLs

---

## Dependencies

### Model Updates
**User Model** (`app/Models/User.php`)
- ✅ Implements `Bavix\Wallet\Interfaces\Customer`
- ✅ Uses `Bavix\Wallet\Traits\CanPay`

Required for PersistCash pipeline to escrow funds.

### Pending Issues
1. **InstructionCostEvaluator Service**
   - Not yet implemented
   - Required by voucher generation pipeline
   - Blocks test execution

---

## Running Tests

```bash
# Run all integration tests
php artisan test --filter Integration

# Run voucher generation tests
php artisan test tests/Feature/Integration/VoucherGenerationFlowTest.php

# Run redemption tests
php artisan test tests/Feature/Integration/VoucherRedemptionFlowTest.php

# Run specific test
php artisan test --filter="cannot redeem expired voucher"
```

---

## Next Steps

### To Complete Step 7:
1. ☐ Create `InstructionCostEvaluator` service
2. ☐ Run full integration test suite
3. ☐ Fix any failing tests
4. ☐ Verify all 30 tests pass

### Then Proceed to Phase 3:
- Frontend development (Vue components)
- Inertia page implementations
- UI/UX for redemption flow

---

## Statistics

| Metric | Value |
|--------|-------|
| Total Tests | 30 |
| Generation Tests | 13 |
| Redemption Tests | 17 |
| Total Lines | 793 |
| Test Coverage | High |
| Model Type Verification | Every test |

---

## Key Features Tested

### Time-Based Features
- ✅ Future start dates (`starts_at`)
- ✅ Variable expiry periods (0, 1, 30, 90 days)
- ✅ Expired voucher rejection
- ✅ Future voucher rejection

### Rider Configuration
- ✅ Custom success messages
- ✅ Custom redirect URLs
- ✅ Default configuration fallback

### Dynamic System
- ✅ Plugin-based redemption flow
- ✅ Field-based validation
- ✅ Session-based state management

### Data Integrity
- ✅ Unique code generation
- ✅ Metadata storage
- ✅ Instructions persistence
- ✅ Model type verification

---

## Conclusion

Step 7 integration tests provide comprehensive coverage of the voucher system's core workflows. The tests verify:
- Correct use of `Vouchers` facade returning `LBHurtado\Voucher\Models\Voucher`
- Dynamic plugin-based redemption
- Time-based validation (future/expired)
- Rider message and redirect configuration
- Session management
- Data persistence

All tests are ready to run once `InstructionCostEvaluator` service is implemented.
