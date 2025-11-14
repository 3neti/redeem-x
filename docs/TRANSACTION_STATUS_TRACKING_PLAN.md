# Transaction Status Tracking Implementation Plan

## Problem Statement

Currently, disbursement transactions are stored with a "Pending" status from NetBank. These statuses need to be updated to "Settled" (or other final states) via:
1. **Webhook (Push)**: Bank sends status updates to our endpoint
2. **Polling (Pull)**: Our system actively checks transaction status via API

The current status values ("Pending", "Settled") are NetBank-specific and should be normalized across all gateways.

## Current State

### Existing Infrastructure
✅ **Interface Method**: `PaymentGatewayInterface::confirmDisbursement($operationId)` already exists
✅ **Webhook Controller**: `ConfirmDisbursementController` handles incoming webhooks
✅ **Event**: `DisbursementConfirmed` dispatched when status updated
✅ **Storage**: Transaction IDs stored in `voucher.metadata.disbursement.transaction_id`

### Missing Components
❌ No polling/cron mechanism to actively check status
❌ No generic status enum (currently using NetBank-specific statuses)
❌ No Omnipay implementation for status checking
❌ No artisan command for manual/scheduled status checks
❌ No service layer to orchestrate status updates

---

## Phase 1: Normalize Transaction Statuses (Gateway-Agnostic)

### 1.1 Create Generic Status Enum

**File**: `packages/payment-gateway/src/Enums/DisbursementStatus.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Enums;

enum DisbursementStatus: string
{
    case PENDING = 'pending';           // Initial state after disbursement
    case PROCESSING = 'processing';     // In transit (some gateways)
    case COMPLETED = 'completed';       // Successfully delivered
    case FAILED = 'failed';             // Permanent failure
    case CANCELLED = 'cancelled';       // User/admin cancelled
    case REFUNDED = 'refunded';         // Money returned
    
    /**
     * Map gateway-specific status to generic status
     */
    public static function fromGateway(string $gateway, string $status): self
    {
        return match($gateway) {
            'netbank' => self::fromNetbank($status),
            'icash' => self::fromICash($status),
            'paypal' => self::fromPayPal($status),
            'stripe' => self::fromStripe($status),
            default => self::fromGeneric($status),
        };
    }
    
    private static function fromNetbank(string $status): self
    {
        return match(strtoupper($status)) {
            'PENDING' => self::PENDING,
            'SETTLED' => self::COMPLETED,
            'FAILED' => self::FAILED,
            'CANCELLED' => self::CANCELLED,
            default => self::PENDING,
        };
    }
    
    private static function fromICash(string $status): self
    {
        // TODO: Map iCash statuses
        return self::fromGeneric($status);
    }
    
    private static function fromPayPal(string $status): self
    {
        return match(strtoupper($status)) {
            'PENDING', 'CREATED' => self::PENDING,
            'SUCCESS', 'COMPLETED' => self::COMPLETED,
            'FAILED', 'DENIED' => self::FAILED,
            'CANCELLED' => self::CANCELLED,
            'REFUNDED' => self::REFUNDED,
            default => self::PENDING,
        };
    }
    
    private static function fromStripe(string $status): self
    {
        return match($status) {
            'pending' => self::PENDING,
            'in_transit' => self::PROCESSING,
            'paid' => self::COMPLETED,
            'failed' => self::FAILED,
            'canceled' => self::CANCELLED,
            default => self::PENDING,
        };
    }
    
    private static function fromGeneric(string $status): self
    {
        return match(strtolower($status)) {
            'pending' => self::PENDING,
            'processing', 'in_transit' => self::PROCESSING,
            'completed', 'success', 'settled' => self::COMPLETED,
            'failed', 'error' => self::FAILED,
            'cancelled', 'canceled' => self::CANCELLED,
            'refunded' => self::REFUNDED,
            default => self::PENDING,
        };
    }
    
    /**
     * Check if status is final (no more updates expected)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
        ]);
    }
    
    /**
     * Get badge variant for UI
     */
    public function getBadgeVariant(): string
    {
        return match($this) {
            self::PENDING => 'secondary',
            self::PROCESSING => 'default',
            self::COMPLETED => 'success',
            self::FAILED => 'destructive',
            self::CANCELLED => 'outline',
            self::REFUNDED => 'default',
        };
    }
}
```

### 1.2 Update DisbursementData DTO

**File**: `packages/voucher/src/Data/DisbursementData.php`

```php
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;

public function __construct(
    public string $gateway,
    public string $transaction_id,
    public DisbursementStatus $status,  // ← Changed from string to enum
    // ... rest of fields
) {}
```

### 1.3 Update DisburseCash Pipeline

Store normalized status from the start:

```php
'status' => DisbursementStatus::fromGateway('netbank', $response->status)->value,
```

---

## Phase 2: Extend Gateway Interface for Status Checking

### 2.1 Add Method to Interface

**File**: `packages/payment-gateway/src/Contracts/PaymentGatewayInterface.php`

```php
/**
 * Check the status of a disbursement transaction
 *
 * @param string $transactionId Gateway transaction ID
 * @return array{status: string, raw: array} Normalized status + raw response
 */
public function checkDisbursementStatus(string $transactionId): array;
```

### 2.2 Implement in NetBank Gateway (Old)

**File**: `packages/payment-gateway/src/Gateways/Netbank/Traits/CanDisburse.php`

```php
public function checkDisbursementStatus(string $transactionId): array
{
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
        ])->get(config('disbursement.server.status-endpoint') . '/' . $transactionId);
        
        if (!$response->successful()) {
            Log::warning('[Netbank] Status check failed', [
                'transaction_id' => $transactionId,
                'response' => $response->body()
            ]);
            return ['status' => 'pending', 'raw' => []];
        }
        
        $data = $response->json();
        $rawStatus = $data['status'] ?? 'PENDING';
        $normalized = DisbursementStatus::fromGateway('netbank', $rawStatus);
        
        return [
            'status' => $normalized->value,
            'raw' => $data,
        ];
    } catch (\Throwable $e) {
        Log::error('[Netbank] Status check error', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage()
        ]);
        return ['status' => 'pending', 'raw' => []];
    }
}
```

### 2.3 Implement in Omnipay Gateway

**Step 1**: Create Omnipay Request

**File**: `packages/payment-gateway/src/Omnipay/Netbank/Message/CheckDisbursementStatusRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

class CheckDisbursementStatusRequest extends AbstractNetbankRequest
{
    public function getData()
    {
        $this->validate('transactionId');
        
        return [
            'transaction_id' => $this->getTransactionId(),
        ];
    }
    
    public function sendData($data)
    {
        $endpoint = $this->getStatusEndpoint() . '/' . $data['transaction_id'];
        
        try {
            $httpResponse = $this->httpClient->request(
                'GET',
                $endpoint,
                $this->getHeaders()
            );
            
            $json = json_decode($httpResponse->getBody()->getContents(), true);
            
            return $this->response = new CheckDisbursementStatusResponse(
                $this,
                $json
            );
        } catch (\Exception $e) {
            return $this->response = new CheckDisbursementStatusResponse(
                $this,
                ['error' => $e->getMessage(), 'status' => 'PENDING']
            );
        }
    }
    
    public function getTransactionId()
    {
        return $this->getParameter('transactionId');
    }
    
    public function setTransactionId($value)
    {
        return $this->setParameter('transactionId', $value);
    }
    
    public function getStatusEndpoint()
    {
        return $this->getParameter('statusEndpoint');
    }
}
```

**Step 2**: Create Response

**File**: `packages/payment-gateway/src/Omnipay/Netbank/Message/CheckDisbursementStatusResponse.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractResponse;

class CheckDisbursementStatusResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return isset($this->data['status']);
    }
    
    public function getStatus()
    {
        return $this->data['status'] ?? 'PENDING';
    }
    
    public function getTransactionId()
    {
        return $this->data['transaction_id'] ?? null;
    }
    
    public function getRawData(): array
    {
        return $this->data;
    }
}
```

**Step 3**: Add to Gateway

**File**: `packages/payment-gateway/src/Omnipay/Netbank/Gateway.php`

```php
public function checkDisbursementStatus(array $options = []): CheckDisbursementStatusRequest
{
    return $this->createRequest(CheckDisbursementStatusRequest::class, $options);
}

public function getStatusEndpoint()
{
    return $this->getParameter('statusEndpoint');
}

public function setStatusEndpoint($value)
{
    return $this->setParameter('statusEndpoint', $value);
}
```

**Step 4**: Wire in OmnipayBridge

**File**: `packages/payment-gateway/src/Services/OmnipayBridge.php`

```php
public function checkDisbursementStatus(string $transactionId): array
{
    try {
        $response = $this->gateway
            ->checkDisbursementStatus([
                'transactionId' => $transactionId,
            ])
            ->send();
        
        if (!$response->isSuccessful()) {
            return ['status' => 'pending', 'raw' => []];
        }
        
        $rawStatus = $response->getStatus();
        $normalized = DisbursementStatus::fromGateway('netbank', $rawStatus);
        
        return [
            'status' => $normalized->value,
            'raw' => $response->getRawData(),
        ];
    } catch (\Throwable $e) {
        Log::error('[OmnipayBridge] Status check failed', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage()
        ]);
        return ['status' => 'pending', 'raw' => []];
    }
}
```

