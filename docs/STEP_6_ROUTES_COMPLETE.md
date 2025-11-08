# ✅ Step 6 Complete: Routes

**Date**: 2025-11-08  
**Status**: Complete

---

## 📦 Routes Created

### 1. **vouchers.php** (25 lines)
**Path**: `routes/vouchers.php`

**Routes**:
```
GET    /vouchers              vouchers.index    List user's vouchers
GET    /vouchers/create       vouchers.create   Show generation form
POST   /vouchers              vouchers.store    Generate vouchers
GET    /vouchers/{voucher}    vouchers.show     View voucher details
```

**Middleware**:
- `auth` - WorkOS authentication required
- `verified` - Email verification required

**Features**:
- ✅ RESTful resource routes
- ✅ Route model binding by `id`
- ✅ Named routes for Wayfinder
- ✅ Clear comments explaining each route

---

### 2. **redeem.php** (49 lines)
**Path**: `routes/redeem.php`

**Routes**:
```
GET    /redeem                           redeem.start          Start redemption
GET    /redeem/{voucher}/wallet          redeem.wallet         Show wallet form
POST   /redeem/{voucher}/wallet          redeem.wallet.store   Save wallet info
GET    /redeem/{voucher}/{plugin}        redeem.plugin         Show plugin form (dynamic!)
POST   /redeem/{voucher}/{plugin}        redeem.plugin.store   Save plugin data
GET    /redeem/{voucher}/finalize        redeem.finalize       Review collected data
POST   /redeem/{voucher}/confirm         redeem.confirm        Execute redemption
GET    /redeem/{voucher}/success         redeem.success        Success page
```

**Middleware**:
- None (public routes - no authentication required)

**Features**:
- ✅ Route model binding by `code` (`{voucher:code}`)
- ✅ Dynamic plugin routes (`{plugin}` parameter)
- ✅ Grouped with prefix and name
- ✅ Clear flow documentation in comments
- ✅ Public access for voucher redemption

---

## 🔌 Registration

Routes registered in `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware('web')->group(base_path('routes/vouchers.php'));
        Route::middleware('web')->group(base_path('routes/redeem.php'));
        Route::middleware('web')->group(base_path('routes/auth.php'));
        Route::middleware('web')->group(base_path('routes/settings.php'));
    },
)
```

---

## 🎯 Route Model Binding

### **Vouchers**
```php
Route::get('/vouchers/{voucher}', ...)
```
- Binds by `id` (default)
- Returns `404` if not found
- Loads `LBHurtado\Voucher\Models\Voucher`

### **Redeem**
```php
Route::prefix('{voucher:code}')->group(...)
```
- Binds by `code` (custom key)
- Case-insensitive (voucher model handles normalization)
- Returns `404` if not found

---

## 🔀 Redemption Flow (Route-Level)

```
1. GET  /redeem
   └─> User enters voucher code

2. GET  /redeem/{CODE}/wallet
   └─> User enters mobile + bank account
   └─> POST /redeem/{CODE}/wallet

3. GET  /redeem/{CODE}/inputs (or other plugin)
   └─> User enters NAME, EMAIL, etc.
   └─> POST /redeem/{CODE}/inputs

4. GET  /redeem/{CODE}/signature (if required)
   └─> User provides signature
   └─> POST /redeem/{CODE}/signature

5. GET  /redeem/{CODE}/finalize
   └─> User reviews all data
   └─> POST /redeem/{CODE}/confirm

6. GET  /redeem/{CODE}/success
   └─> User sees success message + rider
```

**Note**: Steps 3-4 are **dynamic** based on voucher instructions!

---

## 🏷️ Named Routes (Wayfinder)

All routes are named for easy frontend access:

### **Vouchers**
```typescript
import { index, create, store, show } from '@/actions/.../VoucherController'

// Navigate
router.visit(index.url())
router.visit(create.url())

// Submit form
router.post(store.url(), data)

// View voucher
router.visit(show.url({ voucher: 123 }))
```

### **Redeem**
```typescript
import { start, wallet, plugin, confirm } from '@/actions/.../RedeemController'

// Start
router.visit(start.url())

// Wallet
router.visit(wallet.url({ voucher: 'ABC123' }))
router.post(wallet.url({ voucher: 'ABC123' }), data)

// Plugin (dynamic)
router.visit(plugin.url({ voucher: 'ABC123', plugin: 'inputs' }))
router.post(plugin.url({ voucher: 'ABC123', plugin: 'inputs' }), data)

// Confirm
router.post(confirm.url({ voucher: 'ABC123' }))
```

---

## 🔒 Security

### **Authentication**
- **Vouchers**: Requires `auth` + `verified` middleware
- **Redeem**: Public (no auth) - anyone with code can redeem

### **Authorization**
- **VoucherController**: Uses `VoucherPolicy`
  - Users can only view/edit their own vouchers
  - Enforced via `$this->authorize('view', $voucher)`

### **Validation**
- **All POST routes**: Use FormRequests
  - `VoucherInstructionDataRequest`
  - `WalletFormRequest`
  - `PluginFormRequest`

---

## 📊 Route Statistics

| File | Routes | GET | POST | Auth Required |
|------|--------|-----|------|---------------|
| vouchers.php | 4 | 3 | 1 | Yes |
| redeem.php | 8 | 4 | 4 | No |
| **Total** | **12** | **7** | **5** | Mixed |

---

## 🎨 Route Naming Convention

| Pattern | Example | Purpose |
|---------|---------|---------|
| `resource.action` | `vouchers.index` | RESTful actions |
| `resource.nested.action` | `redeem.wallet.store` | Nested resources |
| `resource.verb` | `redeem.start` | Custom actions |

---

## ✨ Special Features

### **1. Dynamic Plugin Routes**
```php
GET  /redeem/{voucher}/{plugin}
POST /redeem/{voucher}/{plugin}
```
The `{plugin}` parameter is **dynamic**:
- `inputs` - Name, Email, Address, etc.
- `signature` - Digital signature
- `selfie` - KYC photo (disabled by default)

The controller determines which plugins are needed based on voucher instructions!

### **2. Route Model Binding by Code**
```php
{voucher:code}
```
Uses Laravel's custom key binding:
- Automatically uppercases and trims
- More user-friendly than numeric IDs
- Handled by `Voucher::resolveRouteBinding()`

### **3. Grouped Routes**
```php
Route::prefix('redeem')->name('redeem.')->group(...)
```
All redemption routes:
- Share `/redeem` prefix
- Share `redeem.` name prefix
- Easy to apply middleware to entire group

---

## 🚀 Next Steps

With routes complete, Phase 2 Backend is **essentially done**!

Remaining:
1. **Step 7**: Integration Tests (test complete flows end-to-end)
2. **Phase 3**: Frontend Development (Vue components)

---

## ✅ Verification

Routes verified with `php artisan route:list`:

**Vouchers** (6 routes total including package routes):
```
✓ vouchers.index
✓ vouchers.create
✓ vouchers.store
✓ vouchers.show
```

**Redeem** (8 routes):
```
✓ redeem.start
✓ redeem.wallet
✓ redeem.wallet.store
✓ redeem.plugin
✓ redeem.plugin.store
✓ redeem.finalize
✓ redeem.confirm
✓ redeem.success
```

All routes registered and accessible! 🎉
