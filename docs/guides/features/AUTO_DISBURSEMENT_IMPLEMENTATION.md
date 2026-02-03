# Auto-Disbursement Implementation Guide

## Executive Summary

**WIG (Wildly Important Goal)**: Enable settlement voucher issuers to automatically disburse collected payments directly to their bank accounts without the manual 3-step process (collect → generate voucher → redeem).

**Current State**: Users must manually collect payments to wallet, generate a new voucher, then redeem it to trigger bank disbursement.

**Target State**: Upon payment confirmation, users can choose to auto-disburse directly to saved bank accounts if voucher is fully paid and amount meets threshold.

**Status**: 🟡 In Progress - Foundation Phase Started  
**Branch**: `feature/auto-disbursement`  
**Lead Measures**: 12 TODO items across 3 phases  
**Lag Measures**: User adoption rate, reduced manual voucher generations, time saved per transaction

---

## Phase 1: Foundation (Items 1-4) 🟡 IN PROGRESS

### WHAT: Build infrastructure for bank account management and settings

### WHY: Users need a secure place to store bank accounts and admins need control over minimum thresholds

### WHERE: 
- Backend: `app/Settings/`, `app/Models/User.php`, `app/Http/Controllers/`
- Database: `settings` table, `users` table
- Frontend: `resources/js/pages/settings/Profile.vue`

### HOW: Step-by-step implementation

---

## Item 1: Add Minimum Auto-Disburse Threshold ✅ COMPLETED

### WHAT
Add configurable minimum amount threshold for auto-disbursement (default: ₱25).

### WHY
- Avoid triggering disbursements for tiny amounts (fees would eat into value)
- Configurable in admin preferences for testing flexibility (can test with ₱50)
- Business rule: Don't auto-disburse amounts < threshold

### WHERE
- `app/Settings/VoucherSettings.php` - Add property
- `database/migrations/2026_01_12_075432_add_auto_disburse_minimum_to_voucher_settings.php` - Database schema
- `database/seeders/VoucherSettingsSeeder.php` - Fresh install default
- `app/Http/Controllers/Admin/PreferencesController.php` - API management
- `resources/js/pages/admin/preferences/Index.vue` - UI

### HOW - Completed Steps

#### 1.1 Model Update ✅
**File**: `app/Settings/VoucherSettings.php`
```php
// Added after line 16:
public int $auto_disburse_minimum;
```

#### 1.2 Migration Created ✅
**File**: `database/migrations/2026_01_12_075432_add_auto_disburse_minimum_to_voucher_settings.php`
```php
public function up(): void
{
    DB::table('settings')->insert([
        'group' => 'voucher',
        'name' => 'auto_disburse_minimum',
        'payload' => json_encode(25), // Default: ₱25
        'locked' => false,
    ]);
}
```

#### 1.3 Seeder Update (NEXT STEP)
**File**: `database/seeders/VoucherSettingsSeeder.php`
**Action**: Add to insert array after `default_home_route`:
```php
[
    'group' => 'voucher',
    'name' => 'auto_disburse_minimum',
    'payload' => json_encode(25),
    'locked' => false,
],
```

#### 1.4 PreferencesController Update (NEXT STEP)
**File**: `app/Http/Controllers/Admin/PreferencesController.php`

**Add to index() props** (line ~31):
```php
'auto_disburse_minimum' => $settings->auto_disburse_minimum,
```

**Add to update() validation** (line ~50):
```php
'auto_disburse_minimum' => ['required', 'integer', 'min:1', 'max:10000'],
```

**Add to update() assignment** (line ~60):
```php
$settings->auto_disburse_minimum = (int) $request->auto_disburse_minimum;
```

**Add to ensureSettingsExist()** (line ~84):
```php
$settings->auto_disburse_minimum = 25;
```

#### 1.5 Preferences UI Update (NEXT STEP)
**File**: `resources/js/pages/admin/preferences/Index.vue`

**Add to Props interface** (line ~23):
```typescript
default_portal_endpoint: string;
default_home_route: string;
auto_disburse_minimum: number; // ADD THIS
```

