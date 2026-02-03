# Gateway Plug-and-Play Implementation Plan

**Goal:** Make the payment gateway system truly plug-and-play so changing `PAYMENT_GATEWAY=netbank` to `PAYMENT_GATEWAY=bdo` requires zero code changes.

**Status:** 90% complete - 3 issues need fixing: DTO location + 2 hardcoded references

---

## Current Issues

### Issue #0: DTO Coupling (New Discovery)
**Files:** 
- `packages/payment-gateway/src/Data/Netbank/Disburse/DisburseInputData.php`
- `packages/payment-gateway/src/Data/Netbank/Disburse/DisburseResponseData.php`

**Problem:**
```php
// ❌ MISLEADING NAMESPACE
namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;  // Interface imports from Netbank!
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;
```

**Analysis:**
- DTOs are **100% generic in substance** (just reference, amount, account_number, bank, via)
- **No NetBank-specific fields** - would work for any gateway
- **Wrong location** - historical artifact from when NetBank was the only gateway
- Creates **perceived coupling** - looks NetBank-specific when it's not

**Impact:** 
- Adding BDO driver would seem to require NetBank DTOs (confusing)
- Interface depends on gateway-specific namespace (violates design principles)
- New developers would think they need to create BDO-specific DTOs (unnecessary)

**Solution:** Move to generic location `Data/Disburse/` (see Phase 0)

**Reference:** See `docs/DTO_COUPLING_ANALYSIS.md` for full investigation

---

### Issue #1: Hardcoded Gateway Name in DisburseCash Pipeline
**File:** `packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php`

**Lines 65, 72:**
```php
// ❌ HARDCODED
$normalizedStatus = DisbursementStatus::fromGateway('netbank', $response->status)->value;
'gateway' => 'netbank',
```

**Problem:** Even if you switch to BDO, it will still store 'netbank' in metadata.

---

### Issue #2: Gateway-Specific Data Extraction
**File:** `app/Services/DisbursementStatusService.php`

**Line 67:**
```php
// ❌ ONLY WORKS FOR NETBANK
if ($disbursement->gateway === 'netbank' && !empty($result['raw'])) {
    $this->extractNetBankData($metadata, $result['raw']);
}
```

**Problem:** Rich data extraction only happens for NetBank. BDO wouldn't get its data extracted.

---

## Solution Architecture

### Approach: **Configuration-Driven + Registry Pattern**

```
┌─────────────────────────────────────────────────────────────┐
│  Configuration Layer                                         │
│  • PAYMENT_GATEWAY=netbank (or bdo, gcash, etc.)            │
│  • Single source of truth                                    │
└────────────────────┬────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────────────────────┐
│  Gateway Resolution                                          │
│  • PaymentGatewayInterface implementation                   │
│  • Automatically resolves based on config                   │
└────────────────────┬────────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────────────────────────────┐
│  Data Enricher Registry (NEW)                               │
│  • Gateway-specific data extractors                         │
│  • Dynamically loads correct enricher                       │
│  • Default fallback for unknown gateways                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Plan

### **Phase 0: Relocate DTOs to Generic Location** 🔧

**Goal:** Move DTOs from misleading `Data/Netbank/Disburse/` to generic `Data/Disburse/` location.

**Why:** DTOs are 100% generic (work for any gateway) but have NetBank-specific namespace due to historical artifact.

**Files to Move:**
```
FROM: packages/payment-gateway/src/Data/Netbank/Disburse/DisburseInputData.php
TO:   packages/payment-gateway/src/Data/Disburse/DisburseInputData.php

FROM: packages/payment-gateway/src/Data/Netbank/Disburse/DisburseResponseData.php
TO:   packages/payment-gateway/src/Data/Disburse/DisburseResponseData.php
```

**Namespace Change:**
```php
// Before:
namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

