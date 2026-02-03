# DisbursementData Generalization Plan

**Date:** November 14, 2025  
**Issue:** Current `DisbursementData` DTO is NetBank-specific (uses bank codes, rails, operation_id)  
**Goal:** Make it generic to support multiple payment gateways (NetBank, ICash, PayPal, Stripe, etc.)

---

## Current Issues

### NetBank-Specific Fields
1. **`bank`** - SWIFT BIC codes (e.g., GXCHPHM2XXX) - only relevant for Philippine banks
2. **`rail`** - INSTAPAY/PESONET - only relevant for Philippine banking
3. **`operation_id`** - NetBank's transaction ID format
4. **`is_emi`** - Philippine-specific concept (e-Money Issuer)
5. **`bank_name`**, **`bank_logo`** - assumes BankRegistry exists

### What Happens With Other Gateways?

**ICash (Philippines):**
- Similar to NetBank (banks, rails, operation IDs)
- Would work but still PH-specific

**PayPal (International):**
- No "bank" or "rail" concept
- Uses PayPal transaction IDs
- Account = PayPal email
- No EMI concept

**Stripe (International):**
- No "bank" or "rail" concept
- Uses charge IDs (ch_xxx)
- Account = card last 4 digits
- Payment method types (card, bank_transfer, etc.)

**GCash Direct API (no banking rails):**
- Transaction reference number
- Mobile number only
- No bank code, no rail

---

## Proposed Generic Structure

### Core Fields (Gateway-Agnostic)
```php
class DisbursementData extends Data
{
    // Universal fields
    public string $gateway;              // 'netbank', 'icash', 'paypal', 'stripe', 'gcash'
    public string $transaction_id;       // Gateway's transaction reference
    public string $status;               // 'pending', 'completed', 'failed'
    public float $amount;                // Amount disbursed
    public string $currency;             // 'PHP', 'USD', etc.
    public string $recipient_identifier; // Account number, email, mobile, etc.
    public string $disbursed_at;         // ISO 8601 timestamp
    
    // Optional universal fields
    public ?string $transaction_uuid = null;    // Internal UUID (if exists)
    public ?string $recipient_name = null;      // Recipient display name
    public ?string $payment_method = null;      // Generic payment method type
    public ?array $metadata = null;             // Gateway-specific extra data
}
```

### Gateway-Specific Metadata
Store gateway-specific fields in `metadata` array:

**NetBank:**
```php
'metadata' => [
    'bank_code' => 'GXCHPHM2XXX',
    'bank_name' => 'GCash',
    'bank_logo' => '/images/banks/gcash.svg',
    'rail' => 'INSTAPAY',
    'is_emi' => true,
    'operation_id' => '260683631',  // Also in transaction_id
]
```

**PayPal:**
```php
'metadata' => [
    'payer_email' => 'user@example.com',
    'fee_amount' => 0.30,
    'transaction_type' => 'web_accept',
]
```

**Stripe:**
```php
'metadata' => [
    'charge_id' => 'ch_1234567890',
    'payment_method_type' => 'card',
    'card_brand' => 'visa',
    'card_last4' => '4242',
]
```

---

## Implementation Options

### Option 1: Single Generic DTO with Metadata (Recommended)

**Pros:**
- ✅ Works for any gateway
- ✅ Backward compatible (can map NetBank fields to new structure)
- ✅ Simple to extend (just add metadata)
- ✅ UI can adapt based on `gateway` field

**Cons:**
- ⚠️ Loses strong typing for gateway-specific fields
- ⚠️ UI needs conditional logic based on gateway

**Structure:**
```php
class DisbursementData extends Data
{
    public string $gateway;
    public string $transaction_id;
    public string $status;
    public float $amount;
    public string $currency;
    public string $recipient_identifier;
    public string $disbursed_at;
    public ?string $transaction_uuid = null;
    public ?string $recipient_name = null;
    public ?string $payment_method = null;
    public ?array $metadata = null;
    
    // Helper methods
    public function getDisplayName(): string
    public function getMaskedIdentifier(): string
    public function getGatewayIcon(): ?string
    public function getPaymentMethodDisplay(): string
}
```