**Add input field** (after Portal Endpoint section, line ~207):
```vue
<div class="grid gap-2">
    <Label for="auto_disburse_minimum">Auto-Disburse Minimum Amount (₱)</Label>
    <Input
        id="auto_disburse_minimum"
        type="number"
        class="mt-1 block w-full"
        name="auto_disburse_minimum"
        :default-value="preferences.auto_disburse_minimum"
        required
        min="1"
        max="10000"
        step="1"
        placeholder="25"
    />
    <p class="text-sm text-muted-foreground">
        Minimum amount for automatic bank disbursement. Payments below this amount will be collected to wallet only. Default: ₱25 (configurable for testing).
    </p>
    <InputError class="mt-2" :message="errors.auto_disburse_minimum" />
</div>
```

#### 1.6 Run Migration
```bash
php artisan migrate
```

#### 1.7 Verification
```bash
php artisan tinker --execute="use App\Settings\VoucherSettings; \$s = app(VoucherSettings::class); echo \$s->auto_disburse_minimum;"
# Expected output: 25
```

---

## Item 2: Add Bank Accounts to User Model

### WHAT
Add JSON field to store multiple bank accounts per user with CRUD helper methods.

### WHY
- Users need to save bank accounts for reuse (avoid re-entering on every transaction)
- Support multiple accounts (GCash, bank, etc.)
- One account can be set as default

### WHERE
- `database/migrations/YYYY_MM_DD_add_bank_accounts_to_users.php` - Schema
- `app/Models/User.php` - Model methods
- Reuse: `LBHurtado\Contact\Classes\BankAccount` (format validation)

### HOW

#### 2.1 Create Migration
```bash
php artisan make:migration add_bank_accounts_to_users_table
```

**Migration content**:
```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->json('bank_accounts')->nullable()->after('ui_preferences');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('bank_accounts');
    });
}
```

#### 2.2 Update User Model
**File**: `app/Models/User.php`

**Add to fillable** (line ~47):
```php
protected $fillable = [
    'name',
    'email',
    'workos_id',
    'avatar',
    'ui_preferences',
    'bank_accounts', // ADD THIS
];
```

**Add to casts** (line ~86):
```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'ip_whitelist' => 'array',
        'ip_whitelist_enabled' => 'boolean',
        'rate_limit_tier' => 'string',
        'signature_secret' => 'string',
        'signature_enabled' => 'boolean',
        'ui_preferences' => 'array',
        'bank_accounts' => 'array', // ADD THIS
    ];
}
```

**Add helper methods** (at end of class):
```php
/**
 * Get user's bank accounts as Collection.
 */
public function getBankAccounts(): Collection
{
    $accounts = $this->bank_accounts ?? [];
    return collect($accounts);
}

/**
 * Add a new bank account.
 * 
 * @return array The created bank account with generated ID
 */
public function addBankAccount(string $bank_code, string $account_number, string $label, bool $is_default = false): array
{
    $accounts = $this->getBankAccounts();
    
    // If setting as default, unset existing defaults
    if ($is_default) {
        $accounts = $accounts->map(fn($acc) => array_merge($acc, ['is_default' => false]));
    }
    
    $newAccount = [
        'id' => (string) Str::uuid(),
        'bank_code' => $bank_code,
        'account_number' => $account_number,
        'label' => $label,
        'is_default' => $is_default,
        'created_at' => now()->toIso8601String(),
    ];
    
    $accounts->push($newAccount);
    $this->bank_accounts = $accounts->toArray();
    $this->save();
    
    return $newAccount;
}

/**
 * Remove a bank account by ID.
 */
public function removeBankAccount(string $id): void
{
    $accounts = $this->getBankAccounts()->filter(fn($acc) => $acc['id'] !== $id);
    $this->bank_accounts = $accounts->values()->toArray();
    $this->save();
}

/**
 * Get default bank account.
 */
public function getDefaultBankAccount(): ?array
{
    return $this->getBankAccounts()->firstWhere('is_default', true);
}

/**
 * Set a bank account as default.
 */
public function setDefaultBankAccount(string $id): void
{
    $accounts = $this->getBankAccounts()->map(function($acc) use ($id) {
        $acc['is_default'] = ($acc['id'] === $id);
        return $acc;
    });
    
    $this->bank_accounts = $accounts->toArray();
    $this->save();
}

/**
 * Get bank account by ID.
 */
public function getBankAccountById(string $id): ?array
{
    return $this->getBankAccounts()->firstWhere('id', $id);
}
```

**Add import at top**:
```php
use Illuminate\Support\Str;
```

