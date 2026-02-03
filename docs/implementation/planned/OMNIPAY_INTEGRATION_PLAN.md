# Omnipay Integration Plan - Wiring into Redeem-X

**Date:** November 14, 2025  
**Status:** 📋 Planning Phase  
**Goal:** Integrate new Omnipay payment gateway implementation into redeem-x voucher redemption flow

---

## Overview

This document outlines the plan to wire the newly implemented Omnipay-based payment gateway into the redeem-x application, replacing the direct API call implementation from x-change while maintaining backward compatibility.

---

## Current State Analysis

### Existing Implementation (x-change style)

**Location:** `packages/payment-gateway/src/Gateways/Netbank/NetbankPaymentGateway.php`

**Characteristics:**
- Uses direct HTTP calls via Laravel `Http` facade
- OAuth2 handled manually in `getAccessToken()` method
- Traits: `CanConfirmDeposit`, `CanDisburse`, `CanGenerate`
- No settlement rail validation
- No EMI detection
- No KYC address workaround

**Flow:**
```
NetbankPaymentGateway::disburse()
    ↓
CanDisburse trait
    ↓
Http::post() with Bearer token
    ↓
DisburseResponseData returned
```

### New Implementation (Omnipay)

**Location:** `packages/payment-gateway/src/Omnipay/Netbank/`

**Characteristics:**
- Uses League Omnipay framework
- OAuth2 via `HasOAuth2` trait with token caching
- Settlement rail validation via `ValidatesSettlementRail` trait
- EMI detection via `BankRegistry`
- KYC workaround via `AppliesKycWorkaround` trait
- Request/Response abstraction

**Flow:**
```
OmnipayBridge::disburse()
    ↓
Gateway::disburse() (Omnipay)
    ↓
DisburseRequest with traits (OAuth2, Validation, KYC)
    ↓
DisburseResponse
    ↓
DisburseResponseData returned
```

### Current Configuration

**Environment Variables:**
```bash
DISBURSE_DISABLE=true          # Currently disabled
USE_OMNIPAY=true               # Flag for new implementation
PAYMENT_GATEWAY=netbank        # Gateway selection
```

**Service Provider Binding:**
```php
// packages/payment-gateway/src/PaymentGatewayServiceProvider.php
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    $concrete = config('payment-gateway.gateway', NetbankPaymentGateway::class);
    return $app->make($concrete);
});
```

Currently always binds to `NetbankPaymentGateway` (old implementation).

---

## Architecture Overview

### Voucher Redemption Flow

```
User redeems voucher
    ↓
ProcessRedemption action
    ↓
RedeemVoucher::run() marks voucher as redeemed
    ↓
VoucherObserver fires VoucherRedeemed event
    ↓
HandleRedeemedVoucher listener
    ↓
Pipeline (config/voucher-pipeline.php 'post-redemption')
    ↓
1. ValidateRedeemerAndCash
2. PersistInputs
3. DisburseCash (if DISBURSE_DISABLE=false) ← WE ARE HERE
4. SendFeedbacks
    ↓
DisbursementRequested event fired
```

### DisburseCash Pipeline Stage

**Location:** `packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php`

**Dependencies:**
- Injects `PaymentGatewayInterface` via constructor
- Expects: `disburse(Wallet $wallet, DisburseInputData $input): DisburseResponseData|bool`

**Process:**
```php
1. Creates DisburseInputData::fromVoucher($voucher)
2. Fires DisburseInputPrepared event
3. Calls $this->gateway->disburse($voucher->cash, $input)
4. Expects DisburseResponseData or false
5. Logs result
6. Continues pipeline on success
```

### Interface Contract

**Required by `DisburseCash`:**
```php
interface PaymentGatewayInterface
{
    public function disburse(
        Wallet $wallet, 
        DisburseInputData|array $validated
    ): DisburseResponseData|bool;
    
    // Other methods...
}
```

---

## Implementation Plan

### Task 1: Create OmnipayPaymentGateway Class

**File:** `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`

**Purpose:** Adapter that implements `PaymentGatewayInterface` using Omnipay framework

