# Balance Monitoring Implementation Plan

**Goal:** Implement comprehensive balance checking and monitoring system across all payment gateways.

**Phases:**
1. Test & Validate Current Implementation (15-30 min)
2. Add to PaymentGatewayInterface (Plug-and-Play) (45 min)
3. Build Full Balance Monitoring System (2-3 hours)

---

## Phase 1: Test & Validate Current Implementation

**Time Estimate:** 15-30 minutes

### Current State

**Already Implemented:**
- ✅ `CheckBalanceRequest` - GET request to `/accounts/{account_number}/details`
- ✅ `CheckBalanceResponse` - Parses response with balance/available_balance/currency
- ✅ `CheckBalanceCommand` - CLI tool (`php artisan omnipay:balance`)
- ✅ `BalanceData` DTO - Generic data object
- ✅ OAuth2 authentication via `HasOAuth2` trait

**Expected Endpoint:**
```
GET https://api.netbank.ph/v1/accounts/{account_number}/details
Authorization: Bearer {token}
```

### Tasks

#### 1.1. Verify Environment Configuration
**File:** `.env`

```bash
# Check these are set
NETBANK_BALANCE_ENDPOINT=https://api.netbank.ph/v1/accounts
NETBANK_CLIENT_ID=your-client-id
NETBANK_CLIENT_SECRET=your-secret
NETBANK_TOKEN_ENDPOINT=https://api.netbank.ph/oauth/token
```

#### 1.2. Test with Real Credentials
```bash
php artisan omnipay:balance --account=YOUR_ACCOUNT_NUMBER
```

**Expected Success Output:**
```
Checking Account Balance
==================================================

Checking balance for account: 113-001-00001-9...

✓ Balance retrieved successfully!

┌──────────────────┬────────────────┐
│ Account          │ 113-001-00001-9│
│ Balance          │ PHP 12,500.00  │
│ Available        │ PHP 12,000.00  │
│ Currency         │ PHP            │
│ As Of            │ 2024-11-14...  │
└──────────────────┴────────────────┘
```

#### 1.3. Document Actual API Response
**If test fails**, capture actual NetBank response and update:

**File:** `packages/payment-gateway/src/Omnipay/Netbank/Message/CheckBalanceResponse.php`

Update response parsing to match actual field names.

**Example NetBank Response (to verify):**
```json
{
  "status": "success",
  "data": {
    "account_number": "113-001-00001-9",
    "account_name": "John Doe",
    "account_type": "SAVINGS",
    "balance": 1250000,
    "available_balance": 1200000,
    "currency": "PHP",
    "status": "ACTIVE",
    "as_of": "2024-11-14T12:00:00Z"
  }
}
```

#### 1.4. Create Test Cases
**File:** `packages/payment-gateway/tests/Feature/CheckBalanceFeatureTest.php`

```php
test('can check balance with valid account', function () {
    $response = $gateway->checkBalance([
        'accountNumber' => '113-001-00001-9',
    ])->send();
    
    expect($response->isSuccessful())->toBeTrue();
    expect($response->getBalance())->toBeInt();
    expect($response->getCurrency())->toBe('PHP');
});

test('handles invalid account gracefully', function () {
    $response = $gateway->checkBalance([
        'accountNumber' => 'INVALID',
    ])->send();
    
    expect($response->isSuccessful())->toBeFalse();
    expect($response->getMessage())->toBeString();
});
```

### Deliverables
- ✅ Confirmed working balance check command
- ✅ Documented actual API response structure
- ✅ Test coverage for balance checking
- ✅ Update documentation with correct endpoint

---

## Phase 2: Add to PaymentGatewayInterface (Plug-and-Play)

**Time Estimate:** 45 minutes

### Goal
Make balance checking gateway-agnostic, just like disbursements.

### Architecture

```
PaymentGatewayInterface
├── checkAccountBalance(string $accountNumber): array
│
├── Implementation: NetbankPaymentGateway (old)
│   └── Uses HTTP facade directly
│
└── Implementation: OmnipayPaymentGateway (new)
    └── Uses Omnipay Gateway
```

### Tasks

#### 2.1. Update PaymentGatewayInterface
**File:** `packages/payment-gateway/src/Contracts/PaymentGatewayInterface.php`

