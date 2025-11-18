# Comprehensive Refactoring Plan

## Executive Summary

This plan reorganizes the codebase to follow a **consistent, domain-driven structure** that mirrors Laravel conventions and makes the project intuitive for new developers. The refactoring addresses:

1. **Backend**: Move standalone controllers into domain subdirectories
2. **Frontend Pages**: Already standardized to lowercase (✅ Complete)
3. **Frontend Components**: Rationalize `domain/` and `voucher/` directories
4. **Naming**: Fix singular/plural inconsistencies

---

## 🎯 Goals

1. **Predictability**: `app/Http/Controllers/{Domain}/` ↔ `resources/js/pages/{domain}/`
2. **Discoverability**: Clear domain boundaries
3. **Scalability**: Easy to add new features
4. **Convention**: Follow Laravel/Inertia best practices

---

## 📋 Phase 1: Backend Reorganization

### Current Issues

**Standalone Controllers** (inconsistent organization):
```
app/Http/Controllers/
├── BalancePageController.php          ❌ Should be in Balances/
├── CheckWalletBalanceController.php   ❌ Should be in Wallet/
├── ContactController.php              ❌ Should be in Contacts/
├── TopUpController.php                ❌ Should be in Wallet/
├── TransactionController.php          ❌ Should be in Transactions/
├── VoucherGenerationController.php    ❌ Should be in Vouchers/
```

**Naming Inconsistency**:
- Backend: `Voucher/` (singular)
- Frontend: `vouchers/` (plural)
- URLs: `/vouchers` (plural)

### Proposed Structure

```
app/Http/Controllers/
├── Admin/
│   ├── BillingController.php
│   └── PricingController.php
├── Balances/
│   └── BalanceController.php          ← MOVE BalancePageController
├── Billing/
│   └── BillingController.php          ← MOVE from User/BillingController
├── Contacts/
│   ├── ContactController.php          ← MOVE ContactController
│   └── ExportController.php           (future)
├── Redeem/
│   ├── RedeemController.php           ✓ Already grouped
│   └── RedeemWizardController.php     ✓ Already grouped
├── Settings/
│   ├── AppearanceController.php       (exists in routes)
│   ├── CampaignController.php         ✓ Already here
│   ├── PreferencesController.php      ✓ Already here
│   ├── ProfileController.php          ✓ Already here
│   ├── TwoFactorAuthenticationController.php  ✓ Already here
│   └── WalletController.php           ✓ Already here
├── Transactions/
│   ├── TransactionController.php      ← MOVE TransactionController
│   └── ExportController.php           (future)
├── Vouchers/
│   ├── VoucherController.php          ✓ Already here (rename Voucher/ → Vouchers/)
│   ├── GenerateController.php         ← MOVE VoucherGenerationController
│   └── ExportController.php           (future)
└── Wallet/
    ├── BalanceController.php          ← MOVE CheckWalletBalanceController
    ├── LoadController.php             ✓ Already here
    ├── LoadPublicController.php       ✓ Already here
    └── TopUpController.php            ← MOVE TopUpController
```

### Changes Required

#### 1. Rename `Voucher/` → `Vouchers/` (plural consistency)

```bash
mv app/Http/Controllers/Voucher app/Http/Controllers/Vouchers
```

**Update namespace in:**
- `app/Http/Controllers/Vouchers/VoucherController.php`

#### 2. Move Standalone Controllers

| Current | New Location | New Name |
|---------|-------------|----------|
| `BalancePageController.php` | `Balances/BalanceController.php` | `BalanceController` |
| `CheckWalletBalanceController.php` | `Wallet/BalanceController.php` | Keep or rename to `CheckBalanceController` |
| `ContactController.php` | `Contacts/ContactController.php` | Keep name |
| `TopUpController.php` | `Wallet/TopUpController.php` | Keep name |
| `TransactionController.php` | `Transactions/TransactionController.php` | Keep name |
| `VoucherGenerationController.php` | `Vouchers/GenerateController.php` | `GenerateController` |
| `User/BillingController.php` | `Billing/BillingController.php` | Keep name |

#### 3. Update Route Definitions

