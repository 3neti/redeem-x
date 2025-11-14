# Legacy Disbursement Format Deprecation Plan

**Date:** November 14, 2025  
**Context:** New application - no historical data migration needed  
**Status:** 📋 Ready for Implementation

---

## Overview

Since this is a **new application** with minimal historical data, we can deprecate the legacy format more aggressively than typical enterprise applications. The goal is to remove legacy format support and simplify the codebase.

---

## Current State (After Phase 3)

### What's Using Legacy Format
- ✅ **Voucher 7QHX** - Old format (stored before Phase 2)
- ✅ **DTO** - Supports both formats via dual reading
- ✅ **UI Helpers** - Fall back to legacy fields

### What's Using New Format
- ✅ **Voucher QVAL** - New format (stored after Phase 2)
- ✅ **All future redemptions** - Automatically use new format
- ✅ **DisburseCash pipeline** - Stores in new format

---

## Deprecation Strategy (Fast Track)

Since there's no need for data migration, we can move quickly through deprecation phases.

### Timeline: 2-Week Deprecation Path

| Phase | Timeline | Actions | Breaking? |
|-------|----------|---------|-----------|
| **Phase 4a: Soft Deprecation** | Week 1 | Add deprecation warnings | ❌ No |
| **Phase 4b: Hard Deprecation** | Week 2 | Remove legacy support | ✅ Yes |

---

## Phase 4a: Soft Deprecation (Week 1)

### Goal
Add deprecation notices to warn developers and log legacy format usage.

### Changes

#### 1. Backend - DTO Deprecation Notices

**File:** `packages/voucher/src/Data/DisbursementData.php`

```php
class DisbursementData extends Data
{
    public function __construct(
        // Core fields (gateway-agnostic)
        public string $gateway,
        public string $transaction_id,
        public string $status,
        public float $amount,
        public string $currency,
        public string $recipient_identifier,
        public string $disbursed_at,
        public ?string $transaction_uuid = null,
        public ?string $recipient_name = null,
        public ?string $payment_method = null,
        public ?array $metadata = null,
        
        // Legacy fields (deprecated)
        /** 
         * @deprecated Use transaction_id instead. Will be removed in 2 weeks.
         * @see $transaction_id
         */
        public ?string $operation_id = null,
        
        /** 
         * @deprecated Use metadata.bank_code instead. Will be removed in 2 weeks.
         * @see $metadata['bank_code']
         */
        public ?string $bank = null,
        
        /** 
         * @deprecated Use metadata.rail instead. Will be removed in 2 weeks.
         * @see $metadata['rail']
         */
        public ?string $rail = null,
        
        /** 
         * @deprecated Use recipient_identifier instead. Will be removed in 2 weeks.
         * @see $recipient_identifier
         */
        public ?string $account = null,
        
        /** 
         * @deprecated Use recipient_name instead. Will be removed in 2 weeks.
         * @see $recipient_name
         */
        public ?string $bank_name = null,
        
        /** 
         * @deprecated Use metadata.bank_logo instead. Will be removed in 2 weeks.
         * @see $metadata['bank_logo']
         */
        public ?string $bank_logo = null,
        
        /** 
         * @deprecated Use metadata.is_emi instead. Will be removed in 2 weeks.
         * @see $metadata['is_emi']
         */
        public bool $is_emi = false,
    ) {}
    
    protected static function fromLegacyNetbankFormat(array $data): static
    {
        // Log legacy format usage
        \Log::warning('[DEPRECATED] Legacy disbursement format detected', [
            'data' => $data,
            'notice' => 'Legacy format support will be removed in 2 weeks. Please update to generic format.',
        ]);
        
        // ... existing conversion logic
    }
}
```

#### 2. Frontend - Console Warnings

**File:** `resources/js/pages/Transactions/Index.vue`

