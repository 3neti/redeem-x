# Wallet Charging Flow

This document explains how wallet charging works during voucher generation and redemption.

## Overview

The system uses a **closed-loop wallet system** where money flows between internal wallets:
- **User wallets** - Customers who generate vouchers
- **Product wallets** - Instruction items that collect fees
- **System wallet** - Only participates in external top-ups

## Voucher Generation Flow

### API Endpoint
```
POST /api/v1/vouchers
```

### Request Example
```json
{
  "amount": 100,
  "count": 1
}
```

### Charging Flow Diagram

```
POST /api/v1/vouchers
    ↓
GenerateVouchers::asController()
    ├─ Generate vouchers (creates Cash entities)
    ├─ Dispatch VouchersGenerated event
    └─ HandleGeneratedVouchers (synchronous)
           └─ Pipeline runs immediately
                  └─ ChargeInstructions
                         ├─ pay($cashEntity) → Escrow to Cash wallet (₱100)
                         └─ pay($instructionItem) → Fees to Products (₱0 for simplest)
```

### Step-by-Step Breakdown

#### 1. Voucher Creation
**Location**: `packages/voucher/src/Actions/GenerateVouchers.php`

Vouchers are created with instructions stored in metadata.

#### 3. Event Dispatch
**Location**: `packages/voucher/src/Actions/GenerateVouchers.php:95`

```php
VouchersGenerated::dispatch($collection);
```

#### 4. Synchronous Pipeline Execution
**Location**: `packages/voucher/src/Listeners/HandleGeneratedVouchers.php`

The listener runs **synchronously** (no `ShouldQueue`), processing vouchers immediately:

```php
app(Pipeline::class)
    ->send($unprocessed)
    ->through($post_generation_pipeline_array)
    ->thenReturn();
```

#### 5. Escrow and Fee Charging
**Location**: `app/Pipelines/GeneratedVoucher/ChargeInstructions.php`

Runs for each voucher in the batch:

```php
$charges = $this->evaluator->evaluate($voucher->owner, $voucher->instructions);

foreach ($charges as $charge) {
    if ($charge['item'] !== null) {
        $owner->pay($charge['item']); // Transfer from user to item wallet
    }
}
```

**What gets charged:**

1. **Cash Entity** (always):
   - `$owner->pay($voucher->cash)` where `$voucher->cash` is the Cash entity
   - User wallet: **-₱100** → Cash entity wallet: **+₱100** (escrow)
   - The Cash entity's wallet IS the escrow account

2. **Instruction Items** (if configured):
   - Only charged if instruction has non-default values
   - **Simplest voucher** (no inputs/feedbacks): ₱0 fees
   - Example with inputs: `inputs.fields.email` = ₱2.20 per voucher

**Note**: `cash.amount` InstructionItem is **excluded** from evaluation to prevent double-charging.

### Final Balance Changes

**For simplest voucher** (1 voucher of ₱100, no inputs/feedbacks):

| Wallet | Change | Reason |
|--------|--------|--------|
| User | **-₱100** | ₱100 escrow only |
| Cash Entity #123 | **+₱100** | Escrow held here |
| Products | **±0** | No fees for simplest voucher |
| System | **±0** | No change (closed system) |

**With input fields** (e.g., email + location):

| Wallet | Change | Reason |
|--------|--------|--------|
| User | **-₱105.20** | ₱100 escrow + ₱5.20 fees |
| Cash Entity | **+₱100** | Escrow |
| Products | **+₱5.20** | Fees (₱2.20 email + ₱3.00 location) |
| System | **±0** | No change |

### Money Flow

**Simplest Voucher** (₱100, no fees):
```
┌──────────────┌
│  User Wallet │
│  -₱100       │
└───────┬───────┘
        │
        └─── ₱100 ───→ ┌──────────────────┌
                       │ Cash Entity Wallet │
                       │ +₱100 (escrow)    │
                       └──────────────────┘
```

**With Input Fields** (₱100 + ₱5.20 fees):
```
┌──────────────┌
│  User Wallet │
│  -₱105.20    │
└───────┬───────┘
        │
        ├─── ₱100 ───→ ┌──────────────────┌
        │              │ Cash Entity Wallet │
        │              │ +₱100 (escrow)    │
        │              └──────────────────┘
        │
        └─── ₱5.20 ──→ ┌────────────────┌
                       │ Product Wallets │
                       │ +₱5.20 (fees)  │
                       └────────────────┘
```

## Understanding Cash Entity Escrow

### What is a Cash Entity?

A **Cash entity** (`LBHurtado\Cash\Models\Cash`) represents the monetary value of a voucher:
- Created automatically when a voucher is generated
- Has its own wallet (implements `HasWallet` trait)
- Holds the escrowed funds until redemption
- Linked to voucher via `voucher->cash` relationship

### How Money Flows

**Generation:**
```php
// In ChargeInstructions pipeline:
$voucher->owner->pay($voucher->cash);
// This creates TWO transactions:
// TX 1: User wallet -₱100 (withdraw)
// TX 2: Cash entity wallet +₱100 (deposit)
```

**Redemption:**
```php
// In DisburseCash pipeline:
$voucher->cash->wallet->transfer($redeemer->wallet, $amount);
// Transfers from Cash entity → redeemer
```

### Why This Design?