```php
/**
 * Check account balance
 *
 * @param string $accountNumber Account number to check
 * @return array{balance: int, available_balance: int, currency: string, as_of: ?string, raw: array}
 */
public function checkAccountBalance(string $accountNumber): array;
```

#### 2.2. Implement in OmnipayPaymentGateway
**File:** `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`

```php
public function checkAccountBalance(string $accountNumber): array
{
    try {
        $response = $this->gateway->checkBalance([
            'accountNumber' => $accountNumber,
        ])->send();
        
        if (!$response->isSuccessful()) {
            Log::warning('[OmnipayPaymentGateway] Balance check failed', [
                'account' => $accountNumber,
                'error' => $response->getMessage(),
            ]);
            
            return [
                'balance' => 0,
                'available_balance' => 0,
                'currency' => 'PHP',
                'as_of' => null,
                'raw' => [],
            ];
        }
        
        Log::info('[OmnipayPaymentGateway] Balance checked', [
            'account' => $accountNumber,
            'balance' => $response->getBalance(),
        ]);
        
        return [
            'balance' => $response->getBalance(),
            'available_balance' => $response->getAvailableBalance(),
            'currency' => $response->getCurrency(),
            'as_of' => $response->getAsOf(),
            'raw' => $response->getData(),
        ];
        
    } catch (\Throwable $e) {
        Log::error('[OmnipayPaymentGateway] Balance check error', [
            'account' => $accountNumber,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'balance' => 0,
            'available_balance' => 0,
            'currency' => 'PHP',
            'as_of' => null,
            'raw' => [],
        ];
    }
}
```

#### 2.3. Implement in OmnipayBridge
**File:** `packages/payment-gateway/src/Services/OmnipayBridge.php`

```php
public function checkAccountBalance(string $accountNumber): array
{
    // Same implementation as OmnipayPaymentGateway
}
```

#### 2.4. Implement in NetbankPaymentGateway (Old)
**File:** `packages/payment-gateway/src/Gateways/Netbank/NetbankPaymentGateway.php`

```php
use LBHurtado\PaymentGateway\Gateways\Netbank\Traits\CanCheckBalance;

trait CanCheckBalance
{
    public function checkAccountBalance(string $accountNumber): array
    {
        try {
            $endpoint = config('disbursement.server.balance-endpoint');
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->get($endpoint . '/' . $accountNumber . '/details');
            
            if (!$response->successful()) {
                return [
                    'balance' => 0,
                    'available_balance' => 0,
                    'currency' => 'PHP',
                    'as_of' => null,
                    'raw' => [],
                ];
            }
            
            $data = $response->json();
            
            return [
                'balance' => $data['data']['balance'] ?? 0,
                'available_balance' => $data['data']['available_balance'] ?? 0,
                'currency' => $data['data']['currency'] ?? 'PHP',
                'as_of' => $data['data']['as_of'] ?? null,
                'raw' => $data,
            ];
            
        } catch (\Throwable $e) {
            Log::error('[Netbank] Balance check error', [
                'account' => $accountNumber,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'balance' => 0,
                'available_balance' => 0,
                'currency' => 'PHP',
                'as_of' => null,
                'raw' => [],
            ];
        }
    }
}
```

#### 2.5. Update ADDING_NEW_GATEWAY.md
**File:** `docs/ADDING_NEW_GATEWAY.md`

Add section on implementing `checkAccountBalance()` method.

### Deliverables
- ✅ `checkAccountBalance()` in PaymentGatewayInterface
- ✅ Implementations in all gateway classes
- ✅ Works with any gateway (netbank, bdo, etc.)
- ✅ Updated documentation

---

## Phase 3: Build Full Balance Monitoring System

**Time Estimate:** 2-3 hours

### Features
1. Balance tracking (database storage)
2. Balance history & trends
3. Low balance alerts
4. Automatic balance checks (scheduled)
5. Dashboard widget
6. Balance reconciliation

### Architecture