// After:
namespace LBHurtado\PaymentGateway\Data\Disburse;
```

**Files to Update Imports (7 files):**
1. `packages/payment-gateway/src/Contracts/PaymentGatewayInterface.php`
2. `packages/payment-gateway/src/Services/OmnipayBridge.php`
3. `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`
4. `packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php`
5. `packages/payment-gateway/src/Gateways/Netbank/Traits/CanDisburse.php`
6. `packages/payment-gateway/src/Http/Controllers/DisburseController.php`
7. `packages/voucher/src/Events/DisburseInputPrepared.php`

**Import Update:**
```php
// Before:
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseResponseData;

// After:
use LBHurtado\PaymentGateway\Data\Disburse\DisburseInputData;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;
```

**Benefits:**
- ✅ Removes misleading namespace
- ✅ Makes DTOs obviously generic
- ✅ Matches `DisbursementStatus` enum pattern (already in generic location)
- ✅ No architectural changes - just better organization

**Testing:**
```bash
php artisan test
php artisan test --filter DisburseControllerTest
php artisan test --filter NetbankPaymentGatewayTest
php artisan disbursement:update-status --voucher=E4JE
```

---

### **Phase 1: Add Gateway Name Resolution** ⭐

**Goal:** Get the gateway name from configuration, not hardcode it.

**Files to Modify:**
1. `packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php`

**Changes:**
```php
// Before:
$normalizedStatus = DisbursementStatus::fromGateway('netbank', $response->status)->value;
'gateway' => 'netbank',

// After:
$gatewayName = config('payment-gateway.default', 'netbank');
$normalizedStatus = DisbursementStatus::fromGateway($gatewayName, $response->status)->value;
'gateway' => $gatewayName,
```

**Benefits:**
- ✅ Automatically uses correct gateway name from config
- ✅ No code changes needed when switching gateways
- ✅ Works with any gateway (netbank, bdo, gcash, etc.)

---

### **Phase 2: Create Data Enricher Registry** ⭐⭐

**Goal:** Support gateway-specific data extraction without hardcoding.

**New Files to Create:**

#### 2.1. Abstract Base Enricher
**File:** `app/Services/DataEnrichers/AbstractDataEnricher.php`

```php
abstract class AbstractDataEnricher
{
    /**
     * Extract rich data from gateway API response
     *
     * @param array &$metadata Voucher metadata (by reference)
     * @param array $raw Raw gateway response
     * @return void
     */
    abstract public function extract(array &$metadata, array $raw): void;
    
    /**
     * Check if this enricher supports the given gateway
     *
     * @param string $gateway Gateway name
     * @return bool
     */
    abstract public function supports(string $gateway): bool;
}
```

#### 2.2. NetBank Enricher (Move Existing Logic)
**File:** `app/Services/DataEnrichers/NetBankDataEnricher.php`

```php
class NetBankDataEnricher extends AbstractDataEnricher
{
    public function supports(string $gateway): bool
    {
        return strtolower($gateway) === 'netbank';
    }
    
    public function extract(array &$metadata, array $raw): void
    {
        // Move existing extractNetBankData() logic here
        // Extract settled_at, reference_number, fees, status_history
    }
}
```

#### 2.3. Default Enricher (Fallback)
**File:** `app/Services/DataEnrichers/DefaultDataEnricher.php`

```php
class DefaultDataEnricher extends AbstractDataEnricher
{
    public function supports(string $gateway): bool
    {
        return true; // Fallback for all unknown gateways
    }
    
    public function extract(array &$metadata, array $raw): void
    {
        // Generic extraction - just log that raw data is available
        Log::debug("[DefaultEnricher] Raw data available for {$metadata['disbursement']['gateway']}", [
            'has_data' => !empty($raw),
        ]);
        // Don't extract anything specific - leave raw data intact
    }
}
```

#### 2.4. Enricher Registry
**File:** `app/Services/DataEnrichers/DataEnricherRegistry.php`

```php
class DataEnricherRegistry
{
    protected array $enrichers = [];
    
    public function __construct()
    {
        // Auto-register enrichers
        $this->register(new NetBankDataEnricher());
        $this->register(new DefaultDataEnricher()); // Must be last (fallback)
    }
    
    public function register(AbstractDataEnricher $enricher): void
    {
        $this->enrichers[] = $enricher;
    }
    
