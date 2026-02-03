# Implementation Plan: Pricing System for redeem-x

This document provides a detailed, step-by-step implementation plan for the dynamic instruction customization pricing system.

---

## Overview

**Goal:** Implement a per-customization pricing model that charges users based on which features they enable in voucher generation, with full audit trail and admin management UI.

**Timeline:** ~3-4 days for Phase 1 (MVP)

---

## Phase 1: Core Pricing System + Admin UI (MVP)

### Prerequisites
- [ ] Ensure `spatie/laravel-data` is installed
- [ ] Ensure `brick/money` is installed
- [ ] Laravel Actions installed (if using `lorisleiva/actions`)

---

## Step 1: Database Setup

### 1.1 Install spatie/laravel-permission
```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 1.2 Create Migrations

#### Migration 1: `user_voucher` table
```bash
php artisan make:migration create_user_voucher_table
```

**File:** `database/migrations/xxxx_create_user_voucher_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_voucher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_code')->index();
            $table->foreign('voucher_code')
                  ->references('code')
                  ->on('vouchers')
                  ->cascadeOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->unique(['user_id', 'voucher_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_voucher');
    }
};
```

#### Migration 2: `instruction_items` table
```bash
php artisan make:migration create_instruction_items_table
```

**File:** `database/migrations/xxxx_create_instruction_items_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruction_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('index')->unique();
            $table->string('type');
            $table->integer('price')->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruction_items');
    }
};
```

#### Migration 3: `instruction_item_price_history` table
```bash
php artisan make:migration create_instruction_item_price_history_table
```

**File:** `database/migrations/xxxx_create_instruction_item_price_history_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruction_item_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_item_id')->constrained()->cascadeOnDelete();
            $table->integer('old_price');
            $table->integer('new_price');
            $table->string('currency', 3)->default('PHP');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruction_item_price_history');
    }
};
```

#### Migration 4: `voucher_generation_charges` table
```bash
php artisan make:migration create_voucher_generation_charges_table
```

**File:** `database/migrations/xxxx_create_voucher_generation_charges_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_generation_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->json('voucher_codes');
            $table->integer('voucher_count');
            $table->json('instructions_snapshot');
            $table->json('charge_breakdown');
            $table->decimal('total_charge', 10, 2);
            $table->decimal('charge_per_voucher', 10, 2);
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index(['user_id', 'generated_at']);
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_generation_charges');
    }
};
```

### 1.3 Run Migrations
```bash
php artisan migrate
```

---

## Step 2: Configuration

### 2.1 Update `config/redeem.php`

Add pricelist section:

```php
// config/redeem.php