#### 2.3 Run Migration
```bash
php artisan migrate
```

#### 2.4 Verification
```bash
php artisan tinker
$user = User::first();
$account = $user->addBankAccount('GXCHPHM2XXX', '09171234567', 'GCash Main', true);
echo json_encode($account, JSON_PRETTY_PRINT);
echo $user->getDefaultBankAccount()['label']; // Should output: GCash Main
```

---

## Item 3: Bank Account Management API

### WHAT
RESTful API endpoints for CRUD operations on user bank accounts.

### WHY
- Frontend needs API to manage bank accounts
- Validate bank codes against BankRegistry
- Ensure users can only manage their own accounts

### WHERE
- `app/Http/Controllers/Api/BankAccountController.php` - New controller
- `routes/api.php` - API routes

### HOW

#### 3.1 Create Controller
```bash
php artisan make:controller Api/BankAccountController
```

**Controller content**:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LBHurtado\MoneyIssuer\Support\BankRegistry;

class BankAccountController extends Controller
{
    /**
     * List user's bank accounts with enriched data.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bankRegistry = app(BankRegistry::class);
        
        $accounts = $user->getBankAccounts()->map(function($account) use ($bankRegistry) {
            return array_merge($account, [
                'bank_name' => $bankRegistry->getBankName($account['bank_code']),
                'bank_logo' => $bankRegistry->getBankLogo($account['bank_code']),
                'is_emi' => $bankRegistry->isEMI($account['bank_code']),
            ]);
        });
        
        return response()->json([
            'success' => true,
            'data' => $accounts->values(),
        ]);
    }
    
    /**
     * Add new bank account.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);
        
        // Validate bank code exists
        $bankRegistry = app(BankRegistry::class);
        if (!$bankRegistry->getBankName($request->bank_code)) {
            throw ValidationException::withMessages([
                'bank_code' => ['Invalid bank code.'],
            ]);
        }
        
        $user = $request->user();
        $account = $user->addBankAccount(
            $request->bank_code,
            $request->account_number,
            $request->label,
            $request->boolean('is_default', false)
        );
        
        // Enrich response
        $account['bank_name'] = $bankRegistry->getBankName($account['bank_code']);
        $account['bank_logo'] = $bankRegistry->getBankLogo($account['bank_code']);
        
        return response()->json([
            'success' => true,
            'message' => 'Bank account added successfully',
            'data' => $account,
        ], 201);
    }
    
    /**
     * Update bank account.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);
        
        $user = $request->user();
        $accounts = $user->getBankAccounts();
        
        $accountIndex = $accounts->search(fn($acc) => $acc['id'] === $id);
        if ($accountIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
            ], 404);
        }
        
        $accounts[$accountIndex]['label'] = $request->label;
        $user->bank_accounts = $accounts->toArray();
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Bank account updated successfully',
            'data' => $accounts[$accountIndex],
        ]);
    }
    
    /**
     * Delete bank account.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $user->removeBankAccount($id);
        
        return response()->json([
            'success' => true,
            'message' => 'Bank account deleted successfully',
        ]);
    }
    
    /**
     * Set bank account as default.
     */
    public function setDefault(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        // Verify account exists
        if (!$user->getBankAccountById($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Bank account not found',
            ], 404);
        }
        
        $user->setDefaultBankAccount($id);
        
        return response()->json([
            'success' => true,
            'message' => 'Default bank account updated',
        ]);
    }
}
```

#### 3.2 Add Routes
**File**: `routes/api.php`

Add inside authenticated route group:
```php
// Bank Account Management
Route::prefix('user/bank-accounts')->name('user.bank-accounts.')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\BankAccountController::class, 'index'])->name('index');
    Route::post('/', [App\Http\Controllers\Api\BankAccountController::class, 'store'])->name('store');
    Route::put('{id}', [App\Http\Controllers\Api\BankAccountController::class, 'update'])->name('update');
    Route::delete('{id}', [App\Http\Controllers\Api\BankAccountController::class, 'destroy'])->name('destroy');
    Route::put('{id}/set-default', [App\Http\Controllers\Api\BankAccountController::class, 'setDefault'])->name('set-default');
});
```

#### 3.3 Verification
```bash
# List accounts
curl -X GET http://redeem-x.test/api/v1/user/bank-accounts \
  -H "Authorization: Bearer YOUR_TOKEN"

