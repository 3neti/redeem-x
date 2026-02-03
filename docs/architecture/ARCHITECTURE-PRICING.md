# Architecture: Dynamic Instruction Customization Pricing for redeem-x

## Overview

This document describes the architecture for implementing a per-customization pricing model for voucher generation in redeem-x. The system charges users (tenants) based on which features they enable in their `VoucherInstructionsData` when generating vouchers.

---

## Core Principles

✅ **Zero modifications** to the voucher package  
✅ User = Tenant (each user is a paying customer)  
✅ Transparent pricing with itemized charges  
✅ Full audit trail for pricing changes  
✅ Admin role-based pricing management  

---

## System Architecture Diagram

```
┌───────────────────────────────────────────────────────┐
│  User (Tenant/Customer)                               │
│  • Authenticated via WorkOS                           │
│  • Generates vouchers (pays per customization)        │
│  • Views billing dashboard                            │
└────────────┬──────────────────────────────────────────┘
             │
             ▼ Creates vouchers
┌───────────────────────────────────────────────────────┐
│  Frontend: VoucherInstructionsForm.vue                │
│  • User configures VoucherInstructionsData            │
│  • useChargeBreakdown composable shows real-time price│
│  • Displays itemized charges per field                │
└────────────┬──────────────────────────────────────────┘
             │
             ▼ POST /vouchers (generate)
┌───────────────────────────────────────────────────────┐
│  Controller: VoucherController@store                  │
│  1. CalculateChargeAction → get pricing               │
│  2. GenerateVouchers (package) → create vouchers      │
│  3. VoucherGenerationCharge → record billing          │
│  4. user_voucher pivot → link user to vouchers        │
└────────────┬──────────────────────────────────────────┘
             │
             ▼ Uses
┌───────────────────────────────────────────────────────┐
│  Service: InstructionCostEvaluator                    │
│  • Traverses VoucherInstructionsData (dot notation)   │
│  • Checks "truthiness" of each field                  │
│  • Applies pricing from instruction_items             │
│  • Returns ChargeBreakdownData                        │
└────────────┬──────────────────────────────────────────┘
             │
             ▼ Queries
┌───────────────────────────────────────────────────────┐
│  Repository: InstructionItemRepository                │
│  • Fetches instruction_items (pricing catalog)        │
│  • Filters by index, type, etc.                       │
└────────────┬──────────────────────────────────────────┘
             │
             ▼ Reads from
┌───────────────────────────────────────────────────────┐
│  Database Tables:                                     │
│  • instruction_items (pricing catalog)                │
│  • instruction_item_price_history (audit trail)       │
│  • voucher_generation_charges (billing records)       │
│  • user_voucher (pivot: user ↔ voucher)               │
│  • vouchers (package table - untouched)               │
└───────────────────────────────────────────────────────┘
```

---

## Database Schema

### Table: `user_voucher`
Links users to the vouchers they generated (pivot table).

```sql
CREATE TABLE user_voucher (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    voucher_code VARCHAR(255) NOT NULL,
    generated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (voucher_code) REFERENCES vouchers(code) ON DELETE CASCADE,
    UNIQUE KEY unique_user_voucher (user_id, voucher_code),
    INDEX idx_user_id (user_id),
    INDEX idx_voucher_code (voucher_code)
);
```

---

### Table: `voucher_generation_charges`
Records billing information for each voucher generation batch.

```sql
CREATE TABLE voucher_generation_charges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NULL,
    voucher_codes JSON NOT NULL,              -- ['CODE1', 'CODE2', ...]
    voucher_count INT NOT NULL,
    instructions_snapshot JSON NOT NULL,      -- Full VoucherInstructionsData
    charge_breakdown JSON NOT NULL,           -- {'cash.amount': 20.00, 'feedback.email': 1.00}
    total_charge DECIMAL(10, 2) NOT NULL,     -- Sum of charge_breakdown
    charge_per_voucher DECIMAL(10, 2) NOT NULL,
    generated_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    INDEX idx_user_generated (user_id, generated_at),
    INDEX idx_campaign_id (campaign_id)
);
```

---

### Table: `instruction_items`
Pricing catalog for all chargeable instruction customizations.

```sql
CREATE TABLE instruction_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,               -- "Email Address"
    index VARCHAR(255) NOT NULL UNIQUE,       -- "feedback.email" (dot notation)
    type VARCHAR(100) NOT NULL,               -- "feedback", "inputs", "validation"
    price INT NOT NULL DEFAULT 0,             -- Centavos (100 = ₱1.00)
    currency VARCHAR(3) NOT NULL DEFAULT 'PHP',
    meta JSON NULL,                           -- {label: "...", description: "..."}
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_type (type)
);
```

