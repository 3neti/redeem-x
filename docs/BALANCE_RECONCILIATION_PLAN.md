# Balance Reconciliation Implementation Plan

**Goal:** Ensure system wallet balances never exceed actual bank balance to prevent financial liability.

**Financial Rule:** `Total System Balance ≤ Bank Balance` (always!)

---

## Critical Issue

**Two balances exist:**
1. **System Balance** - Sum of all user wallet balances (what users can spend)
2. **Bank Balance** - Actual cash in NetBank account

**Problem:**
- If System Balance > Bank Balance = users can redeem more than you have
- Unable to fulfill disbursements
- Financial liability risk

---

## Implementation: Option A + C (Recommended)

### Phase 1: Balance Page Warnings (Quick Alert)
**Time:** 30 minutes

**Features:**
- Calculate total system balance (all user wallets)
- Compare with bank balance
- Show visual warnings on `/balances` page
- Log discrepancies for audit

**Warning Levels:**
1. 🟢 **SAFE** - System balance < 90% of bank balance
2. 🟡 **WARNING** - System balance 90-100% of bank balance
3. 🔴 **CRITICAL** - System balance > bank balance

### Phase 2: Pre-Generation Safety Check
**Time:** 1 hour

**Features:**
- Check available bank balance before voucher generation
- Calculate: `Available = Bank Balance - System Balance - Buffer`
- Block generation if insufficient funds
- Show user how much they can safely generate

---

## Configuration (.env)

### Reconciliation Settings

```bash
# Enable/disable reconciliation checks
BALANCE_RECONCILIATION_ENABLED=true

# Safety buffer percentage (10% = keep 10% reserve)
BALANCE_RECONCILIATION_BUFFER=10

# Warning threshold percentage (90% = warn at 90% usage)
BALANCE_RECONCILIATION_WARNING_THRESHOLD=90

# Block voucher generation if exceeds bank balance
BALANCE_RECONCILIATION_BLOCK_GENERATION=true

# Email alerts when discrepancy detected
BALANCE_RECONCILIATION_ALERT_EMAILS=admin@company.com,finance@company.com

# Check frequency (for scheduled job)
BALANCE_RECONCILIATION_CHECK_INTERVAL=hourly  # hourly, daily, etc.
```

### Override Options

```bash
# EMERGENCY OVERRIDE: Disable all checks (⚠️ USE CAREFULLY!)
BALANCE_RECONCILIATION_OVERRIDE=false

# Allow voucher generation even with low balance (⚠️ NOT RECOMMENDED!)
BALANCE_RECONCILIATION_ALLOW_OVERGENERATION=false

# Ignore warnings (still log, but don't show in UI)
BALANCE_RECONCILIATION_SUPPRESS_WARNINGS=false

# Custom buffer amount in centavos (overrides percentage)
BALANCE_RECONCILIATION_BUFFER_AMOUNT=5000000  # ₱50,000
```

---

## Technical Implementation

### 1. Calculate System Balance

**Method:** Sum all user wallet balances

```php
// In BalanceService or new ReconciliationService
public function getTotalSystemBalance(): int
{
    // Sum all user wallet balances (in centavos)
    return User::sum('balance') ?? 0;
    
    // Or if using bavix/laravel-wallet:
    return \Bavix\Wallet\Models\Wallet::sum('balance') ?? 0;
}
```

### 2. Calculate Available Amount

```php
public function getAvailableAmount(int $bankBalance): int
{
    $systemBalance = $this->getTotalSystemBalance();
    $buffer = $this->getBuffer($bankBalance);
    
    return max(0, $bankBalance - $systemBalance - $buffer);
}

protected function getBuffer(int $bankBalance): int
{
    // Custom amount takes precedence
    if ($customAmount = config('balance.reconciliation.buffer_amount')) {
        return $customAmount;
    }
    
    // Otherwise use percentage
    $bufferPercent = config('balance.reconciliation.buffer', 10);
    return (int) ($bankBalance * ($bufferPercent / 100));
}
```

### 3. Check Reconciliation Status

