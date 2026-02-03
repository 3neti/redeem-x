# Revenue Collection Architecture

## Overview

The revenue collection system manages fees accumulated in **InstructionItem wallets** from voucher generation charges. It provides **flexible per-item destination routing** with manual control and audit trails.

### Key Features

- ✅ **Flexible Destinations**: Each InstructionItem can specify its own revenue destination (polymorphic)
- ✅ **Default Fallback**: Global default revenue user with system user fallback
- ✅ **Manual Control**: Admin-initiated collections with preview and confirmation
- ✅ **Audit Trail**: Complete history via `revenue_collections` table
- ✅ **Destination Override**: Command-line override for specific collections
- ✅ **Statistics**: Dashboard showing pending and all-time revenue

## Architecture

### Revenue Flow

```
User generates voucher
    ↓
ChargeInstructions pipeline: $user->pay($instructionItem)
    ↓
User wallet → InstructionItem wallet (fees accumulate)
    ↓
Admin runs: php artisan revenue:collect
    ↓
InstructionItem wallet → Destination wallet
    (configured per-item OR default revenue user OR system user)
    ↓
RevenueCollection record created (audit trail)
```

### Database Schema

#### instruction_items table (additions)
```sql
revenue_destination_type VARCHAR   -- Polymorphic: App\Models\User, etc.
revenue_destination_id   BIGINT    -- ID of destination model
```

#### revenue_collections table
```sql
id                       BIGINT PRIMARY KEY
instruction_item_id      BIGINT    -- Source of revenue
collected_by_user_id     BIGINT    -- Admin who collected
destination_type         VARCHAR   -- Polymorphic destination
destination_id           BIGINT    -- Destination ID
amount                   BIGINT    -- Amount in centavos
transfer_uuid            UUID      -- bavix/wallet transfer reference
notes                    TEXT      -- Optional notes
created_at, updated_at   TIMESTAMP
```

## Configuration

### Environment Variables

```bash
# config/account.php
SYSTEM_USER_ID=system@disburse.cash      # Required
REVENUE_USER_ID=revenue@example.com      # Optional (null = use system user)
```

### Per-Item Configuration

Set revenue destination for specific InstructionItem:

```php
$instructionItem = InstructionItem::find(1);

// Set to a specific user
$partner = User::where('email', 'partner@example.com')->first();
$instructionItem->revenue_destination_type = User::class;
$instructionItem->revenue_destination_id = $partner->id;
$instructionItem->save();

// Or via relationship
$instructionItem->revenueDestination()->associate($partner);
$instructionItem->save();

// Clear to use default
$instructionItem->revenueDestination()->dissociate();
$instructionItem->save();
```

### Destination Resolution Priority

1. **InstructionItem's configured `revenueDestination`** (if set)
2. **Default revenue user** from `config('account.revenue_user.identifier')`
3. **System user** as final fallback

## Usage

### Command: `revenue:collect`

#### Preview Pending Revenue
```bash
php artisan revenue:collect --preview
```

Output shows:
- InstructionItem ID, Name, Index
- Current balance
- Resolved destination
- Type (Configured vs Default)

#### Show Statistics
```bash
php artisan revenue:collect --stats
```

Shows:
- Pending revenue (by item)
- All-time collected
- Last collection details

#### Collect All Revenue
```bash
php artisan revenue:collect
```

Interactive:
1. Shows pending items with destinations
2. Confirms before execution
3. Reports total collected

#### Collect from Specific Item
```bash
php artisan revenue:collect --item=2
```

Collects only from InstructionItem #2.

#### Minimum Balance Filter
```bash
php artisan revenue:collect --min=100
```

Only collects from items with ≥ ₱100 balance.

#### Override Destination
```bash
php artisan revenue:collect --destination=partner@example.com
```

Sends ALL collections to specified user (ignores configured destinations).

### Programmatic Usage

#### Service: `RevenueCollectionService`

```php
use App\Services\RevenueCollectionService;

$service = app(RevenueCollectionService::class);

// Get pending revenue
$pending = $service->getPendingRevenue();
// Returns: [
//     ['id' => 1, 'name' => 'Email', 'balance' => 10.0, 'destination' => [...]]
// ]

// Collect from one item
$item = InstructionItem::find(1);
$collection = $service->collect($item);
// Returns: RevenueCollection model

// Collect from all
$collections = $service->collectAll();
// Returns: Collection of RevenueCollection models

// Get total pending
$total = $service->getTotalPendingRevenue();
// Returns: float (PHP)

// Get statistics
$stats = $service->getStatistics();
// Returns: ['pending' => [...], 'all_time' => [...], 'last_collection' => [...]]
```