**Step 5**: Implement in OmnipayPaymentGateway

**File**: `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`

```php
public function checkDisbursementStatus(string $transactionId): array
{
    return $this->bridge->checkDisbursementStatus($transactionId);
}
```

---

## Phase 3: Create Status Update Service

### 3.1 Create Service

**File**: `app/Services/DisbursementStatusService.php`

```php
<?php

namespace App\Services;

use App\Models\Voucher;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\Voucher\Data\DisbursementData;
use Illuminate\Support\Facades\Log;

class DisbursementStatusService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}
    
    /**
     * Update status for a single voucher
     */
    public function updateVoucherStatus(Voucher $voucher): bool
    {
        $disbursement = DisbursementData::fromMetadata($voucher->metadata);
        
        if (!$disbursement) {
            Log::warning('[StatusService] No disbursement data', ['voucher' => $voucher->code]);
            return false;
        }
        
        // Skip if already in final state
        $currentStatus = DisbursementStatus::from($disbursement->status);
        if ($currentStatus->isFinal()) {
            Log::debug('[StatusService] Already final', [
                'voucher' => $voucher->code,
                'status' => $currentStatus->value
            ]);
            return false;
        }
        
        // Check status with gateway
        $result = $this->gateway->checkDisbursementStatus($disbursement->transaction_id);
        $newStatus = $result['status'];
        
        // No change
        if ($newStatus === $currentStatus->value) {
            return false;
        }
        
        // Update voucher metadata
        $metadata = $voucher->metadata;
        $metadata['disbursement']['status'] = $newStatus;
        $metadata['disbursement']['status_updated_at'] = now()->toIso8601String();
        $metadata['disbursement']['status_raw'] = $result['raw'];
        
        $voucher->metadata = $metadata;
        $voucher->save();
        
        Log::info('[StatusService] Status updated', [
            'voucher' => $voucher->code,
            'old_status' => $currentStatus->value,
            'new_status' => $newStatus,
        ]);
        
        // Dispatch event if completed
        if (DisbursementStatus::from($newStatus)->isFinal()) {
            event(new \LBHurtado\PaymentGateway\Events\DisbursementConfirmed($voucher));
        }
        
        return true;
    }
    
    /**
     * Update status for multiple vouchers
     */
    public function updatePendingVouchers(int $limit = 100): int
    {
        $updated = 0;
        
        // Get vouchers with pending disbursements
        $vouchers = Voucher::query()
            ->whereNotNull('redeemed_at')
            ->whereNotNull('metadata->disbursement')
            ->whereIn('metadata->disbursement->status', ['pending', 'processing'])
            ->limit($limit)
            ->get();
        
        foreach ($vouchers as $voucher) {
            if ($this->updateVoucherStatus($voucher)) {
                $updated++;
            }
        }
        
        return $updated;
    }
}
```

### 3.2 Register Service Provider

**File**: `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    $this->app->singleton(DisbursementStatusService::class, function ($app) {
        return new DisbursementStatusService(
            $app->make(PaymentGatewayInterface::class)
        );
    });
}
```

---

## Phase 4: Create Artisan Command

### 4.1 Create Command

**File**: `app/Console/Commands/UpdateDisbursementStatusCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\DisbursementStatusService;
use Illuminate\Console\Command;

class UpdateDisbursementStatusCommand extends Command
{
    protected $signature = 'disbursement:update-status 
                            {--voucher= : Update specific voucher by code}
                            {--limit=100 : Max vouchers to check}';
    
    protected $description = 'Check and update pending disbursement statuses';
    
    public function __construct(
        protected DisbursementStatusService $service
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $voucherCode = $this->option('voucher');
        
        if ($voucherCode) {
            return $this->updateSingle($voucherCode);
        }
        
        return $this->updateBatch();
    }
    
    protected function updateSingle(string $code): int
    {
        $this->info("Checking status for voucher: {$code}");
        
        $voucher = \App\Models\Voucher::where('code', $code)->first();
        
        if (!$voucher) {
            $this->error("Voucher not found: {$code}");
            return 1;
        }
        
        $updated = $this->service->updateVoucherStatus($voucher);
        
        if ($updated) {
            $this->info("✓ Status updated");
        } else {
            $this->warn("No update needed");
        }
        
        return 0;
    }
    
    protected function updateBatch(): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Checking up to {$limit} pending disbursements...");
        
        $updated = $this->service->updatePendingVouchers($limit);
        
        $this->info("✓ Updated {$updated} voucher(s)");
        
        return 0;
    }
}
```

### 4.2 Add to Schedule

