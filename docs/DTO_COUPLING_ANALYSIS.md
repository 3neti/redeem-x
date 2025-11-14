# DTO Coupling Analysis

## Executive Summary

**Finding**: The current DTOs (`DisburseInputData` and `DisburseResponseData`) are **NOT truly gateway-specific** despite being in the `Data/Netbank/` folder. They represent **generic disbursement concepts** that would work for any payment gateway.

**Conclusion**: The DTOs are **poorly organized but architecturally sound**. They should be **moved** to a generic location, not abstracted with interfaces.

**Impact on Plug-and-Play Plan**: Minimal - just needs **Phase 0** to relocate DTOs before implementing gateway name resolution.

---

## Detailed Analysis

### 1. DTO Structure Review

#### DisburseInputData (13 lines of properties)
```php
public function __construct(
    public string      $reference,      // Generic: transaction reference
    public int|float   $amount,         // Generic: money amount
    public string      $account_number, // Generic: recipient account
    public string      $bank,           // Generic: bank code (BIC/SWIFT)
    public string      $via             // Generic: settlement rail (INSTAPAY/PESONET)
) {}
```

**Assessment**: ✅ **100% Generic**
- No NetBank-specific fields (no API keys, merchant IDs, etc.)
- All properties are standard banking concepts
- Would work for BDO, UnionBank, any bank disbursement

#### DisburseResponseData (14 lines)
```php
public function __construct(
    public string $uuid,           // Generic: internal transaction UUID
    public string $transaction_id, // Generic: gateway transaction ID
    public string $status,         // Generic: transaction status
) {}
```

**Assessment**: ✅ **100% Generic**
- Minimal response fields common to all gateways
- No NetBank-specific data structures

---

### 2. Usage Pattern Analysis

#### PaymentGatewayInterface (Lines 7-8, 31)
```php
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;

public function disburse(Wallet $user, DisburseInputData|array $validated): DisburseResponseData|bool;
```

**Problem**: ❌ Interface imports from `Data\Netbank\` namespace
- Creates **perceived coupling** (looks NetBank-specific)
- Violates interface design principle (should depend on abstractions)
- Would require code changes when adding BDO driver

#### OmnipayBridge (Lines 84-162)
```php
public function disburse(Wallet $wallet, DisburseInputData|array $validated): DisburseResponseData|bool
{
    // Accepts DisburseInputData
    $data = $validated instanceof DisburseInputData ? $validated->toArray() : $validated;
    
    // ...generic disbursement logic...
    
    // Returns DisburseResponseData
    return DisburseResponseData::from([
        'uuid' => $transaction->uuid,
        'transaction_id' => $response->getOperationId(),
        'status' => $response->getStatus(),
    ]);
}
```

**Assessment**: ✅ Logic is gateway-agnostic
- Extracts generic array from DTO
- Maps generic gateway response to DTO
- No NetBank-specific transformations

#### DisburseCash Pipeline (Lines 32, 50)
```php
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;

$disburseInput = DisburseInputData::fromVoucher($voucher, $settlementRail);
$response = $gateway->disburse($user, $disburseInput);
```

**Assessment**: ✅ Constructs generic data
- Builds DTO from voucher/user data
- No gateway-specific logic

---

### 3. Why DTOs Are in Netbank Folder

**Hypothesis**: Historical artifact from initial NetBank-only implementation

**Evidence**:
1. `Data/Netbank/Deposit/`, `Data/Netbank/Generate/` exist (other operations)
2. No competing `Data/BDO/` or `Data/Generic/` folders
3. DTOs were created when NetBank was the only gateway

**Verdict**: **Organizational debt**, not architectural requirement

---

### 4. Comparison with Status Enum

**Good Pattern**: `DisbursementStatus` enum (newly created)
- Located in `Enums/DisbursementStatus.php` (generic location)
- Supports multiple gateways via `fromGateway()` method
- No gateway-specific namespace pollution

**Bad Pattern**: DisburseInputData/ResponseData
- Located in `Data/Netbank/` (misleading namespace)
- **Same generic purpose** but poor location

---

## Recommendations

### Option A: Move DTOs to Generic Location (RECOMMENDED)
**Effort**: 15 minutes  
**Risk**: Low (just namespace changes)

**Changes**:
```
FROM: packages/payment-gateway/src/Data/Netbank/Disburse/DisburseInputData.php
TO:   packages/payment-gateway/src/Data/Disburse/DisburseInputData.php