### Option 2: Polymorphic DTOs (Inheritance)

**Pros:**
- ✅ Strong typing for each gateway
- ✅ Type-safe access to gateway-specific fields
- ✅ Clear separation of concerns

**Cons:**
- ⚠️ More complex
- ⚠️ Harder to add new gateways
- ⚠️ UI needs to handle different DTO types

**Structure:**
```php
abstract class DisbursementData extends Data
{
    abstract public function getGatewayName(): string;
    abstract public function getDisplayIdentifier(): string;
    abstract public function getMaskedIdentifier(): string;
}

class NetbankDisbursementData extends DisbursementData
{
    public string $operation_id;
    public string $bank_code;
    public string $rail;
    // ... NetBank-specific fields
}

class PaypalDisbursementData extends DisbursementData
{
    public string $transaction_id;
    public string $payer_email;
    // ... PayPal-specific fields
}
```

### Option 3: Composition (Gateway + Generic)

**Pros:**
- ✅ Best of both worlds
- ✅ Strong typing where needed
- ✅ Generic interface for UI

**Cons:**
- ⚠️ Most complex implementation

**Structure:**
```php
class DisbursementData extends Data
{
    public GenericDisbursement $disbursement;
    public ?GatewaySpecificData $gateway_data = null;
}
```

---

## Recommended Approach: Option 1 (Generic DTO)

### Why?
1. **Simplicity** - Easiest to implement and maintain
2. **Flexibility** - Works with any gateway
3. **Backward Compatible** - Can migrate existing NetBank data
4. **UI-Friendly** - Single DTO type to handle

### Migration Strategy

#### Phase 1: Create Generic DTO
1. Create new generic `DisbursementData` structure
2. Keep all existing fields but make them optional/nullable
3. Add `gateway` and `metadata` fields
4. Map NetBank-specific fields to both old and new locations

#### Phase 2: Update Storage
1. When saving disbursement, store in new generic format:
```php
'disbursement' => [
    'gateway' => 'netbank',
    'transaction_id' => '260683631',
    'status' => 'Pending',
    'amount' => 50.00,
    'currency' => 'PHP',
    'recipient_identifier' => '09173011987',
    'disbursed_at' => '2025-11-14T11:59:04+08:00',
    'recipient_name' => 'GCash',
    'payment_method' => 'bank_transfer',
    'metadata' => [
        'bank_code' => 'GXCHPHM2XXX',
        'bank_name' => 'GCash',
        'rail' => 'INSTAPAY',
        'is_emi' => true,
        // Legacy fields for backward compatibility
        'operation_id' => '260683631',  // Duplicate of transaction_id
        'account' => '09173011987',     // Duplicate of recipient_identifier
        'bank' => 'GXCHPHM2XXX',        // Duplicate of metadata.bank_code
    ],
]
```

#### Phase 3: Update DTO Reading
1. `fromMetadata()` tries new format first
2. Falls back to old NetBank format if new format not found
3. Returns generic DTO with computed fields

#### Phase 4: Update UI
1. Transaction table reads from generic fields
2. Add gateway badge/icon
3. Conditional display based on `gateway` field
4. Detail modal shows appropriate fields per gateway

#### Phase 5: Deprecate Old Format
1. Run migration script to convert old disbursements
2. Remove old field mappings
3. Simplify DTO

---

## Detailed Implementation

### Step 1: Update DisbursementData DTO