**Structure:**
```php
<?php

namespace LBHurtado\PaymentGateway\Gateways\Omnipay;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseInputData,
    DisburseResponseData
};
use Omnipay\Common\GatewayInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Brick\Money\Money;
use Illuminate\Support\Facades\{DB, Log};

class OmnipayPaymentGateway implements PaymentGatewayInterface
{
    protected GatewayInterface $gateway;
    
    public function __construct()
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        $this->gateway = OmnipayFactory::make($gatewayName);
    }
    
    public function disburse(
        Wallet $wallet, 
        DisburseInputData|array $validated
    ): DisburseResponseData|bool {
        // Convert DisburseInputData to array if needed
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;
        
        // Validate required fields
        // Convert amounts
        // Call Omnipay gateway
        // Handle response
        // Return DisburseResponseData or false
    }
    
    public function generate(string $account, Money $amount): string
    {
        // Implement QR generation
    }
    
    public function confirmDeposit(array $payload): bool
    {
        // Implement deposit confirmation
    }
    
    public function confirmDisbursement(string $operationId): bool
    {
        // Implement disbursement confirmation
    }
}
```

**Key Considerations:**
- Must match the exact signature expected by `DisburseCash`
- Should leverage existing `DisburseRequest` and `DisburseResponse` from Omnipay implementation
- Needs to handle `DisburseInputData::fromVoucher()` output format
- Must properly handle wallet transactions (withdraw, confirm)

### Task 2: Update PaymentGatewayServiceProvider

**File:** `packages/payment-gateway/src/PaymentGatewayServiceProvider.php`

**Changes:**
```php
public function register(): void
{
    // ... existing config merges ...
    
    $this->app->bind(PaymentGatewayInterface::class, function ($app) {
        // Check if we should use Omnipay implementation
        $useOmnipay = filter_var(
            env('USE_OMNIPAY', false), 
            FILTER_VALIDATE_BOOLEAN
        );
        
        if ($useOmnipay) {
            return $app->make(
                \LBHurtado\PaymentGateway\Gateways\Omnipay\OmnipayPaymentGateway::class
            );
        }
        
        // Fall back to old direct API implementation
        $concrete = config(
            'payment-gateway.gateway', 
            \LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway::class
        );
        return $app->make($concrete);
    });
}
```

**Benefits:**
- Seamless switching via `USE_OMNIPAY` flag
- Maintains backward compatibility
- Easy rollback if issues arise
- Can A/B test implementations

### Task 3: Verify DisburseInputData::fromVoucher()

**File:** `packages/payment-gateway/src/Data/Netbank/Disburse/DisburseInputData.php`

**Required Fields:**
- `amount` - Cash amount in pesos (e.g., 100.00)
- `account_number` - Recipient's account/mobile number
- `bank` - Bank SWIFT code (e.g., GXCHPHM2XXX)
- `via` - Settlement rail (INSTAPAY or PESONET)
- `reference` - Unique reference code

**Verification Points:**
1. Check that `fromVoucher()` method exists
2. Verify it extracts all required fields from voucher
3. Ensure bank account info is parsed from voucher metadata
4. Confirm settlement rail is determined correctly
5. Test with sample voucher data

**Expected Format:**
```php
DisburseInputData {
    amount: 100.00,
    account_number: "09173011987",
    bank: "GXCHPHM2XXX",
    via: "INSTAPAY",
    reference: "REF-123456"
}
```

### Task 4: Testing Integration

**Test Scenarios:**

#### 4.1 Unit Tests
```php
// Test OmnipayPaymentGateway implements interface correctly
test('omnipay gateway implements interface', function () {
    $gateway = app(PaymentGatewayInterface::class);
    expect($gateway)->toBeInstanceOf(OmnipayPaymentGateway::class);
});

// Test disburse accepts DisburseInputData
test('disburse accepts input data', function () {
    $gateway = app(PaymentGatewayInterface::class);
    $wallet = User::factory()->create();
    $input = DisburseInputData::from([...]);
    
    $response = $gateway->disburse($wallet, $input);
    expect($response)->toBeInstanceOf(DisburseResponseData::class);
});
```