---

### Table: `instruction_item_price_history`
Audit trail for all pricing changes.

```sql
CREATE TABLE instruction_item_price_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instruction_item_id BIGINT UNSIGNED NOT NULL,
    old_price INT NOT NULL,
    new_price INT NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PHP',
    changed_by BIGINT UNSIGNED NULL,          -- Admin user who changed it
    reason TEXT NULL,                         -- Why the price changed
    effective_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (instruction_item_id) REFERENCES instruction_items(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

---

## Models & Relationships

### User Model
```php
class User extends Authenticatable
{
    use HasRoles;
    
    public function generatedVouchers()
    {
        return $this->belongsToMany(Voucher::class, 'user_voucher', 'user_id', 'voucher_code', 'id', 'code')
            ->withTimestamps();
    }
    
    public function voucherGenerationCharges()
    {
        return $this->hasMany(VoucherGenerationCharge::class);
    }
    
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
    
    public function monthlyCharges(\DateTime $month = null)
    {
        $month = $month ?? now();
        return $this->voucherGenerationCharges()
            ->whereBetween('generated_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('total_charge');
    }
}
```

### VoucherGenerationCharge Model
```php
class VoucherGenerationCharge extends Model
{
    protected $fillable = [
        'user_id', 'campaign_id', 'voucher_codes', 'voucher_count',
        'instructions_snapshot', 'charge_breakdown', 'total_charge',
        'charge_per_voucher', 'generated_at'
    ];

    protected $casts = [
        'voucher_codes' => 'array',
        'instructions_snapshot' => 'array',
        'charge_breakdown' => 'array',
        'total_charge' => 'decimal:2',
        'charge_per_voucher' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
    
    public function vouchers()
    {
        return Voucher::whereIn('code', $this->voucher_codes)->get();
    }
}
```

### InstructionItem Model
```php
class InstructionItem extends Model
{
    protected $fillable = ['name', 'index', 'type', 'price', 'currency', 'meta'];
    protected $casts = ['meta' => 'array'];

    protected static function booted()
    {
        static::updating(function ($item) {
            if ($item->isDirty('price')) {
                $item->priceHistory()->create([
                    'old_price' => $item->getOriginal('price'),
                    'new_price' => $item->price,
                    'changed_by' => auth()->id(),
                    'effective_at' => now(),
                ]);
            }
        });
    }

    public function priceHistory()
    {
        return $this->hasMany(InstructionItemPriceHistory::class);
    }

    public function getAmountProduct(User $customer): int
    {
        // Future: VIP discounts, volume pricing
        return $this->price;
    }

    public static function attributesFromIndex(string $index, array $overrides = []): array
    {
        return array_merge([
            'index'    => $index,
            'name'     => Str::of($index)->afterLast('.')->headline(),
            'type'     => Str::of($index)->explode('.')[1] ?? 'general',
            'price'    => 0,
            'currency' => 'PHP',
            'meta'     => [],
        ], $overrides);
    }
}
```

### InstructionItemPriceHistory Model
```php
class InstructionItemPriceHistory extends Model
{
    protected $fillable = [
        'instruction_item_id', 'old_price', 'new_price', 'currency',
        'changed_by', 'reason', 'effective_at'
    ];

    protected $casts = ['effective_at' => 'datetime'];