**Files to update:**
- `routes/web.php`
- `routes/settings.php`
- Any other route files

**Pattern changes:**
```php
// Before
use App\Http\Controllers\BalancePageController;
Route::get('/balances', [BalancePageController::class, 'index']);

// After
use App\Http\Controllers\Balances\BalanceController;
Route::get('/balances', [BalanceController::class, 'index']);
```

#### 4. Update Wayfinder Route Generation

After moving controllers, regenerate Wayfinder routes:
```bash
npm run dev  # This will auto-regenerate TypeScript routes
```

---

## 📋 Phase 2: Frontend Components Rationalization

### Issue: `resources/js/components/domain/`

**Current:**
```
components/domain/
└── QrDisplay.vue  # Generic QR code display component
```

**Problem:** "domain" is vague and doesn't indicate what this is for.

**Analysis:**
- `QrDisplay.vue` is used in `wallet/LoadPublic.vue` and `wallet/Load.vue`
- It's a generic QR display component (not domain-specific)
- Could be used for any QR code (vouchers, payments, etc.)

**Recommendation:** Move to `components/` root or create `components/shared/`

```
Option A (Simple): Move to root
components/
└── QrDisplay.vue

Option B (Organized): Create shared/
components/shared/
└── QrDisplay.vue
```

**Justification:** QR display is a **UI utility**, not a domain concept.

### Issue: `resources/js/components/voucher/`

**Current Structure:**
```
components/voucher/
├── forms/                    # Form components (VoucherInstructionsForm, etc.)
├── views/                    # Display components (VoucherDetailsView, etc.)
└── README.md
```

**Analysis:**
- ✅ Well-organized with clear separation (forms vs views)
- ✅ Comprehensive documentation
- ✅ Maps to PHP DTOs
- ✅ Used across multiple pages (Generate, Campaigns, Show)
- ✅ Follows composition pattern

**Recommendation:** **Keep as-is** ✓

**Justification:** 
- This is a **true domain component library**
- Provides reusable voucher-specific components
- Already follows best practices
- Has excellent documentation

### Proposed Component Structure

```
resources/js/components/
├── shared/                   # NEW: Shared utility components
│   └── QrDisplay.vue         ← MOVE from domain/
├── voucher/                  # ✓ Keep as-is
│   ├── forms/
│   ├── views/
│   └── README.md
├── ui/                       # ✓ Keep (shadcn components)
├── AlertError.vue
├── AppContent.vue
├── AppHeader.vue
├── AppLogo.vue
├── AppShell.vue
├── AppSidebar.vue
├── BalanceWidget.vue
├── BankSelect.vue
├── ...
└── (other root-level components)
```

**Alternative:** If more components like `QrDisplay` appear, create `shared/`:
```
shared/
├── QrDisplay.vue
├── ImageUpload.vue          (future)
├── LocationPicker.vue       (future)
└── PhoneInput.vue           (future)
```

### Changes Required

#### 1. Rename `domain/` → `shared/` (or move to root)

```bash
# Option A: Rename to shared
mv resources/js/components/domain resources/js/components/shared

# Option B: Move QrDisplay to root
mv resources/js/components/domain/QrDisplay.vue resources/js/components/QrDisplay.vue
rmdir resources/js/components/domain
```

#### 2. Update Imports

**Files to update:**
- `resources/js/pages/wallet/Load.vue`
- `resources/js/pages/wallet/LoadPublic.vue`

```typescript
// Before
import QrDisplay from '@/components/domain/QrDisplay.vue';

// After (Option A)
import QrDisplay from '@/components/shared/QrDisplay.vue';

// After (Option B)
import QrDisplay from '@/components/QrDisplay.vue';
```

---

## 📋 Phase 3: Verify Frontend Pages Structure

### Current Structure (Already Correct ✅)

```
resources/js/pages/
├── admin/
│   ├── billing/
│   └── pricing/
├── balances/
├── billing/
├── contacts/
├── redeem/
├── settings/
│   └── campaigns/
├── transactions/
├── vouchers/
│   └── Generate/           ← Note: Capital G (consider lowercase)
├── wallet/
├── Dashboard.vue
└── Welcome.vue
```

