# Phase 2: Wallet Dashboard Implementation Plan

## Overview
Create a dedicated Wallet dashboard page at `/wallet` to consolidate wallet functionality (balance, transactions, top-up, QR load) following the noun-based navigation pattern.

---

## Current State
- **Route**: `/wallet/load` - QR generation and merchant settings page
- **Navigation**: "Wallet" → points to `/wallet/load`
- **Related pages**:
  - `/topup` - Top-up via NetBank Direct Checkout
  - `/billing` - User's voucher generation charges
  - `/transactions` - All wallet transactions

**Problem**: "Load" is an action (verb), not a dashboard. Users expect "Wallet" to show overview + actions.

---

## Proposed Structure

```
/wallet (NEW - Dashboard/Index)
├── Overview Section
│   ├── Balance card (large, prominent)
│   ├── Recent transactions (last 5)
│   └── Quick stats (total loaded, total spent, etc.)
│
├── Action Cards (CTAs)
│   ├── "Top Up" → /topup
│   ├── "Generate QR" → /wallet/qr
│   └── "View All Transactions" → /transactions
│
└── Tabs/Sections (optional)
    ├── Transactions
    ├── QR Load Settings
    └── Billing History

/wallet/qr (RENAMED from /wallet/load)
└── QR generation + merchant settings (existing Load.vue)
```

---

## Implementation Steps

### **Step 1: Create Wallet Dashboard Controller**
**File**: `app/Http/Controllers/Wallet/WalletController.php`

```php
<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    /**
     * Display the wallet dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        // Get recent transactions (last 5)
        $recentTransactions = $user->walletTransactions()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'confirmed' => $tx->confirmed,
                    'created_at' => $tx->created_at,
                    'meta' => $tx->meta,
                ];
            });
        
        // Get quick stats
        $stats = [
            'total_loaded' => $user->getPaidTopUps()->sum('amount'),
            'total_spent' => abs($user->walletTransactions()->where('amount', '<', 0)->sum('amount')),
            'transaction_count' => $user->walletTransactions()->count(),
        ];
        
        return Inertia::render('wallet/Index', [
            'balance' => $user->balanceFloatNum,
            'recentTransactions' => $recentTransactions,
            'stats' => $stats,
        ]);
    }
}
```

---

### **Step 2: Create Wallet Dashboard Page**
**File**: `resources/js/pages/wallet/Index.vue`

**Features**:
- **Balance Card**: Large, prominent display with formatted currency
- **Quick Actions**: 3 CTA buttons (Top Up, Generate QR, View Transactions)
- **Recent Transactions**: Table/list of last 5 transactions
- **Stats Cards**: Total loaded, total spent, transaction count
- **Design**: Use shadcn cards, responsive grid layout

**Wireframe**:
```
┌─────────────────────────────────────────┐
│ [Balance Card - Large]                  │
│ ₱12,345.67                              │
│ [Top Up] [Generate QR] [Transactions]   │
└─────────────────────────────────────────┘
┌───────────────┬───────────────┬─────────┐
│ Total Loaded  │ Total Spent   │ Txns    │
│ ₱50,000       │ ₱37,654.33    │ 42      │
└───────────────┴───────────────┴─────────┘
┌─────────────────────────────────────────┐
│ Recent Transactions                     │
│ ┌─────────────────────────────────────┐ │
│ │ Top Up      +₱500.00    2 hrs ago   │ │
│ │ Voucher Gen -₱200.00    5 hrs ago   │ │
│ │ ...                                 │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

---

### **Step 3: Rename `/wallet/load` to `/wallet/qr`**

**3.1 Update LoadController**
```php
// Rename: LoadController → QrController
// File: app/Http/Controllers/Wallet/QrController.php