```php
public function getReconciliationStatus(int $bankBalance): array
{
    $systemBalance = $this->getTotalSystemBalance();
    $buffer = $this->getBuffer($bankBalance);
    $warningThreshold = config('balance.reconciliation.warning_threshold', 90);
    
    $discrepancy = $systemBalance - $bankBalance;
    $usagePercent = $bankBalance > 0 ? ($systemBalance / $bankBalance) * 100 : 0;
    
    // Determine status
    if ($discrepancy > 0) {
        $status = 'critical';  // System > Bank (DANGER!)
        $message = 'System balance exceeds bank balance!';
    } elseif ($usagePercent >= $warningThreshold) {
        $status = 'warning';  // Approaching limit
        $message = "System balance at {$usagePercent}% of bank balance";
    } else {
        $status = 'safe';  // All good
        $message = 'Balances reconciled';
    }
    
    return [
        'status' => $status,
        'message' => $message,
        'bank_balance' => $bankBalance,
        'system_balance' => $systemBalance,
        'discrepancy' => $discrepancy,
        'usage_percent' => round($usagePercent, 2),
        'available' => max(0, $bankBalance - $systemBalance - $buffer),
        'buffer' => $buffer,
    ];
}
```

### 4. Pre-Generation Check

```php
// In VoucherGenerationController or similar
public function validateGeneration(Request $request)
{
    if (!config('balance.reconciliation.enabled', true)) {
        return; // Checks disabled
    }
    
    if (config('balance.reconciliation.override', false)) {
        return; // Emergency override active
    }
    
    $service = app(ReconciliationService::class);
    $bankBalance = $service->getBankBalance();
    $available = $service->getAvailableAmount($bankBalance);
    $requestedAmount = $request->input('amount') * $request->input('count');
    
    if ($requestedAmount > $available && config('balance.reconciliation.block_generation', true)) {
        throw new InsufficientBankBalanceException(
            "Cannot generate vouchers. Available: " . 
            Money::ofMinor($available, 'PHP')->formatTo('en_PH')
        );
    }
}
```

---

## UI Components

### Balance Page Warning Card

**Location:** `/balances` page, top of page

**Display:**

```
┌─────────────────────────────────────────────┐
│ 🔴 CRITICAL: Balance Reconciliation Alert  │
│                                             │
│ Bank Balance:    ₱100,000.00               │
│ System Balance:  ₱105,000.00 ⚠️            │
│ Discrepancy:    +₱5,000.00 (Over limit!)   │
│                                             │
│ ACTION REQUIRED: System balance exceeds    │
│ bank balance. Stop voucher generation and  │
│ reconcile immediately.                      │
└─────────────────────────────────────────────┘
```

**Color Coding:**
- 🟢 Green - Safe (< 90%)
- 🟡 Yellow - Warning (90-100%)
- 🔴 Red - Critical (> 100%)

### Voucher Generation Warning

**Location:** Before voucher generation form submit

**Display:**

```
┌─────────────────────────────────────────────┐
│ ⚠️ Insufficient Bank Balance                │
│                                             │
│ You are trying to generate: ₱50,000.00     │
│ Available bank balance:     ₱30,000.00     │
│                                             │
│ Please reduce the amount or contact admin. │
│                                             │
│ [OK]                                        │
└─────────────────────────────────────────────┘
```

---

## Database Schema (Optional - for logging)

```php
Schema::create('balance_reconciliations', function (Blueprint $table) {
    $table->id();
    $table->bigInteger('bank_balance');
    $table->bigInteger('system_balance');
    $table->bigInteger('discrepancy');
    $table->decimal('usage_percent', 5, 2);
    $table->string('status'); // safe, warning, critical
    $table->text('message')->nullable();
    $table->timestamp('checked_at')->index();
    $table->timestamps();
});
```

---

## Alerts & Notifications

### Email Alert (Critical Discrepancy)

**Trigger:** System balance > Bank balance

**Subject:** 🚨 CRITICAL: Balance Reconciliation Alert

**Body:**
```
CRITICAL FINANCIAL ALERT

Your system wallet balances exceed the bank account balance.

Bank Account: ₱100,000.00
System Total: ₱105,000.00
Discrepancy:  ₱5,000.00 OVER LIMIT

ACTION REQUIRED:
1. Stop all voucher generation immediately
2. Reconcile system and bank balances
3. Investigate discrepancy source

This is a critical financial control issue that must be 
resolved immediately to prevent disbursement failures.

View details: [Link to /balances]
```

### Slack/Webhook Alert (Optional)

```json
{
  "type": "balance_reconciliation_critical",
  "status": "critical",
  "bank_balance": 10000000,
  "system_balance": 10500000,
  "discrepancy": 500000,
  "formatted_discrepancy": "₱5,000.00",
  "message": "System balance exceeds bank balance",
  "timestamp": "2025-11-14T13:00:00Z"
}
```

---

## Configuration Examples