### Minor Fix: `vouchers/Generate/` → `vouchers/generate/`

For full consistency, rename:
```bash
mv resources/js/pages/vouchers/Generate resources/js/pages/vouchers/generate
```

**Update Inertia::render() in:**
- `VoucherGenerationController` → `Vouchers/GenerateController`

```php
// Before
return Inertia::render('vouchers/Generate/Create', [...]);
return Inertia::render('vouchers/Generate/Success', [...]);

// After
return Inertia::render('vouchers/generate/Create', [...]);
return Inertia::render('vouchers/generate/Success', [...]);
```

---

## 📋 Phase 4: Final Directory Structure

### Backend
```
app/Http/Controllers/
├── Admin/
│   ├── BillingController.php
│   └── PricingController.php
├── Api/
│   ├── BalanceController.php
│   ├── Vouchers/
│   └── ...
├── Balances/
│   └── BalanceController.php
├── Billing/
│   └── BillingController.php
├── Contacts/
│   └── ContactController.php
├── Redeem/
│   ├── RedeemController.php
│   └── RedeemWizardController.php
├── Settings/
│   ├── AppearanceController.php
│   ├── CampaignController.php
│   ├── PreferencesController.php
│   ├── ProfileController.php
│   ├── TwoFactorAuthenticationController.php
│   └── WalletController.php
├── Transactions/
│   └── TransactionController.php
├── Vouchers/
│   ├── VoucherController.php
│   └── GenerateController.php
├── Wallet/
│   ├── BalanceController.php
│   ├── LoadController.php
│   ├── LoadPublicController.php
│   └── TopUpController.php
├── Webhooks/
│   └── NetBankWebhookController.php
└── Controller.php
```

### Frontend Pages
```
resources/js/pages/
├── admin/
│   ├── billing/
│   │   ├── Index.vue
│   │   └── Show.vue
│   └── pricing/
│       ├── Edit.vue
│       └── Index.vue
├── balances/
│   └── Index.vue
├── billing/
│   └── Index.vue
├── contacts/
│   ├── Index.vue
│   └── Show.vue
├── redeem/
│   ├── Error.vue
│   ├── Finalize.vue
│   ├── Inputs.vue
│   ├── Location.vue
│   ├── Selfie.vue
│   ├── Signature.vue
│   ├── Start.vue
│   ├── Success.vue
│   └── Wallet.vue
├── settings/
│   ├── campaigns/
│   │   ├── Create.vue
│   │   ├── Edit.vue
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── Appearance.vue
│   ├── Preferences.vue
│   ├── Profile.vue
│   └── Wallet.vue
├── transactions/
│   └── Index.vue
├── vouchers/
│   ├── generate/           ← lowercase
│   │   ├── Create.vue
│   │   └── Success.vue
│   ├── Index.vue
│   └── Show.vue
├── wallet/
│   ├── Balance.vue
│   ├── Load.vue
│   ├── LoadPublic.vue
│   ├── TopUp.vue
│   └── TopUpCallback.vue
├── Dashboard.vue
└── Welcome.vue
```

### Frontend Components
```
resources/js/components/
├── shared/                 ← NEW (or merge into root)
│   └── QrDisplay.vue
├── voucher/                ← KEEP
│   ├── forms/
│   │   ├── CashInstructionForm.vue
│   │   ├── CashValidationRulesForm.vue
│   │   ├── FeedbackInstructionForm.vue
│   │   ├── InputFieldsForm.vue
│   │   ├── RiderInstructionForm.vue
│   │   ├── TimeValidationForm.vue
│   │   ├── LocationValidationForm.vue
│   │   ├── VoucherInstructionsForm.vue
│   │   └── index.ts
│   ├── views/
│   │   ├── VoucherCodeDisplay.vue
│   │   ├── VoucherDetailsTabContent.vue
│   │   ├── VoucherDetailsView.vue
│   │   ├── VoucherOwnerView.vue
│   │   ├── VoucherRedemptionView.vue
│   │   ├── VoucherStatusCard.vue
│   │   └── index.ts
│   └── README.md
├── ui/                     ← KEEP (shadcn)
└── (other root components)
```