class QrController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('wallet/Qr', [
            'loadWalletConfig' => config('load-wallet'),
        ]);
    }
}
```

**3.2 Rename Vue Component**
```bash
# Rename file
mv resources/js/pages/wallet/Load.vue resources/js/pages/wallet/Qr.vue
```

**3.3 Update breadcrumbs in Qr.vue**
```typescript
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallet', href: '/wallet' },      // NEW: Link to dashboard
    { title: 'QR Load', href: '/wallet/qr' },  // NEW: Current page
];
```

---

### **Step 4: Update Routes**
**File**: `routes/web.php`

```php
// Wallet routes
Route::prefix('wallet')->name('wallet.')->group(function () {
    // Dashboard (NEW)
    Route::get('/', [App\Http\Controllers\Wallet\WalletController::class, 'index'])
        ->name('index');
    
    // QR Load (RENAMED from 'load')
    Route::get('qr', App\Http\Controllers\Wallet\QrController::class)
        ->name('qr');
    
    // Keep existing routes
    Route::get('balance', App\Http\Controllers\Wallet\CheckBalanceController::class)
        ->name('balance');
    Route::get('add-funds', LBHurtado\PaymentGateway\Http\Controllers\GenerateController::class)
        ->name('add-funds');
});
```

---

### **Step 5: Update Navigation**
**File**: `resources/js/components/AppSidebar.vue`

```typescript
// Change Wallet href to new dashboard
{
    title: 'Wallet',
    href: '/wallet',  // Changed from '/wallet/load'
    icon: Wallet,
}
```

---

### **Step 6: Create Wayfinder Actions**
```bash
# Regenerate Wayfinder routes
npm run wayfinder:generate
```

**Expected**:
- `resources/js/actions/App/Http/Controllers/Wallet/WalletController.ts` (NEW)
- `resources/js/actions/App/Http/Controllers/Wallet/QrController.ts` (RENAMED from LoadController)

---

## Files to Create/Modify

### New Files
- `app/Http/Controllers/Wallet/WalletController.php`
- `resources/js/pages/wallet/Index.vue`

### Renamed Files
- `app/Http/Controllers/Wallet/LoadController.php` → `QrController.php`
- `resources/js/pages/wallet/Load.vue` → `Qr.vue`

### Modified Files
- `routes/web.php` - Update wallet routes
- `resources/js/components/AppSidebar.vue` - Update wallet href
- `resources/js/pages/wallet/Qr.vue` - Update breadcrumbs

---

## Design Specifications

### Balance Card
- **Size**: Full width, prominent
- **Content**: 
  - Balance in large font (48px+)
  - Last updated timestamp
  - Real-time updates via composable

### Action Buttons
- **Layout**: 3-column grid on desktop, stacked on mobile
- **Icons**: 
  - Top Up: `CreditCard` or `ArrowUp`
  - Generate QR: `QrCode`
  - Transactions: `Receipt`
- **Style**: Outlined buttons with hover effects

### Stats Cards
- **Metrics**:
  - Total Loaded (sum of successful top-ups)
  - Total Spent (sum of negative transactions)
  - Transaction Count
- **Layout**: 3-column grid, compact cards

### Recent Transactions
- **Display**: Last 5 transactions
- **Columns**: Type, Amount, Time
- **Link**: "View all" → `/transactions`

---

## Testing Checklist

- [ ] `/wallet` shows dashboard with balance
- [ ] Quick action buttons navigate correctly
- [ ] Recent transactions display correctly
- [ ] Stats calculations are accurate
- [ ] `/wallet/qr` still works (renamed from `/wallet/load`)
- [ ] Sidebar "Wallet" link points to `/wallet`
- [ ] Breadcrumbs on QR page show "Wallet → QR Load"
- [ ] Real-time balance updates work
- [ ] Responsive layout works on mobile

---

## Migration Notes

**Breaking Changes**:
- `/wallet/load` → `/wallet/qr` (301 redirect recommended)
- Sidebar navigation href changes

**Backward Compatibility**:
- Add redirect in routes:
```php
Route::redirect('/wallet/load', '/wallet/qr', 301);
```

---

## Benefits

1. **Better UX**: Users see overview before choosing action
2. **Consistent Pattern**: Follows noun-based navigation (Vouchers, Wallet, Transactions)
3. **Action Hierarchy**: Dashboard → Actions (Top Up button in page, not nav)
4. **Scalability**: Easy to add new wallet features (payment methods, history filters)