```
┌─────────────────────────────────────────────┐
│  Balance Monitoring System                  │
├─────────────────────────────────────────────┤
│                                             │
│  ┌─────────────────┐  ┌──────────────────┐│
│  │ BalanceService  │  │ BalanceChecker   ││
│  │ - check()       │  │ (Scheduled Job)  ││
│  │ - track()       │  │ - runs hourly    ││
│  │ - alert()       │  │ - updates all    ││
│  └─────────────────┘  └──────────────────┘│
│           │                     │          │
│           ▼                     ▼          │
│  ┌─────────────────────────────────────┐  │
│  │  account_balances table              │  │
│  │  - account_number                    │  │
│  │  - gateway                           │  │
│  │  - balance (int - centavos)          │  │
│  │  - available_balance                 │  │
│  │  - currency                          │  │
│  │  - checked_at                        │  │
│  │  - metadata (json)                   │  │
│  └─────────────────────────────────────┘  │
│           │                                │
│           ▼                                │
│  ┌─────────────────────────────────────┐  │
│  │  balance_alerts table                │  │
│  │  - account_number                    │  │
│  │  - threshold                         │  │
│  │  - alert_type (email/sms/webhook)    │  │
│  │  - recipients                        │  │
│  └─────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

### Tasks

#### 3.1. Database Schema

**Migration:** `create_account_balances_table.php`

```php
Schema::create('account_balances', function (Blueprint $table) {
    $table->id();
    $table->string('account_number')->index();
    $table->string('gateway')->default('netbank');
    $table->bigInteger('balance')->default(0); // centavos
    $table->bigInteger('available_balance')->default(0);
    $table->string('currency', 3)->default('PHP');
    $table->timestamp('checked_at')->index();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->unique(['account_number', 'gateway']);
});

Schema::create('balance_history', function (Blueprint $table) {
    $table->id();
    $table->string('account_number')->index();
    $table->string('gateway');
    $table->bigInteger('balance');
    $table->bigInteger('available_balance');
    $table->string('currency', 3);
    $table->timestamp('recorded_at')->index();
    $table->timestamps();
});

Schema::create('balance_alerts', function (Blueprint $table) {
    $table->id();
    $table->string('account_number')->index();
    $table->string('gateway');
    $table->bigInteger('threshold'); // Alert when balance below this
    $table->string('alert_type'); // email, sms, webhook
    $table->json('recipients'); // emails, phone numbers, webhook URLs
    $table->boolean('enabled')->default(true);
    $table->timestamp('last_triggered_at')->nullable();
    $table->timestamps();
});
```

#### 3.2. Models

**File:** `app/Models/AccountBalance.php`

```php
class AccountBalance extends Model
{
    protected $fillable = [
        'account_number',
        'gateway',
        'balance',
        'available_balance',
        'currency',
        'checked_at',
        'metadata',
    ];
    