```typescript
// Helper to detect and warn about legacy format
const isLegacyFormat = (disbursement: any) => {
    return !disbursement.gateway && disbursement.operation_id;
};

// Update helpers to log warnings
const getTransactionId = (disbursement: any) => {
    if (isLegacyFormat(disbursement)) {
        console.warn(
            '[DEPRECATED] Legacy disbursement format detected. ' +
            'Legacy support will be removed in 2 weeks. ' +
            'Voucher should be updated to use new format.'
        );
    }
    return disbursement.transaction_id || disbursement.operation_id;
};

// Apply to all helpers: getBankName, getRail, getRecipientIdentifier
```

**File:** `resources/js/components/TransactionDetailModal.vue`

```typescript
// Same deprecation warnings in modal helpers
const getTransactionId = () => {
    const d = props.transaction?.disbursement;
    if (d && !d.gateway && d.operation_id) {
        console.warn('[DEPRECATED] Legacy disbursement format detected');
    }
    return d?.transaction_id || d?.operation_id;
};

// Apply to all 8 helper functions
```

#### 3. Documentation Update

**File:** `docs/DISBURSEMENT_GENERALIZATION_PLAN.md`

Add deprecation notice at the top:
```markdown
⚠️ **DEPRECATION NOTICE (November 14, 2025):**
Legacy format support will be removed on **November 28, 2025** (2 weeks).
All new code should use the generic format only.
```

#### 4. API Documentation

Add deprecation warnings to API responses (optional):
```php
// In ListTransactions.php
if (/* legacy format detected */) {
    $response['_deprecated'] = [
        'message' => 'Legacy format detected. Support ends November 28, 2025.',
        'fields' => ['operation_id', 'bank', 'rail', 'account', 'bank_name', 'is_emi'],
        'migration_guide' => url('/docs/DISBURSEMENT_GENERALIZATION_PLAN.md'),
    ];
}
```

### Testing Phase 4a
- ✅ Warnings appear in logs when legacy format accessed
- ✅ Console warnings appear in browser
- ✅ All functionality still works (no breaking changes)
- ✅ Documentation updated

### Commit Phase 4a
```bash
git commit -m "chore: add deprecation warnings for legacy disbursement format

- Add @deprecated PHPDoc tags to legacy DTO fields
- Log warnings when legacy format is read
- Add console warnings in UI helpers
- Update documentation with removal date (2 weeks)
- No breaking changes - everything still works

Legacy format will be removed: November 28, 2025"
```

---

## Phase 4b: Hard Deprecation (Week 2)

### Goal
Remove all legacy format support from codebase.

### Before Starting
1. ✅ Verify no new legacy vouchers created in past week
2. ✅ Check logs for legacy format warnings
3. ✅ Identify any remaining legacy vouchers (7QHX)

### Option 1: Delete Legacy Vouchers (Recommended for New App)

Since it's a new app with test data:

```php
// Delete all vouchers with legacy format
php artisan tinker --execute="
\LBHurtado\Voucher\Models\Voucher::whereNotNull('metadata->disbursement')
    ->get()
    ->filter(function(\$v) {
        return !isset(\$v->metadata['disbursement']['gateway']);
    })
    ->each(function(\$v) {
        echo 'Deleting legacy voucher: ' . \$v->code . PHP_EOL;
        \$v->delete();
    });
"
```

### Option 2: Convert Legacy Vouchers (If Needed)

If voucher 7QHX needs to be kept:

```php
// Artisan command: ConvertLegacyDisbursements.php
php artisan make:command ConvertLegacyDisbursements

// Command implementation
public function handle()
{
    $bankRegistry = app(BankRegistry::class);
    
    $vouchers = Voucher::whereNotNull('metadata->disbursement')
        ->get()
        ->filter(fn($v) => !isset($v->metadata['disbursement']['gateway']));
    
    $this->info("Found {$vouchers->count()} vouchers with legacy format");
    
    foreach ($vouchers as $voucher) {
        $old = $voucher->metadata['disbursement'];
        $bankCode = $old['bank'] ?? '';
        
        $new = [
            'gateway' => 'netbank',
            'transaction_id' => $old['operation_id'] ?? '',
            'status' => $old['status'] ?? 'Unknown',
            'amount' => $old['amount'] ?? 0,
            'currency' => 'PHP',
            'recipient_identifier' => $old['account'] ?? '',
            'disbursed_at' => $old['disbursed_at'] ?? '',
            'transaction_uuid' => $old['transaction_uuid'] ?? null,
            'recipient_name' => $bankRegistry->getBankName($bankCode),
            'payment_method' => 'bank_transfer',
            'metadata' => [
                'bank_code' => $bankCode,
                'bank_name' => $bankRegistry->getBankName($bankCode),
                'bank_logo' => $bankRegistry->getBankLogo($bankCode),
                'rail' => $old['rail'] ?? '',
                'is_emi' => $bankRegistry->isEMI($bankCode),
            ],
        ];
        
        $voucher->metadata = [
            ...$voucher->metadata,
            'disbursement' => $new,
        ];
        $voucher->save();
        
        $this->info("Converted: {$voucher->code}");
    }
    
    $this->info("Conversion complete!");
}
```

### Changes to Remove Legacy Support

#### 1. DTO - Remove Legacy Fields

**File:** `packages/voucher/src/Data/DisbursementData.php`

```php
class DisbursementData extends Data
{
    public function __construct(
        // Core fields only (legacy fields removed)
        public string $gateway,
        public string $transaction_id,
        public string $status,
        public float $amount,
        public string $currency,
        public string $recipient_identifier,
        public string $disbursed_at,
        public ?string $transaction_uuid = null,
        public ?string $recipient_name = null,
        public ?string $payment_method = null,
        public ?array $metadata = null,
    ) {}
    
    public static function fromMetadata(?array $metadata): ?static
    {
        $disbursement = $metadata['disbursement'] ?? null;
        if (!$disbursement || !isset($disbursement['gateway'])) {
            return null; // No longer support legacy format
        }
        
        return new static(
            gateway: $disbursement['gateway'],
            transaction_id: $disbursement['transaction_id'],
            status: $disbursement['status'] ?? 'Unknown',
            amount: (float) ($disbursement['amount'] ?? 0),
            currency: $disbursement['currency'] ?? 'PHP',
            recipient_identifier: $disbursement['recipient_identifier'],
            disbursed_at: $disbursement['disbursed_at'],
            transaction_uuid: $disbursement['transaction_uuid'] ?? null,
            recipient_name: $disbursement['recipient_name'] ?? null,
            payment_method: $disbursement['payment_method'] ?? null,
            metadata: $disbursement['metadata'] ?? null,
        );
    }
    
    // Remove: fromLegacyNetbankFormat()
    // Remove: fromGenericFormat() (inline into fromMetadata)
    
    // Remove legacy helper methods:
    // - getBankCode() [use metadata.bank_code directly]
    // - getRail() [use metadata.rail directly]
    // - getBankName() [use recipient_name directly]
    // - getBankLogo() [use metadata.bank_logo directly]
    // - isEMI() [use metadata.is_emi directly]
}
```

#### 2. UI - Simplify Helpers

**File:** `resources/js/pages/Transactions/Index.vue`

```typescript
// Simplified - no fallbacks
const getRecipientIdentifier = (disbursement: any) => {
    return disbursement.recipient_identifier || 'N/A';
};

const getBankName = (disbursement: any) => {
    return disbursement.recipient_name || 'N/A';
};

const getRail = (disbursement: any) => {
    return disbursement.metadata?.rail;
};

const getTransactionId = (disbursement: any) => {
    return disbursement.transaction_id;
};
```

**File:** `resources/js/components/TransactionDetailModal.vue`