FROM: packages/payment-gateway/src/Data/Netbank/Disburse/DisburseResponseData.php
TO:   packages/payment-gateway/src/Data/Disburse/DisburseResponseData.php
```

**Namespace**:
```php
// FROM
namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

// TO
namespace LBHurtado\PaymentGateway\Data\Disburse;
```

**Update imports** (7 files):
1. `PaymentGatewayInterface.php`
2. `OmnipayBridge.php`
3. `OmnipayPaymentGateway.php`
4. `DisburseCash.php` (voucher pipeline)
5. `CanDisburse.php` (NetBank trait)
6. `DisburseController.php`
7. `DisburseInputPrepared.php` (event)

**Benefits**:
- ✅ Removes misleading namespace
- ✅ No architectural changes needed
- ✅ Makes DTOs obviously generic
- ✅ Matches `DisbursementStatus` pattern

---

### Option B: Create Interface Contracts (NOT RECOMMENDED)
**Effort**: 2+ hours  
**Risk**: Medium (breaks existing code, over-engineering)

**Changes**:
```php
interface DisburseInputInterface { ... }
interface DisburseResponseInterface { ... }

class DisburseInputData implements DisburseInputInterface { ... }
class NetbankDisburseInputData extends DisburseInputData { ... }
class BdoDisburseInputData extends DisburseInputData { ... }
```

**Why NOT**:
- ❌ Over-engineering for current needs
- ❌ DTOs are already generic (no specialization needed)
- ❌ Violates YAGNI principle
- ❌ Doesn't solve the actual problem (location, not abstraction)

---

## Impact on Plug-and-Play Plan

### Original Plan Phases
1. ~~Phase 1~~: Add Gateway Name Resolution
2. ~~Phase 2~~: Create Data Enricher Registry
3. ~~Phase 3~~: Update DisbursementStatusService
4. ~~Phase 4~~: Service Provider Registration
5. ~~Phase 5~~: Documentation

### Updated Plan with Phase 0

**NEW Phase 0**: Relocate DTOs to Generic Location (15 min)
- Move `DisburseInputData` and `DisburseResponseData`
- Update 7 import statements
- Run tests to verify

**Phase 1**: Add Gateway Name Resolution (5 min)
- No changes needed (same as before)

**Phase 2-5**: Proceed as planned
- No impact from DTO relocation

---

## Testing Strategy

### After DTO Relocation
```bash
# Run full test suite
php artisan test

# Specifically test disbursement flow
php artisan test --filter DisburseControllerTest
php artisan test --filter NetbankPaymentGatewayTest

# Test status tracking
php artisan disbursement:update-status --voucher=E4JE
```

### After Plug-and-Play Implementation
```bash
# Test gateway switching
PAYMENT_GATEWAY=netbank php artisan test
PAYMENT_GATEWAY=bdo php artisan test  # (when BDO driver exists)
```

---

## Conclusion

The DTOs are **generic in substance but NetBank-specific in location**. This is a **naming/organization issue**, not an architectural flaw.

**Solution**: Move DTOs to `Data/Disburse/` (generic location) as **Phase 0** before implementing plug-and-play.

**Result**: Clean architecture that supports any payment gateway without abstraction overhead.

---

## Next Steps

1. ✅ Present findings to user
2. ⏳ Get approval for Option A (Move DTOs)
3. ⏳ Implement Phase 0 (DTO relocation)
4. ⏳ Proceed with original Plug-and-Play Plan (Phases 1-5)
5. ⏳ Test with NetBank (existing)
6. ⏳ Document gateway addition guide for future BDO/UnionBank drivers
