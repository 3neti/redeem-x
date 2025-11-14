# System Wallet Architecture

## Overview

The voucher redemption system uses a **system wallet** as the master balance holder. All user wallet funding happens via **transfers FROM the system wallet TO user wallets**, never via direct deposits.

## Architecture

### Wallet Hierarchy

```
Bank Account (₱1,300)
    ↓
System Wallet (₱1,000,000 initially)
    ↓ transfer
User Wallets (₱0 initially, grows via transfers)
```

### Key Components

#### 1. System Wallet
- **Owner**: System user (identified by `SYSTEM_USER_ID` in `.env`)
- **Purpose**: Master balance holder that represents funds loaded from the bank
- **Initial balance**: Seeded with ₱1,000,000 in `SystemWalletSeeder`
- **Should be**: Always >= sum of all user wallets

#### 2. User Wallets
- **Purpose**: Hold redeemed voucher amounts
- **Funding**: Only via `TopupWalletAction` which transfers from system wallet
- **Disbursement**: Funds sent to external bank accounts

#### 3. TopupWalletAction
- **Location**: `packages/wallet/src/Actions/TopupWalletAction.php`
- **Function**: Transfers funds from system wallet to user wallet
- **Usage**: Called during voucher redemption (disbursement pipeline)

```php
// Transfer ₱100 from system to user
$transfer = TopupWalletAction::run($userWallet, 100.00);
// System: -₱100, User: +₱100
```

## Redemption Flow

### Before (WRONG - Direct Withdraw)
```php
// ❌ Old code: User wallet withdraws (where did money come from?)
$transaction = $userWallet->withdraw($amount);
```

**Problem**: User wallet balance could never have had funds to begin with!

### After (CORRECT - System Transfer)
```php
// ✅ New code: System transfers to user
$transfer = TopupWalletAction::run($userWallet, $amount);
$transaction = $transfer->deposit; // Use deposit side for tracking
```

**Result**: 
- System wallet decreases
- User wallet increases
- Bank balance remains unchanged (external)
- Invariant maintained: `system_balance + sum(user_balances) = initial_funding`

## Balance Reconciliation

### Purpose
Ensure user wallets never exceed actual bank balance to prevent financial liability.

### Formula
```
Bank Balance = ₱1,300 (real money in bank)
System Wallet = ₱1,000,000 (master balance)
User Wallets = ₱0 (sum of all user wallets, EXCLUDING system wallet)

Available = Bank - User Wallets - Buffer
```

### Implementation
**ReconciliationService** (`app/Services/ReconciliationService.php`):
- `getTotalSystemBalance()` - Sums ALL wallets EXCEPT system wallet
- `getBankBalance()` - Fetches balance from payment gateway API
- `getReconciliationStatus()` - Compares and returns status

### Status Levels
- 🟢 **SAFE**: User wallets < 90% of bank balance
- 🟡 **WARNING**: User wallets 90-100% of bank balance
- 🔴 **CRITICAL**: User wallets > bank balance (blocks generation!)

## Configuration

### Environment Variables
```bash
# System user (must exist in users table)
SYSTEM_USER_ID=system@disburse.cash

# Reconciliation settings
BALANCE_RECONCILIATION_ENABLED=true
BALANCE_RECONCILIATION_BUFFER=10  # 10% safety margin
BALANCE_RECONCILIATION_WARNING_THRESHOLD=90
BALANCE_RECONCILIATION_BLOCK_GENERATION=true
```

### Config Files
- `config/account.php` - System user configuration
- `config/balance.php` - Reconciliation settings

## Code Changes

### Files Modified

#### Payment Gateway - Netbank
**File**: `packages/payment-gateway/src/Gateways/Netbank/Traits/CanDisburse.php`

**Before**:
```php
$transaction = $wallet->withdraw($credits->getMinorAmount()->toInt(), [], false);
```

**After**:
```php
use LBHurtado\Wallet\Actions\TopupWalletAction;

$transfer = TopupWalletAction::run($wallet, $amount);
$transaction = $transfer->deposit;
```

#### Payment Gateway - Omnipay
**File**: `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`

**Before**:
```php
$transaction = $wallet->withdraw($credits->getMinorAmount()->toInt(), [], false);
```

**After**:
```php
use LBHurtado\Wallet\Actions\TopupWalletAction;

$transfer = TopupWalletAction::run($wallet, $amount);
$transaction = $transfer->deposit;
```

#### Reconciliation Service
**File**: `app/Services/ReconciliationService.php`

**Added**:
- Dependency injection for `SystemUserResolverService`
- System wallet exclusion in `getTotalSystemBalance()`:

```php
$systemUser = $this->systemUserResolver->resolve();
$systemWalletId = $systemUser->wallet->getKey();

$total = DB::table('wallets')
    ->where('id', '!=', $systemWalletId)  // Exclude system wallet!
    ->sum('balance') ?? 0;
```

## Testing

### Manual Test Scenario

1. **Fresh migration**:
   ```bash
   php artisan migrate:fresh --seed
   ```

2. **Check initial state**:
   ```bash
   # System wallet: ₱1,000,000
   # User wallets: ₱0
   # Bank: Not yet checked
   ```

3. **Check bank balance**:
   ```bash
   php artisan balances:check --account=113-001-00001-9
   # Bank: ₱1,300
   ```

4. **Check reconciliation**:
   ```bash
   php artisan tinker --execute="
   \$service = app(\App\Services\ReconciliationService::class);
   \$status = \$service->getReconciliationStatus();
   print_r(\$status);
   "
   ```

   Expected:
   - Bank: ₱1,300
   - User wallets: ₱0
   - Available: ₱1,170 (90% of bank)
   - Status: SAFE ✅

5. **Generate voucher** (₱100):
   - System wallet: ₱999,900 (decreased)
   - User wallet: ₱100 (increased)
   - User wallets total: ₱100
   - Status: Still SAFE (₱100 < ₱1,170)

6. **Generate beyond limit** (₱2,000):
   - ❌ BLOCKED! (₱2,000 > ₱1,170 available)

### Automated Tests

Run voucher redemption tests:
```bash
php artisan test --filter=Redeem
php artisan test --filter=Voucher
```

## Migration Guide

### For Existing Installations

If you already have vouchers and user wallets:

1. **Audit existing balances**:
   ```sql
   -- Check user wallet balances
   SELECT holder_id, balance FROM wallets WHERE holder_type = 'App\\Models\\User';
   
   -- Check system wallet
   SELECT balance FROM wallets 
   WHERE holder_type = 'App\\Models\\User' 
   AND holder_id = (SELECT id FROM users WHERE email = 'system@disburse.cash');
   ```

2. **Verify invariant**:
   ```
   system_balance + sum(user_balances) = initial_system_funding
   ```

3. **If discrepancy exists**:
   - Investigate which vouchers were redeemed
   - Adjust system wallet balance manually via tinker
   - Document adjustment reason

### Breaking Changes

**CRITICAL**: This change modifies the core disbursement logic. 

**Impact**:
- Old code assumed user wallets could withdraw funds they never had
- New code requires system wallet to transfer first
- Reconciliation now correctly excludes system wallet from user total

**Action Required**:
- Review any custom disbursement logic
- Update tests that mock wallet operations
- Ensure system wallet is always funded before redemptions

## Best Practices

### 1. Always Fund System Wallet First
```php
// In seeders or admin panel
$systemUser = User::where('email', env('SYSTEM_USER_ID'))->first();
$systemUser->depositFloat(1_000_000.00); // Top up system wallet
```

### 2. Never Deposit Directly to User Wallets
```php
// ❌ WRONG
$user->depositFloat(100.00);

// ✅ CORRECT
TopupWalletAction::run($user, 100.00);
```

### 3. Monitor Reconciliation Status
- Check `/balances` page regularly
- Set up alerts via `BALANCE_RECONCILIATION_ALERT_EMAILS`
- Run `php artisan balances:check --all` in cron job

### 4. Maintain Buffer
- Never set buffer to 0%
- Recommended: 10-20% buffer
- Higher buffer = more safety, less utilization

## Troubleshooting

### System Wallet Empty
**Symptom**: Transfers fail with insufficient balance error

**Solution**:
```bash
php artisan tinker
$system = User::where('email', env('SYSTEM_USER_ID'))->first();
$system->depositFloat(1_000_000.00); // Replenish
```

### User Wallets Exceed Bank Balance
**Symptom**: Reconciliation status CRITICAL, generation blocked

**Solution**:
1. Check actual bank balance via API
2. If bank balance is wrong, update in account_balances table
3. If user wallets are wrong, investigate redemption logs
4. Contact bank to top up account if needed

### Transfer Not Working
**Symptom**: `TopupWalletAction::run()` fails

**Check**:
1. System user exists: `User::where('email', env('SYSTEM_USER_ID'))->exists()`
2. System user has wallet: `$systemUser->wallet` is not null
3. System wallet has balance: `$systemUser->balanceFloat > 0`

## See Also

- [Balance Reconciliation Plan](BALANCE_RECONCILIATION_PLAN.md)
- [Omnipay Integration](OMNIPAY_INTEGRATION_PLAN.md)
- [Notification Templates](NOTIFICATION_TEMPLATES.md)