1. **Each voucher has isolated escrow** - Can't accidentally mix funds
2. **Traceable money trail** - Clear audit path via wallet transactions
3. **Leverages Bavix Wallet** - Don't reinvent escrow bookkeeping
4. **Atomic operations** - `pay()` ensures withdraw + deposit succeed together

## Key Design Decisions

### Why Synchronous Processing?

**Previous Approach** (Async):
- ❌ Pipeline ran in queue
- ❌ Postman tests failed (balance not updated yet)
- ❌ User uncertainty (did payment go through?)

**Current Approach** (Sync):
- ✅ Immediate wallet updates
- ✅ Instant user feedback
- ✅ Simpler error handling
- ✅ No queue dependencies

### How Escrow Works via Bavix Wallet

When a voucher is generated:
1. A **Cash entity** is created with the voucher amount
2. The Cash entity has its own wallet (via `HasWallet` trait)
3. `pay($cashEntity)` transfers money from user → Cash entity wallet
4. The Cash entity's wallet **IS** the escrow account
5. On redemption, money transfers from Cash entity → redeemer

**Why this is correct:**
- Each Cash entity tracks its own escrow balance
- No manual escrow bookkeeping needed
- Leverages Bavix Wallet's atomic transactions
- Money is actually "held" in a real wallet, not just recorded

### Why Use `pay()` Instead of `withdrawFloat()`?

```php
// ❌ Manual approach (don't do this)
$user->withdrawFloat($fee);
$product->depositFloat($fee);

// ✅ Proper Bavix Wallet method
$user->pay($product);
```

**Benefits of `pay()`:**
- Single atomic transaction
- Built-in transfer tracking
- Consistent with wallet package patterns
- Idempotent (safe if called multiple times)

## Configuration

### Instruction Item Pricing

Fees are configured per instruction item in the database:

```sql
SELECT index, price FROM instruction_items WHERE price > 0;
```

Example:
- `cash.amount`: ₱20.00 (**excluded** from charging to prevent double-escrow)
- `inputs.fields.email`: ₱2.20
- `inputs.fields.location`: ₱3.00
- `inputs.fields.selfie`: ₱4.00

**Note**: `cash.amount` exists in the database but is excluded in `InstructionCostEvaluator::$excludedFields` because the Cash entity itself handles escrow via `pay($cashEntity)`.

### Pipeline Configuration

**Location**: `config/voucher-pipeline.php`

```php
'mint-cash' => [
    \LBHurtado\Voucher\Pipelines\Voucher\CheckBalance::class,
    \LBHurtado\Voucher\Pipelines\Voucher\EscrowAction::class,
    \LBHurtado\Voucher\Pipelines\Voucher\PersistCash::class,
    \App\Pipelines\GeneratedVoucher\ChargeInstructions::class, // ← Fees charged here
],
```

## Testing

### Postman Collection

**Location**: `docs/postman/redeem-x-e2e-generation-billing.postman_collection.json`

Tests the complete flow:
1. Get system balances (before)
2. Get user balance (before)
3. Generate voucher (simplest: ₱100, no inputs)
4. Get user balance (after) - verify -₱100 (escrow only, no fees)
5. Get voucher details - show fee breakdown
6. Get system balances (after) - verify products ±0 (no fees for simplest)

### API Endpoint for Testing

```
GET /api/v1/system/balances
```

Returns:
```json
{
  "system": {
    "email": "admin@disburse.cash",
    "balance": 1004755.8,
    "currency": "PHP"
  },
  "products": [...],
  "totals": {
    "system": 1004755.8,
    "products": 7416.0,
    "combined": 1012171.8
  }
}
```

## Troubleshooting

### Balance Not Updated After Generation

**Symptom**: User balance unchanged after voucher generation

**Causes**:
1. ✅ Pipeline is synchronous - should work immediately
2. Check for exceptions in logs: `tail -f storage/logs/laravel.log`
3. Verify instruction items exist: `SELECT * FROM instruction_items WHERE index = 'cash.amount'`

### Double Charging

**Symptom**: User charged ₱200 instead of ₱100 for simplest voucher

**Cause**: Duplicate escrow withdrawal - once via `pay($cashEntity)` in pipeline, once via manual `withdrawFloat()` in GenerateVouchers

**Solution**: 
- ✅ Keep: `pay($cashEntity)` in ChargeInstructions pipeline
- ❌ Remove: Manual `withdrawFloat()` in GenerateVouchers.php
- ✅ Exclude: `cash.amount` from `InstructionCostEvaluator::$excludedFields`

### Products Not Receiving Fees

**Symptom**: User charged but product wallets unchanged

**Causes**:
1. Check if `InstructionItem` has wallet: `SELECT * FROM wallets WHERE holder_type = 'App\Models\InstructionItem'`
2. Check if item price > 0: `SELECT price FROM instruction_items WHERE index = 'cash.amount'`
3. Check logs for pipeline errors

## Related Documentation

- [Voucher Lifecycle](VOUCHER_LIFECYCLE.md)
- [Pricing System](ARCHITECTURE-PRICING.md)
- [Postman Collections](postman/README.md)

## Future Enhancements

- [ ] Add batch voucher generation progress indicator (if >100 vouchers)
- [ ] Add webhook notifications when generation completes
- [ ] Add refund mechanism for cancelled vouchers
- [ ] Add detailed transaction history per voucher