#### 4.2 Integration Tests
```bash
# Step 1: Ensure USE_OMNIPAY=true in .env
grep USE_OMNIPAY .env  # Should show true

# Step 2: Keep DISBURSE_DISABLE=true for now
grep DISBURSE_DISABLE .env  # Should show true

# Step 3: Test gateway binding
php artisan tinker
>>> app(\LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface::class)
# Should return OmnipayPaymentGateway instance

# Step 4: Generate test voucher
php artisan voucher:generate 100 1 --prefix=TEST

# Step 5: Check voucher has cash entity
php artisan tinker
>>> \LBHurtado\Voucher\Models\Voucher::where('code', 'like', 'TEST%')->first()->cash
# Should show cash entity with amount

# Step 6: Enable disbursement
# Change DISBURSE_DISABLE=false in .env

# Step 7: Redeem voucher (via web UI or API)
# This should trigger the full pipeline including DisburseCash

# Step 8: Verify disbursement
# Check logs: storage/logs/laravel.log
# Look for: [DisburseCash] Starting, [DisburseCash] Success
# Check transaction created in bavix_wallet_transactions table
```

#### 4.3 End-to-End Test
```bash
# Full voucher lifecycle with Omnipay disbursement
php artisan test:voucher-redemption-with-disbursement
```

**Success Criteria:**
- ✅ Gateway binding resolves to `OmnipayPaymentGateway`
- ✅ `DisburseInputData::fromVoucher()` returns valid data
- ✅ Disbursement goes through Omnipay framework
- ✅ OAuth2 token obtained via `HasOAuth2` trait
- ✅ Settlement rail validated via `ValidatesSettlementRail`
- ✅ NetBank API called with correct payload
- ✅ `DisburseResponseData` returned with transaction_id
- ✅ Transaction logged in database
- ✅ DisbursementRequested event fired
- ✅ No errors in logs

### Task 5: Documentation and Commit

**Files to Update:**

#### 5.1 WARP.md
Add section about payment gateway configuration:
```markdown
### Payment Gateway Configuration

**Dual Gateway Support:**
- Old implementation: Direct API calls (x-change style)
- New implementation: Omnipay framework (production-ready)

**Environment Variables:**
```bash
USE_OMNIPAY=true|false        # Switch between implementations
PAYMENT_GATEWAY=netbank       # Gateway selection
DISBURSE_DISABLE=true|false   # Enable/disable disbursement
```

**Recommended for production:** `USE_OMNIPAY=true`

**Benefits of Omnipay:**
- Settlement rail validation
- EMI detection (GCash, PayMaya)
- KYC address workaround
- Better error handling
- Comprehensive testing via Artisan commands
```

#### 5.2 Create GATEWAY_MIGRATION.md
Document migration from old to new implementation:
- Breaking changes (if any)
- Configuration differences
- Testing checklist
- Rollback procedure

#### 5.3 Update IMPLEMENTATION_SUMMARY.md
Add section about integration with voucher redemption flow.

#### 5.4 Commit Message
```
Integrate Omnipay payment gateway into voucher redemption flow

- Created OmnipayPaymentGateway adapter implementing PaymentGatewayInterface
- Updated PaymentGatewayServiceProvider to conditionally bind gateway based on USE_OMNIPAY flag
- Verified DisburseInputData::fromVoucher() provides required fields for Omnipay
- Added comprehensive tests for gateway switching and disbursement flow
- Documented dual-gateway architecture and migration path

Features:
✅ Seamless switching between old (direct API) and new (Omnipay) implementations
✅ Backward compatible with existing x-change style gateway
✅ Production-ready with enhanced validation and error handling
✅ Full integration with voucher redemption pipeline

To enable: Set USE_OMNIPAY=true and DISBURSE_DISABLE=false in .env
```

---

## Key Differences: Old vs New