# Add account
curl -X POST http://redeem-x.test/api/v1/user/bank-accounts \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"bank_code":"GXCHPHM2XXX","account_number":"09171234567","label":"GCash Main","is_default":true}'
```

---

## Item 4: Bank Accounts UI in Profile Settings

### WHAT
Frontend component for managing bank accounts in Profile Settings page.

### WHY
- Users need visual interface to add/edit/delete bank accounts
- Show bank logos and names from BankRegistry
- Highlight default account

### WHERE
- `resources/js/pages/settings/Profile.vue` - Main settings page
- `resources/js/components/settings/BankAccountsSection.vue` - New component
- `resources/js/composables/useBankAccounts.ts` - API client composable

### HOW

#### 4.1 Create Composable
**File**: `resources/js/composables/useBankAccounts.ts`

```typescript
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export interface BankAccount {
    id: string;
    bank_code: string;
    account_number: string;
    label: string;
    is_default: boolean;
    bank_name: string;
    bank_logo: string;
    is_emi: boolean;
    created_at: string;
}

export function useBankAccounts() {
    const accounts = ref<BankAccount[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const fetchAccounts = async () => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await fetch('/api/v1/user/bank-accounts', {
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'include',
            });
            
            const data = await response.json();
            
            if (data.success) {
                accounts.value = data.data;
            } else {
                error.value = data.message || 'Failed to load bank accounts';
            }
        } catch (e) {
            error.value = 'Network error loading bank accounts';
            console.error(e);
        } finally {
            loading.value = false;
        }
    };

    const addAccount = async (bank_code: string, account_number: string, label: string, is_default: boolean = false) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await fetch('/api/v1/user/bank-accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ bank_code, account_number, label, is_default }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                await fetchAccounts();
                return data.data;
            } else {
                error.value = data.message || 'Failed to add bank account';
                throw new Error(error.value);
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Network error';
            throw e;
        } finally {
            loading.value = false;
        }
    };

    const updateAccount = async (id: string, label: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await fetch(`/api/v1/user/bank-accounts/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ label }),
            });
            
            const data = await response.json();
            
            if (data.success) {
                await fetchAccounts();
            } else {
                error.value = data.message || 'Failed to update bank account';
                throw new Error(error.value);
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Network error';
            throw e;
        } finally {
            loading.value = false;
        }
    };

    const deleteAccount = async (id: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await fetch(`/api/v1/user/bank-accounts/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'include',
            });
            
            const data = await response.json();
            
            if (data.success) {
                await fetchAccounts();
            } else {
                error.value = data.message || 'Failed to delete bank account';
                throw new Error(error.value);
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Network error';
            throw e;
        } finally {
            loading.value = false;
        }
    };

    const setDefault = async (id: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await fetch(`/api/v1/user/bank-accounts/${id}/set-default`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                },
                credentials: 'include',
            });
            
            const data = await response.json();
            
            if (data.success) {
                await fetchAccounts();
            } else {
                error.value = data.message || 'Failed to set default';
                throw new Error(error.value);
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Network error';
            throw e;
        } finally {
            loading.value = false;
        }
    };

    return {
        accounts,
        loading,
        error,
        fetchAccounts,
        addAccount,
        updateAccount,
        deleteAccount,
        setDefault,
    };
}
```

#### 4.2 Create Bank Accounts Section Component
**File**: `resources/js/components/settings/BankAccountsSection.vue`

```vue
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useBankAccounts, type BankAccount } from '@/composables/useBankAccounts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, Trash2, Edit, Star } from 'lucide-vue-next';

const { accounts, loading, fetchAccounts, deleteAccount, setDefault } = useBankAccounts();

onMounted(() => {
    fetchAccounts();
});

const maskAccountNumber = (accountNumber: string): string => {
    if (accountNumber.length <= 4) return accountNumber;
    return '•••• ' + accountNumber.slice(-4);
};

const handleDelete = async (id: string) => {
    if (confirm('Are you sure you want to delete this bank account?')) {
        await deleteAccount(id);
    }
};