**File**: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // Check pending disbursements every 5 minutes
    $schedule->command('disbursement:update-status --limit=50')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
}
```

---

## Phase 5: Configuration

### 5.1 Add NetBank Status Endpoint

**File**: `config/disbursement.php`

```php
'server' => [
    'end-point' => env('DISBURSEMENT_SERVER_END_POINT', 'https://uat.qrph.payment.ne-one.net/payment-gateway/api/v1/payments/send-money'),
    'status-endpoint' => env('DISBURSEMENT_SERVER_STATUS_ENDPOINT', 'https://uat.qrph.payment.ne-one.net/payment-gateway/api/v1/payments/status'),
    // ... rest
],
```

### 5.2 Update Omnipay Config

**File**: `config/omnipay.php`

```php
'gateways' => [
    'netbank' => [
        // ... existing
        'statusEndpoint' => env('DISBURSEMENT_SERVER_STATUS_ENDPOINT'),
    ],
],
```

---

## Phase 6: Update UI

### 6.1 Update Transaction Table

Display normalized statuses with proper badges.

**File**: `resources/js/pages/Transactions/Index.vue`

```vue
<Badge :variant="getStatusVariant(disbursement.status)">
    {{ formatStatus(disbursement.status) }}
</Badge>
```

Add helper:
```typescript
const formatStatus = (status: string) => {
    return status.charAt(0).toUpperCase() + status.slice(1);
};
```

### 6.2 Add Manual Refresh Button

In Transaction Detail Modal, add button to manually trigger status check:

```vue
<Button @click="refreshStatus" :disabled="refreshing">
    <RefreshCw :class="{ 'animate-spin': refreshing }" class="h-4 w-4 mr-2" />
    Refresh Status
</Button>
```

---

## Testing Strategy

### Unit Tests
1. `DisbursementStatusTest` - Test enum mappings
2. `DisbursementStatusServiceTest` - Test service logic
3. `CheckDisbursementStatusRequestTest` - Test Omnipay request

### Feature Tests
1. Test webhook updates status correctly
2. Test artisan command updates pending vouchers
3. Test service skips final statuses
4. Test status normalized across gateways

### Manual Testing
```bash
# Generate & redeem test voucher
php artisan test:notification --fake

# Check status manually
php artisan disbursement:update-status --voucher=QVAL

# Check batch
php artisan disbursement:update-status --limit=10

# Simulate webhook (use Postman/curl)
curl -X POST http://redeem-x.test/api/confirm-disbursement \
  -H "Content-Type: application/json" \
  -d '{"operationId": "260741510"}'
```

---

## Rollout Plan

### Step 1: Status Enum (Safe)
- Create `DisbursementStatus` enum
- Update DTO to use enum
- Deploy + test - **no breaking changes**

### Step 2: Gateway Methods (Safe)
- Add `checkDisbursementStatus()` to interface
- Implement in NetBank + Omnipay
- Test manually via Tinker - **no breaking changes**

### Step 3: Service + Command (Safe)
- Create `DisbursementStatusService`
- Create artisan command
- Test command manually - **no automation yet**

### Step 4: Scheduler (Gradual)
- Add to cron with `--limit=10` initially
- Monitor logs for 24-48 hours
- Increase limit gradually

### Step 5: UI Enhancements (Optional)
- Add refresh button
- Show status history
- Add filters by status

---

## Environment Variables

Add to `.env`:
```bash
# NetBank Status Endpoint
NETBANK_STATUS_ENDPOINT=https://api.netbank.ph/v1/transactions

# Or for UAT/Sandbox:
# NETBANK_STATUS_ENDPOINT=https://api-sandbox.netbank.ph/v1/transactions
```

**Note:** The transaction ID is appended to this base URL: `{NETBANK_STATUS_ENDPOINT}/{transaction_id}`

---

## References

- x-change: `confirmDisbursement()` pattern (webhook-based)
- x-change: `ConfirmDisbursementController` for webhooks
- Current redeem-x: Omnipay gateway architecture
- NetBank API docs: Status endpoint (TBD - need confirmation)

---

## Open Questions

1. ✅ Does NetBank provide a status endpoint? (Need to verify URL)
2. ✅ What are all possible NetBank status values? (PENDING, SETTLED, FAILED, ?)
3. ✅ Should we rate-limit status checks? (Yes - via cron limit)
4. ✅ How long to keep polling pending transactions? (Until final status or 7 days)
5. ✅ Should we store status history? (Optional - can use `status_updated_at`)

---

## Success Criteria

- [x] Statuses normalized across all gateways
- [ ] Webhook updates work correctly (already implemented in x-change pattern)
- [x] Polling command successfully updates statuses
- [ ] UI displays correct status with proper badges (existing UI already supports)
- [ ] Cron job runs without errors (to be scheduled)
- [ ] No performance degradation (to be monitored)
- [x] Comprehensive logging for debugging