| Aspect | Old (NetbankPaymentGateway) | New (OmnipayPaymentGateway) |
|--------|----------------------------|----------------------------|
| **Framework** | Direct HTTP calls | Omnipay abstraction |
| **OAuth2** | Manual `getAccessToken()` | `HasOAuth2` trait with caching |
| **Settlement Rail** | No validation | `ValidatesSettlementRail` trait |
| **EMI Detection** | Not implemented | Via `BankRegistry` |
| **Bank Validation** | None | Checks if bank supports rail |
| **Amount Limits** | Config-based | Per-rail validation |
| **KYC Workaround** | None | `AppliesKycWorkaround` trait |
| **Address Generation** | None | Random PH addresses for testing |
| **Error Handling** | Basic HTTP status | Structured response parsing |
| **Testing** | Manual curl | Artisan commands |
| **Token Caching** | None | Cached until expiry |
| **Request Logging** | Basic | Comprehensive audit trail |

---

## Risk Assessment

### Low Risk
- ✅ New implementation already tested independently
- ✅ Environment flag allows instant rollback
- ✅ Old implementation remains untouched
- ✅ Interface contract is clearly defined

### Medium Risk
- ⚠️ `DisburseInputData::fromVoucher()` format assumptions
- ⚠️ Wallet transaction handling differences
- ⚠️ Event firing order/timing

### Mitigation Strategies
1. Test extensively with `DISBURSE_DISABLE=true` first
2. Use small test amounts initially
3. Monitor logs closely during first production redemptions
4. Keep `USE_OMNIPAY=false` option ready for rollback
5. Test with both INSTAPAY and PESONET rails

---

## Rollback Plan

If issues arise after enabling `USE_OMNIPAY=true`:

```bash
# Step 1: Disable new implementation
# Change .env:
USE_OMNIPAY=false

# Step 2: Clear config cache
php artisan config:clear
php artisan config:cache

# Step 3: Verify binding
php artisan tinker
>>> app(\LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface::class)
# Should now return NetbankPaymentGateway

# Step 4: Test disbursement
# Generate and redeem test voucher

# Step 5: Monitor logs
tail -f storage/logs/laravel.log
```

**Recovery Time:** < 2 minutes

---

## Success Metrics

### Phase 1: Integration (Pre-Production)
- [ ] `OmnipayPaymentGateway` class created
- [ ] Service provider binding updated
- [ ] Unit tests passing
- [ ] Integration tests passing
- [ ] Documentation updated
- [ ] Code reviewed and committed

### Phase 2: Testing (Staging)
- [ ] Gateway binding works correctly
- [ ] Test voucher disbursement succeeds
- [ ] Logs show Omnipay flow
- [ ] Transaction recorded in database
- [ ] Events fire correctly
- [ ] Both INSTAPAY and PESONET tested

### Phase 3: Production Enablement
- [ ] `DISBURSE_DISABLE=false` set
- [ ] First production disbursement successful
- [ ] Monitoring in place
- [ ] No errors in logs
- [ ] Performance acceptable
- [ ] Users receive funds

---

## Timeline Estimate

| Phase | Tasks | Duration |
|-------|-------|----------|
| **1. Development** | Create OmnipayPaymentGateway, Update provider | 2-3 hours |
| **2. Verification** | Check DisburseInputData, Unit tests | 1 hour |
| **3. Integration Testing** | End-to-end flow tests | 1-2 hours |
| **4. Documentation** | Update docs, WARP.md | 1 hour |
| **5. Staging Tests** | Full redemption cycle | 1 hour |
| **6. Production Enable** | Flip flag, monitor | 30 min |

**Total:** ~6-8 hours

---

## Next Steps

1. ✅ Document plan (this file)
2. ⏳ Implement `OmnipayPaymentGateway` class
3. ⏳ Update `PaymentGatewayServiceProvider`
4. ⏳ Verify `DisburseInputData::fromVoucher()`
5. ⏳ Run integration tests
6. ⏳ Update documentation
7. ⏳ Commit changes
8. ⏳ Enable `DISBURSE_DISABLE=false`
9. ⏳ Monitor production

---

**Status:** 📋 Ready for Implementation  
**Blocker:** None  
**Dependencies:** All prerequisites met (Omnipay already implemented and tested)  
**Approval:** Pending

---

**Last Updated:** November 14, 2025  
**Next Review:** After implementation completion