---

## 🚀 Implementation Steps

### Step 1: Backend Controllers (Estimated: 2-3 hours)

**Sub-steps:**
1. ✅ Create new directories
2. ✅ Move controller files
3. ✅ Update namespaces in moved controllers
4. ✅ Update route files
5. ✅ Update Wayfinder generation
6. ✅ Test all routes work
7. ✅ Run tests: `php artisan test`

**Commands:**
```bash
# 1. Rename Voucher → Vouchers
git mv app/Http/Controllers/Voucher app/Http/Controllers/Vouchers

# 2. Create new directories
mkdir -p app/Http/Controllers/{Balances,Billing,Contacts,Transactions}

# 3. Move files (examples)
git mv app/Http/Controllers/BalancePageController.php app/Http/Controllers/Balances/BalanceController.php
git mv app/Http/Controllers/ContactController.php app/Http/Controllers/Contacts/ContactController.php
git mv app/Http/Controllers/TransactionController.php app/Http/Controllers/Transactions/TransactionController.php
git mv app/Http/Controllers/VoucherGenerationController.php app/Http/Controllers/Vouchers/GenerateController.php
git mv app/Http/Controllers/TopUpController.php app/Http/Controllers/Wallet/TopUpController.php
git mv app/Http/Controllers/CheckWalletBalanceController.php app/Http/Controllers/Wallet/BalanceController.php
git mv app/Http/Controllers/User/BillingController.php app/Http/Controllers/Billing/BillingController.php

# 4. Update namespaces in all moved files (see detailed list below)

# 5. Update route files (see detailed changes below)

# 6. Regenerate Wayfinder routes
npm run dev

# 7. Test
php artisan route:list
php artisan test
```

### Step 2: Frontend Components (Estimated: 30 min)

```bash
# Option A: Rename domain → shared
git mv resources/js/components/domain resources/js/components/shared

# Option B: Move QrDisplay to root
git mv resources/js/components/domain/QrDisplay.vue resources/js/components/QrDisplay.vue
rmdir resources/js/components/domain

# Update imports in:
# - resources/js/pages/wallet/Load.vue
# - resources/js/pages/wallet/LoadPublic.vue
```

### Step 3: Frontend Pages (Estimated: 15 min)

```bash
# Lowercase Generate directory
git mv resources/js/pages/vouchers/Generate resources/js/pages/vouchers/generate

# Update Inertia::render() calls in Vouchers/GenerateController.php
```

### Step 4: Testing (Estimated: 1 hour)

```bash
# 1. Build frontend
npm run build

# 2. Run PHP tests
php artisan test

# 3. Manual testing checklist
# - Visit /vouchers (voucher list)
# - Visit /vouchers/generate (generate form)
# - Visit /balances (balance monitoring)
# - Visit /contacts (contact list)
# - Visit /transactions (transaction history)
# - Visit /settings/campaigns (campaign list)
# - Visit /wallet/load (wallet loading)
# - Visit /topup (top-up)
```

---

## 📝 Detailed File Changes

### Backend Namespace Updates

**After moving files, update these namespaces:**

1. **Balances/BalanceController.php** (was BalancePageController.php)
```php
namespace App\Http\Controllers\Balances;
```

2. **Billing/BillingController.php** (was User/BillingController.php)
```php
namespace App\Http\Controllers\Billing;
```

3. **Contacts/ContactController.php** (was ContactController.php)
```php
namespace App\Http\Controllers\Contacts;
```

4. **Transactions/TransactionController.php** (was TransactionController.php)
```php
namespace App\Http\Controllers\Transactions;
```

5. **Vouchers/VoucherController.php** (was Voucher/VoucherController.php)
```php
namespace App\Http\Controllers\Vouchers;
```

6. **Vouchers/GenerateController.php** (was VoucherGenerationController.php)
```php
namespace App\Http\Controllers\Vouchers;
class GenerateController extends Controller
{
    // Rename class from VoucherGenerationController
}
```