## Use Cases

### Use Case 1: Default Revenue Collection

**Scenario**: All fees go to company revenue wallet

**Setup**:
```bash
# .env
REVENUE_USER_ID=revenue@company.com
```

**Result**: All InstructionItems without configured destinations → revenue@company.com

### Use Case 2: Partner Revenue Split

**Scenario**: SMS fees go to SMS provider, others to company

**Setup**:
```php
$smsProvider = User::where('email', 'sms@provider.com')->first();
$mobileItem = InstructionItem::where('index', 'input.mobile')->first();
$mobileItem->revenueDestination()->associate($smsProvider);
$mobileItem->save();
```

**Result**:
- Mobile field fees → sms@provider.com
- Other fees → default revenue user

### Use Case 3: Department-Specific Revenue

**Scenario**: Different departments get fees from different fields

**Setup**:
```php
$marketing = User::where('email', 'marketing@company.com')->first();
$tech = User::where('email', 'tech@company.com')->first();

InstructionItem::where('index', 'input.email')->first()
    ->revenueDestination()->associate($marketing)->save();
    
InstructionItem::where('index', 'validation.location')->first()
    ->revenueDestination()->associate($tech)->save();
```

**Collect**:
```bash
php artisan revenue:collect --preview
# Shows each item going to correct department

php artisan revenue:collect
# Executes transfers
```

### Use Case 4: One-Time Override

**Scenario**: Temporarily redirect all revenue for special accounting

**Command**:
```bash
php artisan revenue:collect --destination=accounting@company.com
```

**Result**: ALL items collected to accounting@ (ignores configured destinations)

### Use Case 5: Threshold-Based Collection

**Scenario**: Only collect when balance >= ₱500 to reduce transaction count

**Schedule** (routes/console.php):
```php
Schedule::command('revenue:collect --min=500')
    ->weekly()
    ->sundays()
    ->at('23:00');
```

## Models & Relationships

### InstructionItem

```php
// Polymorphic destination
$item->revenueDestination; // User, Organization, etc.

// Revenue collections from this item
$item->revenueCollections; // Collection of RevenueCollection

// Check balance
$item->balanceFloat; // e.g., 125.50
```

### RevenueCollection

```php
// Source
$collection->instructionItem; // InstructionItem

// Destination
$collection->destination; // User, Organization, etc.
$collection->destination_name; // Auto-formatted name

// Admin who collected
$collection->collectedBy; // User

// Transfer details
$collection->transfer; // bavix/wallet Transfer model
$collection->amount; // Centavos (12550)
$collection->amount_float; // PHP (125.50)
$collection->formatted_amount; // "₱125.50"
```

### User (additions)

```php
// Revenue collections where this user was destination
$user->revenueCollections() // morphMany

// InstructionItems routing to this user
$user->revenueInstructionItems() // morphMany
```

## Admin Dashboard Integration

### Revenue Widget Component

```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

interface Props {
  pending_revenue: {
    id: number
    name: string
    formatted_balance: string
    destination: { name: string }
  }[]
  total_pending: string
  last_collection: {
    amount: string
    collected_at: string
  } | null
}

const props = defineProps<Props>()

const collectRevenue = () => {
  router.post('/admin/revenue/collect', {}, {
    onSuccess: () => router.reload()
  })
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>Revenue Collection</CardTitle>
      <CardDescription>Fees from InstructionItem usage</CardDescription>
    </CardHeader>
    <CardContent>
      <div class="space-y-4">
        <div class="text-3xl font-bold">{{ total_pending }}</div>
        <div class="text-sm text-muted-foreground">Pending collection</div>
        
        <div v-if="pending_revenue.length" class="space-y-2">
          <div v-for="item in pending_revenue" :key="item.id" 
               class="flex justify-between text-sm border-b pb-2">
            <div>
              <div class="font-medium">{{ item.name }}</div>
              <div class="text-xs text-muted-foreground">
                → {{ item.destination.name }}
              </div>
            </div>
            <span class="font-mono">{{ item.formatted_balance }}</span>
          </div>
        </div>
        
        <Button @click="collectRevenue" class="w-full" :disabled="!pending_revenue.length">
          Collect Revenue
        </Button>
        
        <div v-if="last_collection" class="text-xs text-muted-foreground pt-2">
          Last: {{ last_collection.amount }} on {{ last_collection.collected_at }}
        </div>
      </div>
    </CardContent>
  </Card>
</template>
```