    public function getEnricher(string $gateway): AbstractDataEnricher
    {
        foreach ($this->enrichers as $enricher) {
            if ($enricher->supports($gateway)) {
                return $enricher;
            }
        }
        
        // Should never reach here if DefaultEnricher is registered
        return new DefaultDataEnricher();
    }
}
```

---

### **Phase 3: Update DisbursementStatusService** ⭐

**Goal:** Use registry instead of hardcoded NetBank check.

**File:** `app/Services/DisbursementStatusService.php`

**Changes:**

```php
// Before:
if ($disbursement->gateway === 'netbank' && !empty($result['raw'])) {
    $this->extractNetBankData($metadata, $result['raw']);
}

// After:
if (!empty($result['raw'])) {
    $enricher = app(DataEnricherRegistry::class)->getEnricher($disbursement->gateway);
    $enricher->extract($metadata, $result['raw']);
}

// Remove extractNetBankData() method - logic moved to NetBankDataEnricher
```

**Benefits:**
- ✅ Automatically selects correct enricher based on gateway
- ✅ Easy to add new gateway enrichers (just create new class)
- ✅ No code changes in service when adding gateways
- ✅ Fallback for unknown gateways

---

### **Phase 4: Register Enrichers in Service Provider** ⭐

**Goal:** Make enrichers available app-wide.

**File:** `app/Providers/AppServiceProvider.php`

**Changes:**
```php
public function register()
{
    // Register enricher registry as singleton
    $this->app->singleton(DataEnricherRegistry::class, function ($app) {
        return new DataEnricherRegistry();
    });
    
    // Existing DisbursementStatusService registration
    $this->app->singleton(DisbursementStatusService::class, function ($app) {
        return new DisbursementStatusService(
            $app->make(PaymentGatewayInterface::class)
        );
    });
}
```

---

### **Phase 5: Add BDO Example (Documentation)** 📚

**Goal:** Show how easy it is to add a new gateway.

**File:** `docs/ADDING_NEW_GATEWAY.md`

**Content:**
```markdown
# How to Add a New Payment Gateway

## Step 1: Create Omnipay Gateway Driver
Create `packages/payment-gateway/src/Omnipay/Bdo/Gateway.php`

## Step 2: Add Configuration
In `config/omnipay.php`:
```php
'bdo' => [
    'class' => \LBHurtado\PaymentGateway\Omnipay\Bdo\Gateway::class,
    'options' => [
        'apiKey' => env('BDO_API_KEY'),
        'apiEndpoint' => env('BDO_API_ENDPOINT'),
        // ... BDO-specific config
    ],
],
```

## Step 3: Add Status Mapping
In `DisbursementStatus` enum, add BDO mapping:
```php
private static function fromBdo(string $status): self
{
    return match(strtoupper($status)) {
        'PENDING' => self::PENDING,
        'PROCESSING' => self::PROCESSING,
        'COMPLETED' => self::COMPLETED,
        'FAILED' => self::FAILED,
        default => self::PENDING,
    };
}
```

## Step 4: (Optional) Create BDO Data Enricher
If BDO has rich response data:
```php
class BdoDataEnricher extends AbstractDataEnricher
{
    public function supports(string $gateway): bool
    {
        return strtolower($gateway) === 'bdo';
    }
    
    public function extract(array &$metadata, array $raw): void
    {
        // Extract BDO-specific data
        $metadata['disbursement']['bdo_reference'] = $raw['ref_id'] ?? null;
        // ... more BDO fields
    }
}
```

Register in `DataEnricherRegistry`:
```php
$this->register(new BdoDataEnricher());
```

## Step 5: Switch Gateway
Update `.env`:
```bash
PAYMENT_GATEWAY=bdo
BDO_API_KEY=your-key
BDO_API_ENDPOINT=https://api.bdo.com
```