7. **Wallet/BalanceController.php** (was CheckWalletBalanceController.php)
```php
namespace App\Http\Controllers\Wallet;
class BalanceController extends Controller  // or CheckBalanceController
{
    // Keep or rename class
}
```

8. **Wallet/TopUpController.php** (was TopUpController.php)
```php
namespace App\Http\Controllers\Wallet;
```

### Route File Updates

**routes/web.php:**
```php
// Before
use App\Http\Controllers\BalancePageController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VoucherGenerationController;
use App\Http\Controllers\Voucher\VoucherController;
use App\Http\Controllers\CheckWalletBalanceController;
use App\Http\Controllers\TopUpController;

// After
use App\Http\Controllers\Balances\BalanceController as BalancesController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Transactions\TransactionController;
use App\Http\Controllers\Vouchers\GenerateController as VoucherGenerateController;
use App\Http\Controllers\Vouchers\VoucherController;
use App\Http\Controllers\Wallet\BalanceController as WalletBalanceController;
use App\Http\Controllers\Wallet\TopUpController;

// Update route definitions
Route::get('/balances', [BalancesController::class, 'index'])->name('balances.index');
Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
Route::get('/vouchers/generate', [VoucherGenerateController::class, 'create'])->name('vouchers.generate.create');
// etc.
```

---

## 🎯 Benefits of This Refactoring

### 1. **Predictable Structure**
- New developers can instantly find code
- Backend mirrors frontend mirrors URLs
- No "hidden" standalone controllers

### 2. **Scalable**
```
// Adding new feature? Clear where it goes:
app/Http/Controllers/Invoices/InvoiceController.php
resources/js/pages/invoices/Index.vue
URL: /invoices
```

### 3. **Maintainable**
- Clear domain boundaries
- Easy to see feature scope
- Reduces merge conflicts

### 4. **Laravel Standard**
- Follows Laravel community conventions
- Familiar to experienced Laravel devs
- Easier to onboard contributors

---

## ⚠️ Risks & Mitigation

### Risk 1: Breaking Changes
**Impact:** Routes might break temporarily  
**Mitigation:** 
- Do in development branch
- Test all routes before merging
- Use `php artisan route:list` to verify

### Risk 2: Wayfinder Route Generation
**Impact:** TypeScript routes need regeneration  
**Mitigation:**
- Run `npm run dev` after moving controllers
- Commit generated routes files
- Test imports in Vue files

### Risk 3: Existing PRs/Branches
**Impact:** Merge conflicts in other branches  
**Mitigation:**
- Communicate refactoring to team
- Rebase other branches after merge
- Document changes in PR

---

## 📊 Success Criteria

- [ ] All backend controllers grouped by domain
- [ ] No standalone controllers in root `Controllers/` directory
- [ ] `Voucher/` renamed to `Vouchers/` for consistency
- [ ] `domain/` components rationalized (moved to `shared/` or root)
- [ ] `vouchers/Generate/` renamed to `vouchers/generate/`
- [ ] All routes working (`php artisan route:list`)
- [ ] All tests passing (`php artisan test`)
- [ ] Frontend builds without errors (`npm run build`)
- [ ] Wayfinder routes regenerated
- [ ] Documentation updated (WARP.md)

---

## 📅 Timeline

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| Phase 1 | Backend reorganization | 2-3 hours |
| Phase 2 | Component rationalization | 30 minutes |
| Phase 3 | Pages structure fixes | 15 minutes |
| Phase 4 | Testing & verification | 1 hour |
| **Total** | | **4-5 hours** |

---

## 🤝 Recommendation

**Priority: HIGH**

This refactoring should be done **before open sourcing** because:
1. Sets proper conventions early
2. Prevents technical debt accumulation
3. Creates better first impression for contributors
4. Reduces confusion in issues/PRs

**Suggested Approach:**
1. Create feature branch: `refactor/domain-organization`
2. Complete Phase 1 → commit → test
3. Complete Phase 2 → commit → test
4. Complete Phase 3 → commit → test
5. Phase 4: Final verification
6. Create PR with this plan attached
7. Merge after approval

---

**Questions or concerns? Review this plan before proceeding.**