    protected $casts = [
        'balance' => 'integer',
        'available_balance' => 'integer',
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function getFormattedBalanceAttribute(): string
    {
        return Money::of($this->balance / 100, $this->currency)
            ->formatTo('en_PH');
    }
    
    public function history()
    {
        return $this->hasMany(BalanceHistory::class, 'account_number', 'account_number');
    }
    
    public function alerts()
    {
        return $this->hasMany(BalanceAlert::class, 'account_number', 'account_number');
    }
    
    public function isLow(): bool
    {
        $alerts = $this->alerts()->where('enabled', true)->get();
        
        foreach ($alerts as $alert) {
            if ($this->balance < $alert->threshold) {
                return true;
            }
        }
        
        return false;
    }
}
```

#### 3.3. BalanceService

**File:** `app/Services/BalanceService.php`

```php
class BalanceService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}
    
    /**
     * Check and update balance for an account
     */
    public function checkAndUpdate(string $accountNumber): AccountBalance
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        
        // Check balance from gateway
        $result = $this->gateway->checkAccountBalance($accountNumber);
        
        // Update or create record
        $balance = AccountBalance::updateOrCreate(
            [
                'account_number' => $accountNumber,
                'gateway' => $gatewayName,
            ],
            [
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
                'currency' => $result['currency'],
                'checked_at' => now(),
                'metadata' => $result['raw'],
            ]
        );
        
        // Record history
        BalanceHistory::create([
            'account_number' => $accountNumber,
            'gateway' => $gatewayName,
            'balance' => $result['balance'],
            'available_balance' => $result['available_balance'],
            'currency' => $result['currency'],
            'recorded_at' => now(),
        ]);
        
        // Check for alerts
        $this->checkAlerts($balance);
        
        Log::info('[BalanceService] Balance updated', [
            'account' => $accountNumber,
            'balance' => $result['balance'],
        ]);
        
        return $balance;
    }
    
    /**
     * Check alerts and trigger if needed
     */
    protected function checkAlerts(AccountBalance $balance): void
    {
        $alerts = $balance->alerts()
            ->where('enabled', true)
            ->where('threshold', '>', $balance->balance)
            ->get();
        
        foreach ($alerts as $alert) {
            // Prevent spam - only alert once per day
            if ($alert->last_triggered_at && $alert->last_triggered_at->isToday()) {
                continue;
            }
            
            $this->triggerAlert($balance, $alert);
            
            $alert->update(['last_triggered_at' => now()]);
        }
    }
    
    /**
     * Trigger an alert
     */
    protected function triggerAlert(AccountBalance $balance, BalanceAlert $alert): void
    {
        $message = "Low balance alert: {$balance->account_number} has {$balance->formatted_balance} (threshold: " . 
                   Money::of($alert->threshold / 100, $balance->currency)->formatTo('en_PH') . ")";
        
        switch ($alert->alert_type) {
            case 'email':
                foreach ($alert->recipients as $email) {
                    Mail::to($email)->send(new LowBalanceAlert($balance, $alert));
                }
                break;
                
            case 'sms':
                foreach ($alert->recipients as $phone) {
                    // Send SMS via notification system
                }
                break;
                
            case 'webhook':
                foreach ($alert->recipients as $url) {
                    Http::post($url, [
                        'type' => 'low_balance_alert',
                        'account' => $balance->account_number,
                        'balance' => $balance->balance,
                        'threshold' => $alert->threshold,
                        'currency' => $balance->currency,
                    ]);
                }
                break;
        }
        
        Log::warning('[BalanceService] Low balance alert triggered', [
            'account' => $balance->account_number,
            'balance' => $balance->balance,
            'threshold' => $alert->threshold,
        ]);
    }
    
    /**
     * Get balance trend for account
     */
    public function getTrend(string $accountNumber, int $days = 7): Collection
    {
        return BalanceHistory::query()
            ->where('account_number', $accountNumber)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at')
            ->get();
    }
}
```

#### 3.4. Scheduled Job

**File:** `app/Console/Commands/CheckAccountBalancesCommand.php`

```php
class CheckAccountBalancesCommand extends Command
{
    protected $signature = 'balances:check
                            {--account= : Specific account to check}
                            {--all : Check all configured accounts}';
    
    protected $description = 'Check account balances and update records';
    
    public function handle(BalanceService $service): int
    {
        $accounts = $this->getAccountsToCheck();
        
        if (empty($accounts)) {
            $this->error('No accounts to check. Use --account or --all option.');
            return self::FAILURE;
        }
        
        $this->info("Checking balances for " . count($accounts) . " account(s)...");
        
        $bar = $this->output->createProgressBar(count($accounts));
        $bar->start();
        
        $success = 0;
        $failed = 0;
        
        foreach ($accounts as $account) {
            try {
                $balance = $service->checkAndUpdate($account);
                $success++;
            } catch (\Throwable $e) {
                $this->error("\nFailed to check {$account}: " . $e->getMessage());
                $failed++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✓ Success: {$success}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }
        
        return self::SUCCESS;
    }
    
    protected function getAccountsToCheck(): array
    {
        if ($account = $this->option('account')) {
            return [$account];
        }
        
        if ($this->option('all')) {
            // Get all accounts from database
            return AccountBalance::pluck('account_number')->unique()->toArray();
        }
        
        // Default: check configured primary account
        $primaryAccount = config('omnipay.test_account') 
            ?? config('disbursement.account_number');
        
        return $primaryAccount ? [$primaryAccount] : [];
    }
}
```

**Register in Kernel:**
```php
protected function schedule(Schedule $schedule): void
{
    // Check balances every hour
    $schedule->command('balances:check --all')
        ->hourly()
        ->withoutOverlapping();
}
```

#### 3.5. Dashboard Widget (Inertia.js)

**File:** `resources/js/components/BalanceWidget.vue`

```vue
<template>
  <Card>
    <CardHeader>
      <CardTitle>Account Balance</CardTitle>
      <CardDescription>{{ account }}</CardDescription>
    </CardHeader>
    <CardContent>
      <div class="space-y-4">
        <div>
          <p class="text-sm text-muted-foreground">Current Balance</p>
          <p class="text-3xl font-bold">{{ formattedBalance }}</p>
        </div>
        