return [
    // ... existing config
    
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
            'price' => 180, // ₱1.80
            'label' => 'Mobile Number',
            'description' => 'SMS notification',
        ],
        'feedback.webhook' => [
            'price' => 190, // ₱1.90
            'label' => 'Webhook URL',
            'description' => 'Webhook notification',
        ],
        'cash.validation.secret' => [
            'price' => 120, // ₱1.20
            'description' => 'Secret code validation',
        ],
        'cash.validation.mobile' => [
            'price' => 130, // ₱1.30
            'description' => 'Mobile number validation',
        ],
        'cash.validation.location' => [
            'price' => 150, // ₱1.50
            'description' => 'GPS location validation',
        ],
        'cash.validation.radius' => [
            'price' => 160, // ₱1.60
            'description' => 'Radius validation',
        ],
        'inputs.fields.email' => [
            'price' => 220, // ₱2.20
            'description' => 'Email input field',
        ],
        'inputs.fields.mobile' => [
            'price' => 230, // ₱2.30
            'description' => 'Mobile number input field',
        ],
        'inputs.fields.name' => [
            'price' => 240, // ₱2.40
            'description' => 'Name input field',
        ],
        'inputs.fields.address' => [
            'price' => 250, // ₱2.50
            'label' => 'Full Address',
            'description' => 'Address input field',
        ],
        'inputs.fields.birth_date' => [
            'price' => 260, // ₱2.60
            'description' => 'Birth date input field',
        ],
        'inputs.fields.gross_monthly_income' => [
            'price' => 270, // ₱2.70
            'description' => 'Gross monthly income input field',
        ],
        'inputs.fields.signature' => [
            'price' => 280, // ₱2.80
            'description' => 'Signature capture field',
        ],
        'inputs.fields.location' => [
            'price' => 300, // ₱3.00
            'description' => 'GPS location capture field',
        ],
        'inputs.fields.reference_code' => [
            'price' => 250, // ₱2.50
            'description' => 'Reference code input field',
        ],
        'inputs.fields.otp' => [
            'price' => 400, // ₱4.00
            'description' => 'OTP verification field',
        ],
        'rider.message' => [
            'price' => 200, // ₱2.00
            'label' => 'Rider Message',
            'description' => 'Custom message shown after redemption',
        ],
        'rider.url' => [
            'price' => 210, // ₱2.10
            'label' => 'Rider URL',
            'description' => 'Redirect URL after redemption',
        ],
    ],
];
```

---

## Step 3: Create Models

### 3.1 InstructionItem Model
```bash
php artisan make:model InstructionItem
```

**File:** `app/Models/InstructionItem.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InstructionItem extends Model
{
    protected $fillable = [
        'name',
        'index',
        'type',
        'price',
        'currency',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

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

    public function getMetaProduct(): ?array
    {
        return [
            'type' => $this->type,
            'title' => $this->meta['title'] ?? ucfirst($this->type),
            'description' => $this->meta['description'] ?? "Charge for {$this->type} instruction",
        ];
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

### 3.2 InstructionItemPriceHistory Model
```bash
php artisan make:model InstructionItemPriceHistory
```

**File:** `app/Models/InstructionItemPriceHistory.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionItemPriceHistory extends Model
{
    protected $fillable = [
        'instruction_item_id',
        'old_price',
        'new_price',
        'currency',
        'changed_by',
        'reason',
        'effective_at',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

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
        if ($this->old_price === 0) {
            return 100.0;
        }
        return (($this->new_price - $this->old_price) / $this->old_price) * 100;
    }
}
```

### 3.3 VoucherGenerationCharge Model
```bash
php artisan make:model VoucherGenerationCharge
```

**File:** `app/Models/VoucherGenerationCharge.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\Voucher\Models\Voucher;

class VoucherGenerationCharge extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'voucher_codes',
        'voucher_count',
        'instructions_snapshot',
        'charge_breakdown',
        'total_charge',
        'charge_per_voucher',
        'generated_at',
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
    
    public function grossProfit(): float
    {
        // Future: subtract actual expenses
        return $this->total_charge;
    }
}
```

### 3.4 Update User Model

**File:** `app/Models/User.php`

Add these methods to existing User model:
```php
use LBHurtado\Voucher\Models\Voucher;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    
    // Add these relationships:
    
    public function generatedVouchers()
    {
        return $this->belongsToMany(
            Voucher::class,
            'user_voucher',
            'user_id',
            'voucher_code',
            'id',
            'code'
        )->withTimestamps();
    }
    
    public function voucherGenerationCharges()
    {
        return $this->hasMany(VoucherGenerationCharge::class);
    }
    
    public function monthlyCharges(\DateTime $month = null)
    {
        $month = $month ?? now();
        
        return $this->voucherGenerationCharges()
            ->whereBetween('generated_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth()
            ])
            ->sum('total_charge');
    }
}
```

---

## Step 4: Create Repository

### 4.1 InstructionItemRepository
```bash
mkdir -p app/Repositories
touch app/Repositories/InstructionItemRepository.php
```

**File:** `app/Repositories/InstructionItemRepository.php`
```php
<?php

namespace App\Repositories;

use App\Models\InstructionItem;
use Illuminate\Support\Collection;

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

---

## Step 5: Create Service Layer

### 5.1 InstructionCostEvaluator
```bash
mkdir -p app/Services
touch app/Services/InstructionCostEvaluator.php
```

**File:** `app/Services/InstructionCostEvaluator.php`
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\InstructionItemRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class InstructionCostEvaluator
{
    protected array $excludedFields = [
        'count',
        'mask',
        'ttl',
        'starts_at',
        'expires_at',
    ];

    public function __construct(
        protected InstructionItemRepository $repository
    ) {}

    public function evaluate(User $customer, VoucherInstructionsData $source): Collection
    {
        $charges = collect();
        $items = $this->repository->all();

        Log::debug('[InstructionCostEvaluator] Starting evaluation', [
            'user_id' => $customer->id,
            'instruction_items_count' => $items->count(),
        ]);

        foreach ($items as $item) {
            if (in_array($item->index, $this->excludedFields)) {
                continue;
            }

            $value = data_get($source, $item->index);
            
            $isTruthyString = is_string($value) && trim($value) !== '';
            $isTruthyBoolean = is_bool($value) && $value === true;
            $isTruthyFloat = is_float($value) && $value > 0.0;
            $shouldCharge = ($isTruthyString || $isTruthyBoolean || $isTruthyFloat) && $item->price > 0;

            $price = $item->getAmountProduct($customer);

            Log::debug("[InstructionCostEvaluator] Evaluating: {$item->index}", [
                'value' => $value,
                'type' => gettype($value),
                'price' => $price,
                'should_charge' => $shouldCharge,
            ]);

            if ($shouldCharge) {
                $label = $item->meta['label'] ?? $item->name;

                Log::info('[InstructionCostEvaluator] ✅ Chargeable instruction', [
                    'index' => $item->index,
                    'label' => $label,
                    'price' => $price,
                ]);

                $charges->push([
                    'item' => $item,
                    'value' => $value,
                    'price' => $price,
                    'currency' => $item->currency,
                    'label' => $label,
                ]);
            }
        }

        Log::info('[InstructionCostEvaluator] Evaluation complete', [
            'total_items_charged' => $charges->count(),
            'total_amount' => $charges->sum('price'),
        ]);

        return $charges;
    }
}
```

---

## Step 6: Create Data Transfer Objects

### 6.1 ChargeBreakdownData
```bash
mkdir -p app/Data
touch app/Data/ChargeBreakdownData.php
```

**File:** `app/Data/ChargeBreakdownData.php`
```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ChargeBreakdownData extends Data
{
    public function __construct(
        public array $breakdown, // ['cash.amount' => 20.00, 'feedback.email' => 1.00]
        public float $total      // Total charge to customer
    ) {}
}
```

---

## Step 7: Create Actions

### 7.1 CalculateChargeAction
```bash
mkdir -p app/Actions
touch app/Actions/CalculateChargeAction.php
```

**File:** `app/Actions/CalculateChargeAction.php`
```php
<?php

namespace App\Actions;

use App\Data\ChargeBreakdownData;
use App\Models\User;
use App\Services\InstructionCostEvaluator;
use Brick\Money\Money;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateChargeAction
{
    use AsAction;

    public function __construct(
        protected InstructionCostEvaluator $evaluator
    ) {}

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

        return new ChargeBreakdownData(
            breakdown: $breakdown,
            total: $total
        );
    }

    public function asController(Request $request)
    {
        $instructions = VoucherInstructionsData::from($request->all());
        return $this->handle($request->user(), $instructions);
    }
}
```

---

## Step 8: Create Seeders

### 8.1 InstructionItemSeeder
```bash
php artisan make:seeder InstructionItemSeeder
```

**File:** `database/seeders/InstructionItemSeeder.php`
```php
<?php

namespace Database\Seeders;

use App\Models\InstructionItem;
use Illuminate\Database\Seeder;

class InstructionItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = config('redeem.pricelist', []);

        foreach ($items as $index => $data) {
            InstructionItem::firstOrCreate(
                ['index' => $index],
                InstructionItem::attributesFromIndex($index, [
                    'price' => $data['price'],
                    'currency' => 'PHP',
                    'meta' => [
                        'description' => $data['description'] ?? null,
                        'label' => $data['label'] ?? null,
                    ],
                ])
            );
        }
    }
}
```

### 8.2 RolePermissionSeeder
```bash
php artisan make:seeder RolePermissionSeeder
```

**File:** `database/seeders/RolePermissionSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        Permission::create(['name' => 'manage pricing']);
        Permission::create(['name' => 'view all billing']);
        Permission::create(['name' => 'manage users']);

        // Create roles
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo([
            'manage pricing',
            'view all billing',
            'manage users',
        ]);
    }
}
```

### 8.3 Update DatabaseSeeder
```php
// database/seeders/DatabaseSeeder.php

public function run(): void
{
    $this->call([
        RolePermissionSeeder::class,
        InstructionItemSeeder::class,
    ]);
}
```

### 8.4 Run Seeders
```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=InstructionItemSeeder
```

---

## Step 9: Create Controllers

### 9.1 Settings/PricingController
```bash
php artisan make:controller Settings/PricingController --resource
```

**File:** `app/Http/Controllers/Settings/PricingController.php`
```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InstructionItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index()
    {
        $items = InstructionItem::with('priceHistory')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return Inertia::render('Settings/Pricing/Index', [
            'items' => $items,
        ]);
    }

    public function edit(InstructionItem $pricing)
    {
        return Inertia::render('Settings/Pricing/Edit', [
            'item' => $pricing->load('priceHistory.changedBy'),
        ]);
    }

    public function update(Request $request, InstructionItem $pricing)
    {
        $validated = $request->validate([
            'price' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $pricing->update(['price' => $validated['price']]);
        
        if (!empty($validated['reason'])) {
            $pricing->priceHistory()->latest()->first()?->update([
                'reason' => $validated['reason']
            ]);
        }

        return redirect()->route('settings.pricing.index')
            ->with('success', 'Pricing updated successfully.');
    }
}
```

### 9.2 Settings/BillingController
```bash
php artisan make:controller Settings/BillingController
```

**File:** `app/Http/Controllers/Settings/BillingController.php`
```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\VoucherGenerationCharge;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $monthlyStats = VoucherGenerationCharge::query()
            ->where('user_id', $user->id)
            ->whereBetween('generated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('
                SUM(total_charge) as total_charges,
                COUNT(*) as generation_count,
                SUM(voucher_count) as total_vouchers
            ')
            ->first();
        
        $recentCharges = VoucherGenerationCharge::query()
            ->where('user_id', $user->id)
            ->with('campaign:id,name')
            ->latest('generated_at')
            ->paginate(20);

        return Inertia::render('Settings/Billing', [
            'monthlyStats' => $monthlyStats,
            'recentCharges' => $recentCharges,
        ]);
    }
}
```

---

## Step 10: Update Routes

**File:** `routes/web.php`

Add these routes:
```php
use App\Actions\CalculateChargeAction;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\PricingController;

Route::middleware(['auth', 'workos'])->group(function () {
    
    // API: Calculate charges (for real-time preview)
    Route::post('/api/calculate-charge', CalculateChargeAction::class)
        ->name('calculate-charge');
    
    // User Billing Dashboard
    Route::get('/settings/billing', [BillingController::class, 'index'])
        ->name('settings.billing');
    
    // Admin: Pricing Management
    Route::middleware('permission:manage pricing')->group(function () {
        Route::resource('/settings/pricing', PricingController::class)
            ->names('settings.pricing');
    });
});
```

---

## Step 11: Frontend Implementation

### 11.1 Create Composable: useChargeBreakdown.ts
```bash
touch resources/js/composables/useChargeBreakdown.ts
```

**File:** `resources/js/composables/useChargeBreakdown.ts`
```typescript
import { ref, computed, watch } from 'vue'
import { debounce } from 'lodash'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

export function useChargeBreakdown(form: any, excluded: string[] = []) {
    const chargeBreakdown = ref<any>(null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    const payload = computed(() => {
        const cleaned = JSON.parse(JSON.stringify(form))
        excluded.forEach(field => {
            delete cleaned[field]
        })
        return cleaned
    })

    axios.defaults.withCredentials = true

    async function calculateCharge() {
        loading.value = true
        error.value = null

        try {
            const response = await axios.post(
                route('calculate-charge'),
                payload.value
            )
            chargeBreakdown.value = response.data
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to calculate charges.'
        } finally {
            loading.value = false
        }
    }

    function getChargeComponent(path: string): number | undefined {
        return chargeBreakdown.value?.breakdown?.[path]
    }

    function getTotalCharge(): number | undefined {
        return chargeBreakdown.value?.total
    }

    watch(
        payload,
        debounce(() => {
            calculateCharge()
        }, 500),
        { deep: true, immediate: true }
    )

    return {
        chargeBreakdown,
        getChargeComponent,
        getTotalCharge,
        loading,
        error,
        refresh: calculateCharge,
    }
}
```

### 11.2 Update VoucherInstructionsForm.vue

Add to the script section:
```typescript
import { useChargeBreakdown } from '@/composables/useChargeBreakdown'
import { useFormatCurrency } from '@/composables/useFormatCurrency'

const excluded = ['count', 'mask', 'ttl', 'starts_at', 'expires_at']
const { chargeBreakdown, getChargeComponent, getTotalCharge, loading } = useChargeBreakdown(
    props.modelValue,
    excluded
)
const formatCurrency = useFormatCurrency()

function getChargeMessage(index: string): string {
    const charge = getChargeComponent(index)
    return charge ? (formatCurrency(Number(charge), { detailed: false }) as string) : ''
}
```

Add to template (example for one field):
```vue
<div class="space-y-2">
  <Label>Email for Feedback</Label>
  <Input v-model="modelValue.feedback.email" />
  
  <!-- Inline charge indicator -->
  <div v-if="getChargeMessage('feedback.email')" class="text-right text-xs text-gray-500">
    +{{ getChargeMessage('feedback.email') }}
  </div>
</div>
```

Add total at bottom of form:
```vue
<div class="mt-6 border-t pt-4">
  <div class="flex justify-between items-center">
    <span class="font-medium">Total Charge:</span>
    <span class="text-lg font-bold text-red-600">
      {{ loading ? 'Calculating...' : formatCurrency(getTotalCharge() ?? 0) }}
    </span>
  </div>
</div>
```

---

## Step 12: Integrate Billing into Voucher Generation

Find your voucher generation controller/action and update the store method:

```php
use App\Actions\CalculateChargeAction;
use App\Models\VoucherGenerationCharge;
use Illuminate\Support\Facades\DB;

public function store(Request $request)
{
    $user = auth()->user();
    $instructions = VoucherInstructionsData::from($request->all());
    
    // 1. Calculate charges
    $chargeBreakdown = app(CalculateChargeAction::class)->handle($user, $instructions);
    
    // 2. Generate vouchers (your existing package logic)
    // Adjust this based on how you currently generate vouchers
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
    
    // 4. Link vouchers to user
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
```

---

## Step 13: Create Admin UI Pages

### 13.1 Pricing Index Page
**File:** `resources/js/pages/Settings/Pricing/Index.vue`

(Basic structure - customize as needed)
```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue'

defineProps<{
    items: Record<string, any[]>
}>()
</script>

<template>
  <Head title="Pricing Management" />
  
  <AppSidebarLayout>
    <SettingsLayout>
      <div class="space-y-6">
        <div>
          <h2 class="text-2xl font-bold">Pricing Management</h2>
          <p class="text-muted-foreground">Manage instruction item pricing</p>
        </div>

        <div v-for="(typeItems, type) in items" :key="type" class="space-y-2">
          <h3 class="text-lg font-semibold capitalize">{{ type }}</h3>
          
          <div class="border rounded-lg divide-y">
            <div
              v-for="item in typeItems"
              :key="item.id"
              class="flex items-center justify-between p-4"
            >
              <div>
                <p class="font-medium">{{ item.name }}</p>
                <p class="text-sm text-muted-foreground">{{ item.index }}</p>
              </div>
              
              <div class="flex items-center gap-4">
                <span class="font-mono">₱{{ (item.price / 100).toFixed(2) }}</span>
                <a
                  :href="route('settings.pricing.edit', item.id)"
                  class="text-primary hover:underline"
                >
                  Edit
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppSidebarLayout>
</template>
```

### 13.2 Pricing Edit Page
**File:** `resources/js/pages/Settings/Pricing/Edit.vue`

(Basic structure - customize as needed)
```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'

const props = defineProps<{
    item: any
}>()

const form = useForm({
    price: props.item.price,
    reason: '',
})

function submit() {
    form.patch(route('settings.pricing.update', props.item.id))
}
</script>

<template>
  <Head :title="`Edit Pricing: ${item.name}`" />
  
  <AppSidebarLayout>
    <SettingsLayout>
      <form @submit.prevent="submit" class="space-y-6 max-w-2xl">
        <div>
          <h2 class="text-2xl font-bold">Edit Pricing</h2>
          <p class="text-muted-foreground">{{ item.name }}</p>
          <p class="text-sm text-muted-foreground">{{ item.index }}</p>
        </div>

        <div class="space-y-2">
          <Label>Price (centavos)</Label>
          <Input v-model.number="form.price" type="number" min="0" />
          <p class="text-sm text-muted-foreground">
            Current: ₱{{ (item.price / 100).toFixed(2) }} → 
            New: ₱{{ (form.price / 100).toFixed(2) }}
          </p>
        </div>

        <div class="space-y-2">
          <Label>Reason for Change (optional)</Label>
          <Textarea v-model="form.reason" placeholder="e.g., Market adjustment" />
        </div>

        <div class="flex gap-2">
          <Button type="submit" :disabled="form.processing">
            Update Price
          </Button>
          <Button
            type="button"
            variant="outline"
            @click="$inertia.visit(route('settings.pricing.index'))"
          >
            Cancel
          </Button>
        </div>

        <!-- Price History -->
        <div v-if="item.price_history?.length" class="mt-8">
          <h3 class="text-lg font-semibold mb-4">Price History</h3>
          <div class="border rounded-lg divide-y">
            <div
              v-for="history in item.price_history"
              :key="history.id"
              class="p-4"
            >
              <div class="flex justify-between">
                <div>
                  <p>
                    ₱{{ (history.old_price / 100).toFixed(2) }} → 
                    ₱{{ (history.new_price / 100).toFixed(2) }}
                  </p>
                  <p v-if="history.reason" class="text-sm text-muted-foreground">
                    {{ history.reason }}
                  </p>
                </div>
                <div class="text-right text-sm text-muted-foreground">
                  <p>{{ history.effective_at }}</p>
                  <p v-if="history.changed_by">by {{ history.changed_by.name }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </SettingsLayout>
  </AppSidebarLayout>
</template>
```

### 13.3 User Billing Page
**File:** `resources/js/pages/Settings/Billing.vue`

(Basic structure - customize as needed)
```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import SettingsLayout from '@/layouts/settings/SettingsLayout.vue'

defineProps<{
    monthlyStats: any
    recentCharges: any
}>()
</script>

<template>
  <Head title="Billing" />
  
  <AppSidebarLayout>
    <SettingsLayout>
      <div class="space-y-6">
        <div>
          <h2 class="text-2xl font-bold">Billing & Charges</h2>
          <p class="text-muted-foreground">View your voucher generation charges</p>
        </div>

        <!-- Monthly Stats -->
        <div class="grid grid-cols-3 gap-4">
          <div class="border rounded-lg p-4">
            <p class="text-sm text-muted-foreground">This Month</p>
            <p class="text-2xl font-bold">
              ₱{{ (monthlyStats?.total_charges ?? 0).toFixed(2) }}
            </p>
          </div>
          <div class="border rounded-lg p-4">
            <p class="text-sm text-muted-foreground">Generations</p>
            <p class="text-2xl font-bold">{{ monthlyStats?.generation_count ?? 0 }}</p>
          </div>
          <div class="border rounded-lg p-4">
            <p class="text-sm text-muted-foreground">Total Vouchers</p>
            <p class="text-2xl font-bold">{{ monthlyStats?.total_vouchers ?? 0 }}</p>
          </div>
        </div>

        <!-- Recent Charges -->
        <div>
          <h3 class="text-lg font-semibold mb-4">Recent Charges</h3>
          <div class="border rounded-lg divide-y">
            <div
              v-for="charge in recentCharges.data"
              :key="charge.id"
              class="p-4 flex justify-between items-center"
            >
              <div>
                <p class="font-medium">{{ charge.voucher_count }} vouchers</p>
                <p class="text-sm text-muted-foreground">
                  {{ new Date(charge.generated_at).toLocaleString() }}
                </p>
                <p v-if="charge.campaign" class="text-sm text-muted-foreground">
                  Campaign: {{ charge.campaign.name }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-mono font-bold">₱{{ charge.total_charge }}</p>
                <p class="text-sm text-muted-foreground">
                  ₱{{ charge.charge_per_voucher }}/voucher
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </SettingsLayout>
  </AppSidebarLayout>
</template>
```

---

## Step 14: Testing

### 14.1 Manual Testing Checklist
- [ ] Seed instruction items
- [ ] Create a super-admin user and assign role
- [ ] Generate vouchers and verify charges are recorded
- [ ] Verify real-time charge preview in form
- [ ] Edit pricing as admin and verify history is logged
- [ ] View billing dashboard as regular user
- [ ] Verify permissions (non-admin cannot access pricing)

### 14.2 Test Commands
```bash
# Create admin user (run in tinker)
php artisan tinker

$user = \App\Models\User::find(1);
$user->assignRole('super-admin');

# Verify instruction items
\App\Models\InstructionItem::count();

# Verify permissions
\Spatie\Permission\Models\Permission::all();
```

---

## Phase 2: Enhanced Features (Future)

### 2.1 Export Functionality
- PDF invoices
- CSV export of charges

### 2.2 Advanced Reporting
- Monthly/yearly charts
- Campaign-based revenue analysis

### 2.3 Volume Discounts
- Pricing tiers table
- Bulk discount logic

### 2.4 Expense Tracking
- Track actual costs (SMS, email, server)
- Gross profit calculation

---

## Rollback Plan

If issues arise, rollback steps:

```bash
# Rollback migrations
php artisan migrate:rollback --step=4

# Remove seeded data
php artisan db:seed --class=InstructionItemSeeder --force
# (manually delete if needed)

# Revert code changes
git revert <commit-hash>
```

---

## Completion Checklist

Phase 1 is complete when:
- [ ] All migrations run successfully
- [ ] Instruction items seeded from config
- [ ] Roles & permissions created
- [ ] Real-time charge preview works in frontend
- [ ] Charges recorded on voucher generation
- [ ] Admin can edit pricing via UI
- [ ] Price history tracked automatically
- [ ] User billing dashboard functional
- [ ] Non-admins cannot access pricing management

---

## Support & Documentation

- Architecture document: `docs/ARCHITECTURE-PRICING.md`
- Questions/issues: Create GitHub issue or contact team