### Production (High Security)
```bash
BALANCE_RECONCILIATION_ENABLED=true
BALANCE_RECONCILIATION_BUFFER=15  # 15% safety margin
BALANCE_RECONCILIATION_WARNING_THRESHOLD=85
BALANCE_RECONCILIATION_BLOCK_GENERATION=true
BALANCE_RECONCILIATION_ALERT_EMAILS=ceo@company.com,finance@company.com
BALANCE_RECONCILIATION_OVERRIDE=false
```

### Development (Relaxed)
```bash
BALANCE_RECONCILIATION_ENABLED=true
BALANCE_RECONCILIATION_BUFFER=5   # 5% buffer
BALANCE_RECONCILIATION_WARNING_THRESHOLD=95
BALANCE_RECONCILIATION_BLOCK_GENERATION=false  # Allow testing
BALANCE_RECONCILIATION_ALERT_EMAILS=dev@company.com
```

### Emergency Override (⚠️ Temporary Only!)
```bash
BALANCE_RECONCILIATION_OVERRIDE=true  # Disable ALL checks
# OR
BALANCE_RECONCILIATION_ALLOW_OVERGENERATION=true  # Allow exceeding
```

---

## Testing Scenarios

### Test 1: Normal Operation
```
Bank: ₱100,000
System: ₱50,000
Result: 🟢 SAFE - Can generate up to ₱40,000 (with 10% buffer)
```

### Test 2: Warning Level
```
Bank: ₱100,000
System: ₱92,000
Result: 🟡 WARNING - Can generate up to ₱8,000
```

### Test 3: Critical Level
```
Bank: ₱100,000
System: ₱105,000
Result: 🔴 CRITICAL - Cannot generate, must reconcile!
```

### Test 4: Block Generation
```
Bank: ₱100,000
System: ₱80,000
Request: ₱25,000 generation
Result: ❌ BLOCKED - Available only ₱10,000 (with buffer)
```

---

## Implementation Checklist

### Phase 1: Balance Page Warnings
- [ ] Create ReconciliationService
- [ ] Add getTotalSystemBalance() method
- [ ] Add getReconciliationStatus() method
- [ ] Update BalancePageController to include reconciliation data
- [ ] Create ReconciliationStatusCard.vue component
- [ ] Add to Balance Index page
- [ ] Update config/balance.php with reconciliation settings
- [ ] Test with various scenarios

### Phase 2: Pre-Generation Check
- [ ] Add validateGeneration() to VoucherGenerationController
- [ ] Check available balance before generation
- [ ] Show error if insufficient funds
- [ ] Add warning message to generation form
- [ ] Test blocking behavior
- [ ] Add override option for emergencies

### Phase 3: Alerts
- [ ] Create BalanceReconciliationAlert notification
- [ ] Send email when critical discrepancy detected
- [ ] Log all reconciliation checks
- [ ] Add webhook support (optional)

### Phase 4: Documentation & Testing
- [ ] Update .env.example with new settings
- [ ] Document in README
- [ ] Create test suite
- [ ] Add monitoring dashboard

---

## Security Considerations

1. ✅ **Never allow system balance > bank balance** in production
2. ✅ **Log all override attempts** for audit trail
3. ✅ **Require admin confirmation** for emergency overrides
4. ✅ **Alert multiple stakeholders** for critical issues
5. ✅ **Regular reconciliation checks** (at least daily)
6. ✅ **Buffer should never be 0** in production

---

## Migration from Current State

**Step 1:** Deploy with checks disabled
```bash
BALANCE_RECONCILIATION_ENABLED=false
```

**Step 2:** Test in staging with checks enabled
```bash
BALANCE_RECONCILIATION_ENABLED=true
BALANCE_RECONCILIATION_BLOCK_GENERATION=false  # Warn only
```

**Step 3:** Enable blocking in production
```bash
BALANCE_RECONCILIATION_BLOCK_GENERATION=true
```

---

## FAQ

**Q: What if I need to generate vouchers urgently?**  
A: Use the emergency override, but LOG IT:
```bash
BALANCE_RECONCILIATION_OVERRIDE=true
```

**Q: How do I know my current system vs bank balance?**  
A: Visit `/balances` page - it will show reconciliation status.

**Q: Can I set a fixed buffer amount instead of percentage?**  
A: Yes! Use `BALANCE_RECONCILIATION_BUFFER_AMOUNT=5000000` (₱50,000)

**Q: What happens if bank balance check fails?**  
A: System will use last known balance and send alert. Override required.

**Q: Should I set buffer to 0%?**  
A: NO! Always keep 5-15% buffer for pending transactions.

---

**Ready to implement:** Start with Phase 1 (Balance Page Warnings) and Phase 2 (Pre-Generation Check).

Next step: Shall I proceed with implementation?
