# Transaction History Enhancement Plan

**Goal:** Display detailed bank transaction information in the Transaction History UI

**Date:** November 14, 2025  
**Voucher Example:** 7QHX (₱50 to GCash via INSTAPAY, Operation ID: 260683631)

---

## Current State

### Data Available
**Voucher metadata (`metadata.disbursement`):**
```json
{
  "operation_id": "260683631",
  "transaction_uuid": "019a8084-42fa-707c-9aa5-864bfa63a83a",
  "status": "Pending",
  "amount": 50,
  "bank": "GXCHPHM2XXX",
  "rail": "INSTAPAY",
  "account": "09173011987",
  "disbursed_at": "2025-11-14T11:59:04+08:00"
}
```

**Transaction meta (in `bavix_wallet_transactions`):**
```json
{
  "operationId": "260683631",
  "user_id": 2,
  "payload": {...},
  "settlement_rail": "INSTAPAY",
  "bank_code": "GXCHPHM2XXX",
  "is_emi": true
}
```

### Current UI
**Location:** `resources/js/pages/Transactions/Index.vue`

**Shows:**
- Voucher Code
- Amount
- Redeemed At
- Created At

**Missing:**
- Bank details (GCash, PayMaya, etc.)
- Settlement rail (INSTAPAY/PESONET)
- Operation ID (for tracking)
- Account number (masked)
- Transaction status
- Disbursement timestamp

---

## Enhancement Plan

### Phase 1: Backend - Expose Disbursement Data