    public function instructionItem(): BelongsTo
    {
        return $this->belongsTo(InstructionItem::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
    
    public function priceDifference(): int
    {
        return $this->new_price - $this->old_price;
    }
    
    public function percentageChange(): float
    {
        if ($this->old_price === 0) return 100.0;
        return (($this->new_price - $this->old_price) / $this->old_price) * 100;
    }
}
```

---

## Service Layer

### InstructionItemRepository
```php
class InstructionItemRepository
{
    public function all(): Collection
    {
        return InstructionItem::all();
    }

    public function findByIndex(string $index): ?InstructionItem
    {
        return InstructionItem::where('index', $index)->first();
    }

    public function findByIndices(array $indices): Collection
    {
        return InstructionItem::whereIn('index', $indices)->get();
    }

    public function allByType(string $type): Collection
    {
        return InstructionItem::where('type', $type)->get();
    }

    public function totalCharge(array $indices): int
    {
        return $this->findByIndices($indices)->sum('price');
    }

    public function descriptionsFor(array $indices): array
    {
        return $this->findByIndices($indices)->mapWithKeys(function ($item) {
            return [$item->index => $item->meta['description'] ?? ''];
        })->toArray();
    }
}
```

### InstructionCostEvaluator
```php
class InstructionCostEvaluator
{
    protected array $excludedFields = ['count', 'mask', 'ttl', 'starts_at', 'expires_at'];

    public function __construct(protected InstructionItemRepository $repository) {}

    public function evaluate(User $customer, VoucherInstructionsData $source): Collection
    {
        $charges = collect();
        $items = $this->repository->all();

        foreach ($items as $item) {
            if (in_array($item->index, $this->excludedFields)) {
                continue;
            }

            $value = data_get($source, $item->index);
            
            $isTruthyString = is_string($value) && trim($value) !== '';
            $isTruthyBoolean = is_bool($value) && $value === true;
            $isTruthyFloat = is_float($value) && $value > 0.0;
            $shouldCharge = ($isTruthyString || $isTruthyBoolean || $isTruthyFloat) && $item->price > 0;

            if ($shouldCharge) {
                $price = $item->getAmountProduct($customer);
                $label = $item->meta['label'] ?? $item->name;

                $charges->push([
                    'item' => $item,
                    'value' => $value,
                    'price' => $price,
                    'currency' => $item->currency,
                    'label' => $label,
                ]);
            }
        }

        return $charges;
    }
}
```

---

## Action Layer

### ChargeBreakdownData DTO
```php
class ChargeBreakdownData extends Data
{
    public function __construct(
        public array $breakdown, // ['cash.amount' => 20.00, 'feedback.email' => 1.00]
        public float $total      // Total charge to customer
    ) {}
}
```

### CalculateChargeAction
```php
class CalculateChargeAction
{
    use AsAction;

    public function __construct(protected InstructionCostEvaluator $evaluator) {}

    public function handle(User $user, VoucherInstructionsData $instructions): ChargeBreakdownData
    {
        $breakdown = ['cash.amount' => $instructions->cash->amount];

        $charges = $this->evaluator->evaluate($user, $instructions);
        
        foreach ($charges as $charge) {
            $breakdown[$charge['item']->index] = Money::ofMinor($charge['price'], 'PHP')
                ->getAmount()
                ->toFloat();
        }

        $total = array_sum($breakdown);

        return new ChargeBreakdownData(breakdown: $breakdown, total: $total);
    }

    public function asController(ActionRequest $request)
    {
        $instructions = VoucherInstructionsData::from($request->all());
        return $this->handle($request->user(), $instructions);
    }
}
```

---

## Controller Integration

### Voucher Generation Flow
```php
class VoucherController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        $instructions = VoucherInstructionsData::from($request->all());
        
        // 1. Calculate charges
        $chargeBreakdown = app(CalculateChargeAction::class)->handle($user, $instructions);
        
        // 2. Generate vouchers (package)
        $vouchers = app(GenerateVouchers::class)->execute($instructions);
        $voucherCodes = collect($vouchers)->pluck('code')->toArray();
        
        // 3. Record billing
        VoucherGenerationCharge::create([
            'user_id' => $user->id,
            'campaign_id' => $request->campaign_id ?? null,
            'voucher_codes' => $voucherCodes,
            'voucher_count' => count($voucherCodes),
            'instructions_snapshot' => $instructions->toArray(),
            'charge_breakdown' => $chargeBreakdown->breakdown,
            'total_charge' => $chargeBreakdown->total,
            'charge_per_voucher' => $chargeBreakdown->total / count($voucherCodes),
            'generated_at' => now(),
        ]);
        
        // 4. Link to user
        foreach ($voucherCodes as $code) {
            DB::table('user_voucher')->insert([
                'user_id' => $user->id,
                'voucher_code' => $code,
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return back()->with('vouchers_generated', [
            'vouchers' => $voucherCodes,
            'charge' => $chargeBreakdown->total,
        ]);
    }
}
```

---

## Configuration

### config/redeem.php
```php
'pricelist' => [
    'cash.amount' => [
        'price' => 2000, // ₱20.00 base fee
        'description' => 'Cash voucher generation base fee',
    ],
    'feedback.email' => [
        'price' => 100, // ₱1.00
        'label' => 'Email Address',
        'description' => 'Email notification on redemption',
    ],
    'feedback.mobile' => [
        'price' => 180,
        'label' => 'Mobile Number',
        'description' => 'SMS notification',
    ],
    'feedback.webhook' => [
        'price' => 190,
        'label' => 'Webhook URL',
        'description' => 'Webhook notification',
    ],
    'inputs.fields.signature' => [
        'price' => 280,
        'description' => 'Signature capture field',
    ],
    'inputs.fields.location' => [
        'price' => 300,
        'description' => 'GPS location capture field',
    ],
    // ... more items
],
```

---

## Frontend Layer

### useChargeBreakdown Composable
```typescript
export function useChargeBreakdown(form: any, excluded: string[] = []) {
    const chargeBreakdown = ref<any>(null)
    const loading = ref(false)
    
    const payload = computed(() => {
        const cleaned = JSON.parse(JSON.stringify(form))
        excluded.forEach(field => delete cleaned[field])
        return cleaned
    })

    async function calculateCharge() {
        loading.value = true
        try {
            const response = await axios.post(route('calculate-charge'), payload.value)
            chargeBreakdown.value = response.data
        } finally {
            loading.value = false
        }
    }

    function getChargeComponent(path: string): number | undefined {
        return chargeBreakdown.value?.breakdown?.[path]
    }

    watch(payload, debounce(() => calculateCharge(), 500), { deep: true, immediate: true })

    return { chargeBreakdown, getChargeComponent, loading }
}
```

### VoucherInstructionsForm Integration
```vue
<script setup>
const excluded = ['count', 'mask', 'ttl', 'starts_at', 'expires_at']
const { chargeBreakdown, getChargeComponent, loading } = useChargeBreakdown(props.modelValue, excluded)

function getChargeMessage(index: string): string {
    const charge = getChargeComponent(index)
    return charge ? formatCurrency(charge) : ''
}
</script>

<template>
  <div class="space-y-2">
    <Label>Email for Feedback</Label>
    <Input v-model="modelValue.feedback.email" />
    <div v-if="getChargeMessage('feedback.email')" class="text-right text-xs text-gray-500">
      +{{ getChargeMessage('feedback.email') }}
    </div>
  </div>

  <div class="mt-6 border-t pt-4">
    <div class="flex justify-between">
      <span class="font-medium">Total Charge:</span>
      <span class="text-lg font-bold text-red-600">
        {{ loading ? 'Calculating...' : formatCurrency(chargeBreakdown?.total ?? 0) }}
      </span>
    </div>
  </div>
</template>
```

---

## Roles & Permissions

### Spatie Laravel Permission Setup
```php
// Permissions
Permission::create(['name' => 'manage pricing']);
Permission::create(['name' => 'view all billing']);
Permission::create(['name' => 'manage users']);

// Roles
$superAdmin = Role::create(['name' => 'super-admin']);
$superAdmin->givePermissionTo(['manage pricing', 'view all billing', 'manage users']);
```

### Routes
```php
Route::middleware(['auth', 'workos'])->group(function () {
    Route::post('/api/calculate-charge', CalculateChargeAction::class)->name('calculate-charge');
    Route::get('/settings/billing', [BillingController::class, 'index'])->name('settings.billing');
    
    Route::middleware('permission:manage pricing')->group(function () {
        Route::resource('/settings/pricing', PricingController::class)->names('settings.pricing');
    });
});
```

---

## Example Pricing Calculation

**Scenario:** 10 vouchers with email, signature, and location

```json
{
  "cash.amount": 20.00,
  "feedback.email": 1.00,
  "inputs.fields.signature": 2.80,
  "inputs.fields.location": 3.00
}
```

**Per Voucher:** ₱26.80  
**Total (10 vouchers):** ₱268.00

---

## Key Design Decisions

| Aspect | Decision | Rationale |
|--------|----------|-----------|
| Naming | `voucher_generation_charges` | Customer-facing clarity |
| Package Isolation | Zero voucher table modifications | Safe upgrades |
| Pricing Storage | Database with audit trail | Dynamic, trackable |
| Excluded Fields | count, mask, ttl, dates | Metadata only |
| User = Tenant | Per-user billing | WorkOS auth model |
| Frontend Updates | Debounced (500ms) | Performance |

---

## Future Extensions

### Expense Tracking (Revenue vs Cost)
Add `voucher_generation_expenses` table to track actual costs (SMS, email, server) and calculate gross profit.

### Volume Discounts
Add `pricing_tiers` table for bulk pricing (e.g., 1M vouchers → 20% off).

### Custom Per-User Pricing
Extend `getAmountProduct()` method for VIP customers or negotiated rates.

---

## Implementation Checklist

See `IMPLEMENTATION-PLAN.md` for detailed step-by-step guide.
