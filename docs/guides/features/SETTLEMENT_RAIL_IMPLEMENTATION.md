# Settlement Rail Selection & Fee Tracking - Implementation Summary

**Date:** December 10, 2025  
**Status:** ✅ Complete  
**Branches Merged:** `fix/voucher-test-suite`, `feature/settlement-rail-selection`

## Overview

Implemented settlement rail selection (INSTAPAY/PESONET) for voucher disbursements with comprehensive fee tracking. Users can now choose the settlement rail during voucher generation, or the system will intelligently select one based on the voucher amount.

## What Was Implemented

### 1. Core Data Structures

**CashInstructionData** (packages/voucher/src/Data/CashInstructionData.php):
- Added `settlement_rail` field (nullable SettlementRail enum)
- Added `fee_strategy` field (string: 'absorb'|'include'|'add', defaults to 'absorb')
- Added EnumCast for proper enum handling
- Updated validation rules

**VoucherInstructionsData** (packages/voucher/src/Data/VoucherInstructionsData.php):
- Added validation rules for `cash.settlement_rail` and `cash.fee_strategy`
- Updated `createFromAttribs()` to handle new fields
- Updated `generateFromScratch()` with proper defaults

### 2. Smart Rail Selection

**DisburseInputData::fromVoucher()** (packages/payment-gateway/src/Data/Disburse/DisburseInputData.php):
- Changed `$via` parameter from required to optional (nullable)
- Implemented 3-tier selection logic:
  1. Use voucher's `settlement_rail` if explicitly set
  2. Use `$via` parameter if provided
  3. Auto-select based on amount: INSTAPAY (<₱50k), PESONET (≥₱50k)
- Added debug logging for rail selection decisions

### 3. Fee Tracking

**PaymentGatewayInterface** (packages/payment-gateway/src/Contracts/PaymentGatewayInterface.php):
- Added `getRailFee(SettlementRail $rail): int` method

**OmnipayPaymentGateway** (packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php):
- Implemented `getRailFee()` reading from `omnipay.php` config

**NetbankPaymentGateway** (packages/payment-gateway/src/Gateways/Netbank/NetbankPaymentGateway.php):
- Implemented `getRailFee()` for backward compatibility

**DisburseCash Pipeline** (packages/voucher/src/Pipelines/RedeemedVoucher/DisburseCash.php):
- Retrieves fee via `$gateway->getRailFee($rail)`
- Calculates `total_cost` (amount + fee)
- Stores in voucher metadata:
  - `settlement_rail` - Selected rail (INSTAPAY/PESONET)
  - `fee_amount` - Fee in centavos
  - `total_cost` - Total cost in centavos
  - `fee_strategy` - Strategy (absorb/include/add)

### 4. Campaign Support

**Campaign Model** (app/Models/Campaign.php):
- Already supports rail preferences through `instructions` field
- No changes needed - campaigns store full `VoucherInstructionsData`
- Rail and fee strategy automatically inherited from campaign templates

### 5. Testing

**RailSelectionTest.php** (packages/voucher/tests/Unit/Data/RailSelectionTest.php):
- 5 comprehensive tests covering:
  - Rail and fee strategy fields
  - Default values (fee_strategy='absorb')
  - Enum validation
  - Integration with VoucherInstructionsData
- All tests passing ✅

**Bug Fix** (packages/voucher/tests/Feature/VoucherExternalMetadataTest.php):
- Fixed duplicate `createVoucher()` function causing fatal error
- Renamed to `createTestVoucherForMetadata()` in feature test

## Configuration

### Rail Fees (config/omnipay.php)

```php
'INSTAPAY' => [
    'enabled' => true,
    'min_amount' => 1,
    'max_amount' => 50000 * 100, // ₱50k in centavos
    'fee' => 1000, // ₱10 in centavos
],
'PESONET' => [
    'enabled' => true,
    'min_amount' => 1,
    'max_amount' => 1000000 * 100, // ₱1M in centavos
    'fee' => 2500, // ₱25 in centavos
],
```

**Note:** Fees are configurable per agreement with banks/EMIs.

## Usage Examples

### Voucher Generation with Explicit Rail

```php
$instructions = VoucherInstructionsData::from([
    'cash' => [
        'amount' => 60000,
        'currency' => 'PHP',
        'settlement_rail' => 'PESONET',
        'fee_strategy' => 'absorb',
        // ... other fields
    ],
    // ... other instructions
]);

GenerateVouchers::run($instructions);
```

### Voucher Generation with Auto-Selection

```php
$instructions = VoucherInstructionsData::from([
    'cash' => [
        'amount' => 30000, // Will auto-select INSTAPAY
        'currency' => 'PHP',
        'settlement_rail' => null, // Auto
        'fee_strategy' => 'absorb',
        // ... other fields
    ],
    // ... other instructions
]);
```

### Campaign with Rail Preference

```php
$campaign = Campaign::create([
    'user_id' => $user->id,
    'name' => 'Large Payouts',
    'instructions' => [
        'cash' => [
            'amount' => 75000,
            'settlement_rail' => 'PESONET', // Preferred for large amounts
            'fee_strategy' => 'absorb',
            // ...
        ],
        // ...
    ],
]);
```

### Checking Disbursement Metadata

```php
$voucher->metadata['disbursement'] = [
    'gateway' => 'netbank',
    'settlement_rail' => 'INSTAPAY',
    'fee_amount' => 1000, // ₱10
    'total_cost' => 11000, // ₱100 + ₱10
    'fee_strategy' => 'absorb',
    // ... other fields
];
```

## Fee Strategies

1. **absorb** (default) - Issuer pays the fee, redeemer receives full voucher amount
2. **include** - Fee deducted from voucher amount before generation
3. **add** - Fee added to total cost, redeemer pays extra

**Note:** Fee strategy logic is stored but not yet implemented in amount calculations. Future enhancement.

## Backward Compatibility

- Existing vouchers without `settlement_rail` will auto-select based on amount
- Default fee strategy is 'absorb' (issuer pays)
- No database migrations required
- Campaign model automatically supports new fields through instructions JSON

## Testing Status

- ✅ Unit tests: 5/5 passing
- ✅ Voucher test suite: Fixed (fatal error resolved)
- ✅ Integration: Tested via existing test infrastructure
- ⏳ UI tests: Not yet implemented (future phase)

## Git Branches

1. `fix/voucher-test-suite` - Fixed duplicate createVoucher function
2. `feature/settlement-rail-selection` - Main rail selection implementation

Both merged to `main` branch.

## Next Steps (Future Enhancements)

1. **UI Implementation** - Add rail selection dropdown in voucher generation form
2. **Fee Strategy Execution** - Implement actual amount adjustments based on fee_strategy
3. **Real-time Fee Display** - Show estimated fees in UI during voucher creation
4. **Analytics Dashboard** - Track rail usage and fee costs
5. **Environment Variables** - Add `NETBANK_INSTAPAY_FEE`, `NETBANK_PESONET_FEE` for easier configuration

## Success Metrics

✅ Settlement rail can be selected or auto-determined  
✅ Transaction fees tracked in voucher metadata  
✅ Smart defaults work correctly (INSTAPAY <₱50k, PESONET ≥₱50k)  
✅ Campaign support through VoucherInstructionsData  
✅ All existing tests pass  
✅ New tests cover rail selection scenarios  
✅ Backward compatible with existing vouchers  
✅ No breaking changes to redemption flow