#### 1.1 Update VoucherData DTO
**File:** `packages/voucher/src/Data/VoucherData.php` (or create if doesn't exist)

**Add fields:**
```php
public ?DisbursementData $disbursement;
```

**Create DisbursementData DTO:**
```php
// packages/voucher/src/Data/DisbursementData.php
class DisbursementData extends Data
{
    public function __construct(
        public string $operation_id,
        public string $transaction_uuid,
        public string $status,
        public float $amount,
        public string $bank,
        public string $rail,
        public string $account,
        public string $disbursed_at,
        public ?string $bank_name = null,  // Human-readable bank name
        public bool $is_emi = false,       // Is EMI (GCash, PayMaya)
    ) {}
    
    public static function fromVoucher(Voucher $voucher): ?self
    {
        $disbursement = $voucher->metadata['disbursement'] ?? null;
        if (!$disbursement) {
            return null;
        }
        
        return new self(
            operation_id: $disbursement['operation_id'],
            transaction_uuid: $disbursement['transaction_uuid'],
            status: $disbursement['status'],
            amount: $disbursement['amount'],
            bank: $disbursement['bank'],
            rail: $disbursement['rail'],
            account: $disbursement['account'],
            disbursed_at: $disbursement['disbursed_at'],
            bank_name: BankRegistry::getBankName($disbursement['bank']),
            is_emi: BankRegistry::isEMI($disbursement['bank']),
        );
    }
    
    public function getMaskedAccount(): string
    {
        // Show last 4 digits: 09173011987 → ***1987
        return '***' . substr($this->account, -4);
    }
}
```

#### 1.2 Update ListTransactions Action
**File:** `app/Actions/Api/Transactions/ListTransactions.php`

**Modify query to eager load disbursement data:**
```php
$query = Voucher::query()
    ->with(['owner', 'cash']) // Add cash relation
    ->whereNotNull('redeemed_at')
    ->orderByDesc('redeemed_at');
```

**Transform response to include disbursement:**
```php
// VoucherData should automatically map disbursement from metadata
$transactionData = new DataCollection(VoucherData::class, $transactions->items());
```

#### 1.3 Update BankRegistry
**File:** `packages/payment-gateway/src/Support/BankRegistry.php`

**Add method to get human-readable bank names:**
```php
public function getBankName(string $bankCode): string
{
    $banks = [
        'GXCHPHM2XXX' => 'GCash',
        'PYMYPHM2XXX' => 'PayMaya',
        'MBTCPHM2XXX' => 'Metrobank',
        'BPIAPHM2XXX' => 'BPI',
        // ... add more banks
    ];
    
    return $banks[$bankCode] ?? $bankCode;
}

public function getBankLogo(string $bankCode): ?string
{
    $logos = [
        'GXCHPHM2XXX' => '/images/banks/gcash.svg',
        'PYMYPHM2XXX' => '/images/banks/paymaya.svg',
        // ... add more logos
    ];
    
    return $logos[$bankCode] ?? null;
}
```

---

### Phase 2: Frontend - Enhanced UI

#### 2.1 Update Transaction Table Columns
**File:** `resources/js/pages/Transactions/Index.vue`

**New columns:**
```vue
<thead>
    <tr>
        <th>Voucher Code</th>
        <th>Amount</th>
        <th>Bank / Account</th>
        <th>Rail</th>
        <th>Status</th>
        <th>Operation ID</th>
        <th>Redeemed At</th>
    </tr>
</thead>
<tbody>
    <tr v-for="transaction in transactions">
        <td>{{ transaction.code }}</td>
        <td>{{ formatAmount(transaction.amount) }}</td>
        <td>
            <div class="flex items-center gap-2">
                <img v-if="transaction.disbursement?.bank_logo" 
                     :src="transaction.disbursement.bank_logo" 
                     class="h-6 w-6" />
                <div>
                    <div class="font-medium">
                        {{ transaction.disbursement?.bank_name || 'N/A' }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ transaction.disbursement?.masked_account || 'N/A' }}
                    </div>
                </div>
            </div>
        </td>
        <td>
            <Badge :variant="getRailVariant(transaction.disbursement?.rail)">
                {{ transaction.disbursement?.rail || 'N/A' }}
            </Badge>
        </td>
        <td>
            <Badge :variant="getStatusVariant(transaction.disbursement?.status)">
                {{ transaction.disbursement?.status || 'N/A' }}
            </Badge>
        </td>
        <td class="font-mono text-xs">
            {{ transaction.disbursement?.operation_id || 'N/A' }}
        </td>
        <td>{{ formatDate(transaction.redeemed_at) }}</td>
    </tr>
</tbody>
```

#### 2.2 Add Detail Modal/Drawer
**Component:** `resources/js/components/TransactionDetailModal.vue`

**Features:**
- Click row to open detailed view
- Show all transaction information
- Copy operation ID button
- Transaction timeline (Created → Redeemed → Disbursed)
- Bank logo and full details
- Link to track transaction status (future)

**Structure:**
```vue
<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Transaction Details</DialogTitle>
                <DialogDescription>
                    Voucher: {{ transaction.code }}
                </DialogDescription>
            </DialogHeader>
            
            <!-- Summary Section -->
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Amount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ formatAmount(transaction.amount) }}
                        </p>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardHeader>
                        <CardTitle>Status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Badge>{{ transaction.disbursement.status }}</Badge>
                    </CardContent>
                </Card>
            </div>
            
            <!-- Disbursement Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Bank Transfer Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Bank:</span>
                        <span class="font-medium">{{ disbursement.bank_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Account:</span>
                        <span class="font-mono">{{ disbursement.account }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Rail:</span>
                        <Badge>{{ disbursement.rail }}</Badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Operation ID:</span>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm">{{ disbursement.operation_id }}</span>
                            <Button @click="copyOperationId" size="sm" variant="ghost">
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Disbursed At:</span>
                        <span>{{ formatDate(disbursement.disbursed_at) }}</span>
                    </div>
                </CardContent>
            </Card>
            
            <!-- Timeline -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction Timeline</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="relative border-l border-muted-foreground/20">
                        <li class="mb-4 ml-6">
                            <div class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary">
                                <Check class="h-3 w-3 text-primary-foreground" />
                            </div>
                            <h3 class="font-medium">Voucher Generated</h3>
                            <time class="text-sm text-muted-foreground">
                                {{ formatDate(transaction.created_at) }}
                            </time>
                        </li>
                        <li class="mb-4 ml-6">
                            <div class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary">
                                <Check class="h-3 w-3 text-primary-foreground" />
                            </div>
                            <h3 class="font-medium">Redeemed</h3>
                            <time class="text-sm text-muted-foreground">
                                {{ formatDate(transaction.redeemed_at) }}
                            </time>
                        </li>
                        <li class="ml-6">
                            <div class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary">
                                <Check class="h-3 w-3 text-primary-foreground" />
                            </div>
                            <h3 class="font-medium">Disbursed</h3>
                            <time class="text-sm text-muted-foreground">
                                {{ formatDate(disbursement.disbursed_at) }}
                            </time>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </DialogContent>
    </Dialog>
</template>
```

#### 2.3 Add Filter Options
**New filters:**
- Filter by bank (dropdown: All, GCash, PayMaya, Banks)
- Filter by rail (dropdown: All, INSTAPAY, PESONET)
- Filter by status (dropdown: All, Pending, Completed, Failed)

```vue
<div class="grid gap-4 pt-4 sm:grid-cols-5">
    <div class="relative sm:col-span-2">
        <Search />
        <Input v-model="searchQuery" placeholder="Search..." class="pl-8" />
    </div>
    <Select v-model="filterBank">
        <option value="">All Banks</option>
        <option value="GXCHPHM2XXX">GCash</option>
        <option value="PYMYPHM2XXX">PayMaya</option>
        <option value="bank">Banks</option>
    </Select>
    <Select v-model="filterRail">
        <option value="">All Rails</option>
        <option value="INSTAPAY">INSTAPAY</option>
        <option value="PESONET">PESONET</option>
    </Select>
    <Select v-model="filterStatus">
        <option value="">All Status</option>
        <option value="Pending">Pending</option>
        <option value="Completed">Completed</option>
        <option value="Failed">Failed</option>
    </Select>
</div>
```

---

### Phase 3: Enhanced Features

#### 3.1 Export Enhancement
**Update export to include disbursement columns:**
```csv
Voucher Code, Amount, Bank, Account, Rail, Status, Operation ID, Redeemed At, Disbursed At
7QHX, 50.00, GCash, ***1987, INSTAPAY, Pending, 260683631, 2025-11-14 11:59:02, 2025-11-14 11:59:04
```

#### 3.2 Statistics Enhancement
**Add new stat cards:**
- Total Disbursed Amount
- INSTAPAY Transactions
- PESONET Transactions
- Failed Transactions

#### 3.3 Real-time Status Updates (Future)
**Add polling or webhooks to update transaction status:**
- Check NetBank API for status updates
- Update voucher metadata when status changes
- Show real-time status in UI

---

## Implementation Steps

### Step 1: Backend Data Layer
1. ✅ Create `DisbursementData` DTO
2. ✅ Update `VoucherData` to include disbursement
3. ✅ Add `getBankName()` to BankRegistry
4. ✅ Update `ListTransactions` to include disbursement data

### Step 2: Basic UI Updates
1. ✅ Add new table columns (Bank, Rail, Status, Operation ID)
2. ✅ Add badge components for rail and status
3. ✅ Format bank display with logos
4. ✅ Mask account numbers

### Step 3: Detail View
1. ✅ Create `TransactionDetailModal` component
2. ✅ Add click handler to table rows
3. ✅ Implement copy operation ID functionality
4. ✅ Add transaction timeline

### Step 4: Enhanced Filtering
1. ✅ Add bank filter to API
2. ✅ Add rail filter to API
3. ✅ Add status filter to API
4. ✅ Update UI with filter dropdowns

### Step 5: Export & Stats
1. ✅ Update CSV export with new columns
2. ✅ Add disbursement-related statistics
3. ✅ Update stats API endpoint

---

## File Changes Required

### Backend
```
packages/voucher/src/Data/
├── DisbursementData.php (NEW)
└── VoucherData.php (UPDATE - add disbursement field)

packages/payment-gateway/src/Support/
└── BankRegistry.php (UPDATE - add getBankName, getBankLogo)

app/Actions/Api/Transactions/
├── ListTransactions.php (UPDATE - add filters, include disbursement)
├── GetTransactionStats.php (UPDATE - add disbursement stats)
└── ExportTransactions.php (UPDATE - add disbursement columns)
```

### Frontend
```
resources/js/pages/Transactions/
└── Index.vue (UPDATE - new columns, filters, detail modal)

resources/js/components/
├── TransactionDetailModal.vue (NEW)
└── ui/badge.vue (VERIFY EXISTS)

resources/js/composables/
└── useTransactionApi.ts (UPDATE - new filter params)

resources/js/types/
└── index.ts (UPDATE - add DisbursementData type)
```

### Assets
```
public/images/banks/
├── gcash.svg (NEW)
├── paymaya.svg (NEW)
└── ... (other bank logos)
```

---

## Testing Checklist

### Backend
- [ ] DisbursementData correctly maps from voucher metadata
- [ ] Bank names resolve correctly for all codes
- [ ] Filters work correctly (bank, rail, status)
- [ ] Export includes all disbursement columns
- [ ] Stats calculate disbursement metrics correctly

### Frontend
- [ ] Table displays all new columns correctly
- [ ] Bank logos display for GCash/PayMaya
- [ ] Account numbers are masked properly
- [ ] Badges show correct colors for rail/status
- [ ] Detail modal opens and displays all information
- [ ] Copy operation ID button works
- [ ] Timeline displays correctly
- [ ] Filters update results correctly
- [ ] Export downloads CSV with new columns
- [ ] Mobile responsive design works

---

## Future Enhancements

### Phase 4: Advanced Features
- [ ] Transaction status tracking (check NetBank API)
- [ ] Retry failed disbursements
- [ ] Bulk operations (export selected, retry selected)
- [ ] Real-time notifications on status change
- [ ] Transaction reconciliation tools
- [ ] Dispute management
- [ ] Advanced analytics dashboard

---

## Estimated Timeline

| Phase | Tasks | Duration |
|-------|-------|----------|
| Phase 1 | Backend data layer | 2-3 hours |
| Phase 2 | Basic UI updates | 2-3 hours |
| Phase 3 | Detail view | 1-2 hours |
| Phase 4 | Enhanced filtering | 1-2 hours |
| Phase 5 | Export & stats | 1-2 hours |
| **Total** | | **7-12 hours** |

---

## Success Criteria

✅ Transaction history shows bank details for all disbursements  
✅ Users can see operation ID for tracking  
✅ Settlement rail and status are visible  
✅ Account numbers are masked for privacy  
✅ Detail modal provides comprehensive information  
✅ Filters allow users to find specific transactions  
✅ Export includes all disbursement data  
✅ UI is responsive and performant  

---

**Status:** 📋 Ready for Implementation  
**Priority:** High  
**Dependencies:** None (all data already persisted)

**Next Steps:** Begin with Phase 1 - Backend Data Layer