```typescript
// Simplified - no fallbacks
const getTransactionId = () => props.transaction?.disbursement?.transaction_id;
const getRecipientIdentifier = () => props.transaction?.disbursement?.recipient_identifier || 'N/A';
const getBankName = () => props.transaction?.disbursement?.recipient_name || 'N/A';
const getRail = () => props.transaction?.disbursement?.metadata?.rail;
const getPaymentMethod = () => {
    const pm = props.transaction?.disbursement?.payment_method;
    return pm === 'bank_transfer' ? 'Bank Transfer' : 
           pm === 'e_wallet' ? 'E-Wallet' : 
           pm === 'card' ? 'Credit/Debit Card' : pm || 'Unknown';
};
const isEWallet = () => props.transaction?.disbursement?.payment_method === 'e_wallet';
const getGatewayName = () => {
    const gateway = props.transaction?.disbursement?.gateway;
    return gateway ? gateway.charAt(0).toUpperCase() + gateway.slice(1) : null;
};
const getCurrency = () => {
    return props.transaction?.disbursement?.currency || 
           props.transaction?.currency || 'PHP';
};
```

#### 3. Remove Legacy Documentation

Delete or archive:
- Old code examples with legacy format
- Legacy field descriptions
- Backward compatibility notes (no longer needed)

### Testing Phase 4b
- ✅ All vouchers use new format
- ✅ DTO only accepts new format
- ✅ UI helpers simplified (no fallbacks)
- ✅ No legacy fields in database
- ✅ Codebase simplified

### Commit Phase 4b
```bash
git commit -m "BREAKING: remove legacy disbursement format support

DTO Changes:
- Remove all legacy fields (operation_id, bank, rail, account, etc.)
- Remove fromLegacyNetbankFormat() method
- Simplify fromMetadata() to only accept new format
- Remove legacy helper methods

UI Changes:
- Simplify all helper functions (remove fallbacks)
- Remove legacy format detection
- Remove console warnings (no longer needed)

Data:
- [Option 1] Deleted X legacy vouchers (test data only)
- [Option 2] Converted X legacy vouchers to new format

Breaking Change:
- Legacy format no longer supported
- All vouchers must use new generic format
- Old API responses will fail if legacy format present

Migration:
- Not needed (new application with minimal data)
- All future redemptions use new format automatically"
```

---

## Summary: 2-Week Fast Track

### Week 1 (Phase 4a): Soft Deprecation
- ✅ Add `@deprecated` tags
- ✅ Log warnings
- ✅ Console warnings
- ✅ Update docs
- ❌ **No breaking changes**

### Week 2 (Phase 4b): Hard Deprecation
- ✅ Delete OR convert legacy vouchers
- ✅ Remove legacy fields from DTO
- ✅ Simplify UI helpers
- ✅ Clean up codebase
- ✅ **Breaking changes** (acceptable for new app)

---

## Benefits of Fast Track Deprecation

✅ **Simpler codebase** - No legacy support bloat  
✅ **Faster development** - No dual format maintenance  
✅ **Less complexity** - Cleaner helper functions  
✅ **Better DX** - New developers don't see deprecated code  
✅ **Future proof** - Generic format only  

---

## Rollout Checklist

### Phase 4a (Week 1)
- [ ] Add deprecation tags to DTO
- [ ] Add log warnings
- [ ] Add console warnings
- [ ] Update documentation
- [ ] Deploy and monitor warnings
- [ ] Commit "chore: add deprecation warnings"

### Phase 4b (Week 2)
- [ ] Check logs for legacy usage
- [ ] Decide: Delete or Convert legacy vouchers
- [ ] Remove legacy fields from DTO
- [ ] Simplify UI helpers
- [ ] Test thoroughly
- [ ] Commit "BREAKING: remove legacy format support"
- [ ] Deploy to production

---

**Status:** 📋 Ready to Implement  
**Timeline:** 2 weeks (fast track)  
**Migration:** Not needed (new app)  
**Next Step:** Implement Phase 4a (deprecation warnings)