```php
<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class DisbursementData extends Data
{
    public function __construct(
        // Core fields (gateway-agnostic)
        public string $gateway,                  // 'netbank', 'icash', 'paypal', etc.
        public string $transaction_id,           // Gateway's transaction reference
        public string $status,                   // 'pending', 'completed', 'failed'
        public float $amount,                    // Amount disbursed
        public string $currency,                 // 'PHP', 'USD', etc.
        public string $recipient_identifier,     // Account/email/mobile
        public string $disbursed_at,             // ISO 8601 timestamp
        public ?string $transaction_uuid = null, // Internal UUID
        public ?string $recipient_name = null,   // Display name (e.g., "GCash", "john@example.com")
        public ?string $payment_method = null,   // 'bank_transfer', 'e_wallet', 'card', etc.
        public ?array $metadata = null,          // Gateway-specific data
    ) {}
    
    public static function fromMetadata(?array $metadata): ?static
    {
        $disbursement = $metadata['disbursement'] ?? null;
        if (!$disbursement) {
            return null;
        }
        
        // Try new generic format first
        if (isset($disbursement['gateway'])) {
            return static::fromGenericFormat($disbursement);
        }
        
        // Fall back to legacy NetBank format
        return static::fromLegacyNetbankFormat($disbursement);
    }
    
    protected static function fromGenericFormat(array $data): static
    {
        return new static(
            gateway: $data['gateway'],
            transaction_id: $data['transaction_id'],
            status: $data['status'] ?? 'Unknown',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'PHP',
            recipient_identifier: $data['recipient_identifier'],
            disbursed_at: $data['disbursed_at'],
            transaction_uuid: $data['transaction_uuid'] ?? null,
            recipient_name: $data['recipient_name'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
    
    protected static function fromLegacyNetbankFormat(array $data): static
    {
        // Map old NetBank format to new generic format
        $bankRegistry = app(\LBHurtado\PaymentGateway\Support\BankRegistry::class);
        $bankCode = $data['bank'] ?? '';
        
        return new static(
            gateway: 'netbank',
            transaction_id: $data['operation_id'] ?? '',
            status: $data['status'] ?? 'Unknown',
            amount: (float) ($data['amount'] ?? 0),
            currency: 'PHP',
            recipient_identifier: $data['account'] ?? '',
            disbursed_at: $data['disbursed_at'] ?? '',
            transaction_uuid: $data['transaction_uuid'] ?? null,
            recipient_name: $bankRegistry->getBankName($bankCode),
            payment_method: 'bank_transfer',
            metadata: [
                'bank_code' => $bankCode,
                'bank_name' => $bankRegistry->getBankName($bankCode),
                'bank_logo' => $bankRegistry->getBankLogo($bankCode),
                'rail' => $data['rail'] ?? '',
                'is_emi' => $bankRegistry->isEMI($bankCode),
            ],
        );
    }
    
    // Helper methods
    public function getMaskedIdentifier(): string
    {
        if (strlen($this->recipient_identifier) <= 4) {
            return $this->recipient_identifier;
        }
        return '***' . substr($this->recipient_identifier, -4);
    }
    
    public function getGatewayIcon(): ?string
    {
        return match($this->gateway) {
            'netbank', 'icash' => '/images/gateways/ph-banking.svg',
            'paypal' => '/images/gateways/paypal.svg',
            'stripe' => '/images/gateways/stripe.svg',
            'gcash' => '/images/gateways/gcash.svg',
            default => null,
        };
    }
    
    public function getPaymentMethodDisplay(): string
    {
        return match($this->payment_method) {
            'bank_transfer' => 'Bank Transfer',
            'e_wallet' => 'E-Wallet',
            'card' => 'Credit/Debit Card',
            default => $this->payment_method ?? 'Unknown',
        };
    }
    
    // Gateway-specific helpers
    public function getBankCode(): ?string
    {
        return $this->metadata['bank_code'] ?? null;
    }
    
    public function getRail(): ?string
    {
        return $this->metadata['rail'] ?? null;
    }
    
    public function getBankName(): ?string
    {
        return $this->metadata['bank_name'] ?? $this->recipient_name;
    }
    
    public function isEMI(): bool
    {
        return $this->metadata['is_emi'] ?? false;
    }
}
```

### Step 2: Update DisburseCash Pipeline