### Controller

```php
use App\Services\RevenueCollectionService;

class DashboardController extends Controller
{
    public function index(RevenueCollectionService $revenueService)
    {
        $stats = $revenueService->getStatistics();
        
        return Inertia::render('Dashboard', [
            'revenue' => [
                'pending_revenue' => $stats['pending']['by_item'],
                'total_pending' => $stats['pending']['formatted_total'],
                'last_collection' => $stats['last_collection'],
            ],
        ]);
    }
}
```

## Best Practices

### 1. Regular Collections

Schedule weekly collections to prevent large accumulations:

```php
// routes/console.php
Schedule::command('revenue:collect --min=50')
    ->weekly()
    ->sundays()
    ->at('00:00')
    ->timezone('Asia/Manila');
```

### 2. Preview Before Collecting

Always use `--preview` first:
```bash
php artisan revenue:collect --preview
php artisan revenue:collect  # After verifying
```

### 3. Configure Important Items

Set destinations for high-value or partner-owned items:
```php
// In seeder or admin panel
$smsItem = InstructionItem::where('index', 'feedback.mobile')->first();
$smsItem->revenueDestination()->associate($smsProvider);
$smsItem->save();
```

### 4. Monitor Revenue

Check statistics regularly:
```bash
php artisan revenue:collect --stats
```

### 5. Document Collections

Use notes field for audit trail:
```php
$service->collect($item, notes: 'Q4 2025 collection - Partner agreement #123');
```

## Troubleshooting

### No Revenue to Collect

**Symptom**: Command shows "No revenue to collect"

**Check**:
```bash
# Check InstructionItem wallets directly
php artisan tinker
InstructionItem::all()->map(fn($i) => [$i->name, $i->balanceFloat])
```

**Cause**: Users may not have generated vouchers, or prices are set to ₱0

### Collection Fails

**Symptom**: Error during `revenue:collect`

**Check logs**:
```bash
tail -f storage/logs/laravel.log | grep RevenueCollection
```

**Common issues**:
- Destination user not found
- InstructionItem has no wallet
- Insufficient balance (race condition)

### Wrong Destination

**Symptom**: Revenue going to wrong wallet

**Check configuration**:
```bash
php artisan tinker
$item = InstructionItem::find(1);
$item->revenueDestination;  // null = uses default
config('account.revenue_user.identifier');  // Default user
```

**Fix**:
```php
// Set correct destination
$correctUser = User::find(5);
$item->revenueDestination()->associate($correctUser);
$item->save();
```

## API Reference

### RevenueCollectionService

```php
class RevenueCollectionService
{
    // Get pending revenue with destinations
    public function getPendingRevenue(?float $minAmount = null): Collection
    
    // Collect from one item
    public function collect(
        InstructionItem $item,
        ?Wallet $destinationOverride = null,
        ?string $notes = null
    ): RevenueCollection
    
    // Collect from all items
    public function collectAll(
        ?float $minAmount = null,
        ?Wallet $destinationOverride = null
    ): Collection
    
    // Get total pending
    public function getTotalPendingRevenue(): float
    
    // Get statistics
    public function getStatistics(): array
}
```

### Command Options

```bash
revenue:collect
  --preview              # Preview without executing
  --item=ID              # Collect from specific InstructionItem
  --min=AMOUNT           # Minimum balance (PHP)
  --destination=EMAIL    # Override destination for all
  --stats                # Show statistics
```

## Migration Guide

### From No Revenue System

If you're adding this to an existing system:

1. **Run migrations**:
   ```bash
   php artisan migrate
   ```

2. **Check existing balances**:
   ```bash
   php artisan revenue:collect --preview
   ```

3. **Collect accumulated fees**:
   ```bash
   php artisan revenue:collect
   ```

4. **Configure destinations** (if needed):
   ```php
   // In tinker or seeder
   $item = InstructionItem::find(2);
   $item->revenueDestination()->associate($partner);
   $item->save();
   ```

### Database Impact

- Adds 2 columns to `instruction_items` (nullable)
- Adds new `revenue_collections` table
- No changes to existing revenue data

## See Also

- [System Wallet Architecture](SYSTEM_WALLET_ARCHITECTURE.md)
- [Balance Reconciliation](BALANCE_RECONCILIATION_PLAN.md)
- [Pricing Architecture](ARCHITECTURE-PRICING.md)