        <div>
          <p class="text-sm text-muted-foreground">Available Balance</p>
          <p class="text-xl font-semibold">{{ formattedAvailable }}</p>
        </div>
        
        <div class="flex items-center gap-2 text-sm">
          <Clock class="h-4 w-4 text-muted-foreground" />
          <span class="text-muted-foreground">
            Updated {{ lastChecked }}
          </span>
        </div>
        
        <BalanceTrendChart :data="trend" class="h-24" />
        
        <Button @click="refresh" :disabled="refreshing" size="sm" variant="outline" class="w-full">
          <RefreshCw :class="{ 'animate-spin': refreshing }" class="mr-2 h-4 w-4" />
          Refresh Balance
        </Button>
      </div>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const props = defineProps<{
  account: string
  balance: number
  availableBalance: number
  currency: string
  checkedAt: string
  trend: Array<{ date: string, balance: number }>
}>()

const refreshing = ref(false)

const formattedBalance = computed(() => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: props.currency
  }).format(props.balance / 100)
})

const formattedAvailable = computed(() => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: props.currency
  }).format(props.availableBalance / 100)
})

const lastChecked = computed(() => {
  return new Date(props.checkedAt).toLocaleString()
})

const refresh = async () => {
  refreshing.value = true
  // Call API to refresh balance
  router.reload({ only: ['balance'] })
  setTimeout(() => refreshing.value = false, 1000)
}
</script>
```

#### 3.6. API Endpoints

**File:** `routes/api.php`

```php
// Balance API endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/balances', [BalanceController::class, 'index']);
    Route::get('/balances/{account}', [BalanceController::class, 'show']);
    Route::post('/balances/{account}/refresh', [BalanceController::class, 'refresh']);
    Route::get('/balances/{account}/history', [BalanceController::class, 'history']);
    
    // Alerts
    Route::get('/balance-alerts', [BalanceAlertController::class, 'index']);
    Route::post('/balance-alerts', [BalanceAlertController::class, 'store']);
    Route::put('/balance-alerts/{alert}', [BalanceAlertController::class, 'update']);
    Route::delete('/balance-alerts/{alert}', [BalanceAlertController::class, 'destroy']);
});
```

### Deliverables
- ✅ Database schema for balance tracking
- ✅ BalanceService for centralized management
- ✅ Scheduled job for automatic checks
- ✅ Alert system (email/SMS/webhook)
- ✅ Dashboard widget
- ✅ API endpoints
- ✅ Balance history & trends
- ✅ Comprehensive documentation

---

## Implementation Timeline

### Day 1: Phase 1 & 2
- Morning: Test current implementation (1 hour)
- Afternoon: Add to PaymentGatewayInterface (1 hour)
- **Deliverable:** Working plug-and-play balance checking

### Day 2: Phase 3 (Part 1)
- Morning: Database schema & models (2 hours)
- Afternoon: BalanceService (2 hours)
- **Deliverable:** Balance tracking system

### Day 3: Phase 3 (Part 2)
- Morning: Scheduled jobs & alerts (2 hours)
- Afternoon: Dashboard & API (2 hours)
- **Deliverable:** Complete monitoring system

---

## Success Criteria

✅ **Phase 1:**
- Balance check command works with real credentials
- API response structure documented
- Test coverage added

✅ **Phase 2:**
- `checkAccountBalance()` in PaymentGatewayInterface
- Works with any gateway (netbank, bdo, etc.)
- Zero code changes to switch gateways

✅ **Phase 3:**
- Balance automatically tracked in database
- Hourly scheduled checks
- Low balance alerts working
- Dashboard shows current balance & trends
- API available for integrations

---

**Ready to proceed with Phase 1?**