```php
// Store in new generic format
$voucher->metadata = array_merge(
    $voucher->metadata ?? [],
    [
        'disbursement' => [
            'gateway' => 'netbank',
            'transaction_id' => $response->transaction_id,
            'status' => $response->status,
            'amount' => $input->amount,
            'currency' => 'PHP',
            'recipient_identifier' => $input->account_number,
            'disbursed_at' => now()->toIso8601String(),
            'transaction_uuid' => $response->uuid,
            'recipient_name' => $bankRegistry->getBankName($input->bank),
            'payment_method' => 'bank_transfer',
            'metadata' => [
                'bank_code' => $input->bank,
                'bank_name' => $bankRegistry->getBankName($input->bank),
                'rail' => $input->via,
                'is_emi' => $bankRegistry->isEMI($input->bank),
            ],
        ],
    ]
);
```

### Step 3: Update UI

**Transaction Table:**
```vue
<td>
    <div v-if="transaction.disbursement">
        <div class="flex items-center gap-2">
            <img v-if="transaction.disbursement.metadata?.bank_logo" 
                 :src="transaction.disbursement.metadata.bank_logo" />
            <div>
                <div>{{ transaction.disbursement.recipient_name }}</div>
                <div class="text-xs">{{ transaction.disbursement.getMaskedIdentifier() }}</div>
            </div>
        </div>
    </div>
</td>
```

**Detail Modal:**
```vue
<Card v-if="transaction.disbursement">
    <CardHeader>
        <CardTitle>{{ transaction.disbursement.gateway }} Transfer Details</CardTitle>
    </CardHeader>
    <CardContent>
        <!-- Show gateway-specific fields conditionally -->
        <div v-if="transaction.disbursement.gateway === 'netbank'">
            <p>Bank: {{ transaction.disbursement.metadata.bank_name }}</p>
            <p>Rail: {{ transaction.disbursement.metadata.rail }}</p>
        </div>
        <div v-if="transaction.disbursement.gateway === 'paypal'">
            <p>Email: {{ transaction.disbursement.recipient_identifier }}</p>
            <p>Fee: {{ transaction.disbursement.metadata.fee_amount }}</p>
        </div>
    </CardContent>
</Card>
```

---

## Migration Plan

### Phase 1: Backward Compatible (✅ COMPLETED)
- ✅ Update DTO to support both formats
- ✅ DTO reads both old and new formats correctly
- ✅ UI works with both formats
- ✅ Commit: 4851d74

### Phase 2: Dual Write (✅ COMPLETED)
- ✅ Store in new generic format
- ✅ Include legacy fields in metadata for compatibility
- ✅ New disbursements use generic format
- ✅ Commit: 024c6fe

### Phase 3: Migration Script (After Phase 2 stable)
```php
// Artisan command to migrate old disbursements
Voucher::whereNotNull('metadata->disbursement')
    ->chunk(100, function ($vouchers) {
        foreach ($vouchers as $voucher) {
            $old = $voucher->metadata['disbursement'];
            
            // Convert to new format
            $new = [
                'gateway' => 'netbank',
                'transaction_id' => $old['operation_id'],
                // ... map all fields
            ];
            
            $voucher->metadata = [
                ...$voucher->metadata,
                'disbursement' => $new,
            ];
            $voucher->save();
        }
    });
```

### Phase 4: Cleanup (After all data migrated)
- Remove legacy format support
- Simplify DTO
- Remove backward compatibility code

---

## Benefits

✅ **Gateway Agnostic** - Works with any payment gateway  
✅ **Future Proof** - Easy to add new gateways  
✅ **Backward Compatible** - Existing NetBank data still works  
✅ **Clean API** - Single DTO type for UI  
✅ **Flexible** - Gateway-specific data in metadata  
✅ **Type Safe** - Core fields strongly typed  
✅ **International** - Supports non-Philippine gateways  

---

## Timeline

| Phase | Tasks | Duration |
|-------|-------|----------|
| Phase 1 | Update DTO with dual format support | 2 hours |
| Phase 2 | Update storage to new format | 1 hour |
| Phase 3 | Update UI for generic display | 2 hours |
| Phase 4 | Test with existing data | 1 hour |
| Phase 5 | Migration script | 1 hour |
| **Total** | | **7 hours** |

---

**Status:** 🚀 In Progress (Phase 1 & 2 Complete)  
**Recommendation:** Option 1 (Generic DTO with Metadata)  
**Next Step:** Phase 3 (Optional) - Migration script for existing data