**That's it!** No changes to application code needed.
```

---

## Testing Strategy

### Test 1: NetBank (Existing)
```bash
PAYMENT_GATEWAY=netbank
php artisan disbursement:update-status --voucher=E4JE
```
**Expected:** 
- ✅ Shows enriched data (settled_at, reference_number, fees)
- ✅ Gateway stored as 'netbank'

### Test 2: Unknown Gateway (Default Fallback)
```bash
PAYMENT_GATEWAY=unknown_gateway
php artisan disbursement:update-status --voucher=TEST
```
**Expected:**
- ✅ Works without errors
- ✅ No enriched data (uses DefaultEnricher)
- ✅ Raw data still stored for audit

### Test 3: BDO (After Implementation)
```bash
PAYMENT_GATEWAY=bdo
php artisan disbursement:update-status --voucher=TEST
```
**Expected:**
- ✅ Gateway stored as 'bdo'
- ✅ Status normalized using BDO mapping
- ✅ BDO-specific enriched data extracted (if enricher exists)

---

## File Structure After Implementation

```
app/
├── Services/
│   ├── DisbursementStatusService.php (MODIFIED - use registry)
│   └── DataEnrichers/
│       ├── AbstractDataEnricher.php (NEW - base class)
│       ├── DataEnricherRegistry.php (NEW - registry)
│       ├── NetBankDataEnricher.php (NEW - moved logic)
│       ├── DefaultDataEnricher.php (NEW - fallback)
│       └── BdoDataEnricher.php (FUTURE - BDO example)

packages/voucher/src/Pipelines/RedeemedVoucher/
└── DisburseCash.php (MODIFIED - use config)

docs/
├── GATEWAY_PLUG_AND_PLAY_PLAN.md (this file)
└── ADDING_NEW_GATEWAY.md (NEW - guide)
```

---

## Success Criteria

After implementation, switching gateways should be:

✅ **1-Line Change:**
```bash
# .env
PAYMENT_GATEWAY=bdo  # Changed from 'netbank'
```

✅ **Zero Code Changes:**
- No modifications to DisburseCash
- No modifications to DisbursementStatusService
- No modifications to commands

✅ **Extensible:**
- Add new gateway enricher = just create new class
- No registry modification needed (auto-discovered)

✅ **Safe:**
- Unknown gateways work (DefaultEnricher fallback)
- Raw data always preserved for audit
- Comprehensive logging

---

## Implementation Order

1. **Phase 0** (15 min) - Relocate DTOs to generic location
2. **Phase 1** (5 min) - Quick win, fixes gateway name issue
3. **Phase 4** (2 min) - Setup service provider
4. **Phase 2** (20 min) - Create enricher infrastructure
5. **Phase 3** (10 min) - Update service to use registry
6. **Phase 5** (10 min) - Documentation
7. **Testing** (15 min) - Verify with existing voucher

**Total Estimate:** ~75 minutes

---

## Benefits

### For Developers:
- 🎯 **True plug-and-play** - change config, done
- 🚀 **Easy to extend** - new gateway = new enricher class
- 🛡️ **Safe** - fallback enricher prevents errors
- 📝 **Clear pattern** - obvious where to add gateway logic

### For Business:
- 💰 **Lower integration cost** - faster to add gateways
- 🔄 **Easy switching** - test different gateways easily
- 🌍 **International ready** - support any country's gateways
- 🔧 **Maintainable** - clear separation of concerns

---

## Future Enhancements (Optional)

### Auto-Discovery Pattern
Instead of manually registering enrichers, auto-discover them:

```php
// In DataEnricherRegistry constructor
foreach (glob(app_path('Services/DataEnrichers/*Enricher.php')) as $file) {
    $class = /* extract class name */;
    if (is_subclass_of($class, AbstractDataEnricher::class)) {
        $this->register(new $class());
    }
}
```

### Enricher Configuration
Allow enrichers to have their own config:

```php
// config/enrichers.php
return [
    'netbank' => [
        'enabled' => true,
        'extract_fees' => true,
        'extract_history' => true,
    ],
    'bdo' => [
        'enabled' => true,
        'custom_field' => 'ref_id',
    ],
];
```

---

**Ready to implement?** This will make the system truly gateway-agnostic! 🚀