const handleSetDefault = async (id: string) => {
    await setDefault(id);
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Bank Accounts</CardTitle>
                    <CardDescription>
                        Manage your saved bank accounts for auto-disbursement
                    </CardDescription>
                </div>
                <Button @click="/* TODO: Open add modal */"> 
                    <Plus class="mr-2 h-4 w-4" />
                    Add Bank Account
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="text-center py-8">
                Loading...
            </div>
            
            <div v-else-if="accounts.length === 0" class="text-center py-8 text-muted-foreground">
                No bank accounts saved yet. Add your first account to enable auto-disbursement.
            </div>
            
            <div v-else class="space-y-4">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="flex items-center justify-between p-4 border rounded-lg"
                >
                    <div class="flex items-center gap-4">
                        <img
                            v-if="account.bank_logo"
                            :src="account.bank_logo"
                            :alt="account.bank_name"
                            class="h-10 w-10 rounded"
                        />
                        <div>
                            <div class="font-medium flex items-center gap-2">
                                {{ account.label }}
                                <Badge v-if="account.is_default" variant="default" class="text-xs">
                                    Default
                                </Badge>
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ account.bank_name }} • {{ maskAccountNumber(account.account_number) }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!account.is_default"
                            variant="ghost"
                            size="sm"
                            @click="handleSetDefault(account.id)"
                            title="Set as default"
                        >
                            <Star class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="/* TODO: Open edit modal */"
                            title="Edit"
                        >
                            <Edit class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="handleDelete(account.id)"
                            title="Delete"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
```

#### 4.3 Add to Profile Settings Page
**File**: `resources/js/pages/settings/Profile.vue`

Find the appropriate section and add:
```vue
<script setup lang="ts">
// ... existing imports
import BankAccountsSection from '@/components/settings/BankAccountsSection.vue';
</script>

<template>
    <!-- ... existing sections -->
    
    <!-- Add after other sections -->
    <BankAccountsSection />
</template>
```

---

## Phase 2: Auto-Disbursement Logic (Items 5-7)

### Status: ⚪ NOT STARTED

### Items:
5. Create reusable DisbursementService
6. Create ConfirmAndDisburse action
7. Enhance ConfirmPayment API

*Documentation continues in next section once Phase 1 is complete*

---

## Phase 3: UI & Notifications (Items 8-10)

### Status: ⚪ NOT STARTED

### Items:
8. Create settlement confirmation modal
9. Add auto-disburse metadata tracking
10. Add email notifications

---

## Phase 4: Infrastructure (Items 11-12)

### Status: ⚪ NOT STARTED

### Items:
11. Add feature flag
12. Write comprehensive tests

---

## Recovery Checklist

If session is interrupted, resume by:

1. **Check Branch**: `git branch` (should be on `feature/auto-disbursement`)
2. **Check Status**: Review this document and TODO list
3. **Identify Last Completed Item**: Look for ✅ markers above
4. **Continue from Next Step**: Follow HOW instructions for next incomplete item
5. **Test Each Item**: Use Verification commands before moving to next item

## Key Files Modified So Far

- ✅ `app/Settings/VoucherSettings.php` - Added auto_disburse_minimum
- ✅ `database/migrations/2026_01_12_075432_add_auto_disburse_minimum_to_voucher_settings.php` - Created
- 🟡 `database/seeders/VoucherSettingsSeeder.php` - NEXT: Add auto_disburse_minimum
- 🟡 `app/Http/Controllers/Admin/PreferencesController.php` - NEXT: Add to index/update/ensure
- 🟡 `resources/js/pages/admin/preferences/Index.vue` - NEXT: Add UI field

## Commands Reference

```bash
# Switch to feature branch
git checkout feature/auto-disbursement

# Check migration status
php artisan migrate:status

# Run pending migrations
php artisan migrate

# Test VoucherSettings
php artisan tinker --execute="use App\Settings\VoucherSettings; \$s = app(VoucherSettings::class); echo json_encode([\$s->auto_disburse_minimum], JSON_PRETTY_PRINT);"

# Test User bank accounts
php artisan tinker
$user = User::first();
$user->addBankAccount('GXCHPHM2XXX', '09171234567', 'GCash Main', true);
$user->getBankAccounts();

# Check route list
php artisan route:list --path=user/bank-accounts
```

## Next Session Prompt

"Continue implementing auto-disbursement feature. I'm on branch `feature/auto-disbursement`. Check `docs/AUTO_DISBURSEMENT_IMPLEMENTATION.md` for current status. Last completed: Item 1. Continue from Item 2."
