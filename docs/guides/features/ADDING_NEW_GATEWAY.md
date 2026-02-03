# How to Add a New Payment Gateway

This guide shows how to add a new payment gateway (e.g., BDO, UnionBank, GCash) to the system with minimal code changes.

## Overview

The system is designed to be **plug-and-play**. After implementing a gateway driver, switching between gateways only requires changing the `.env` configuration:

```bash
# Switch from NetBank to BDO
PAYMENT_GATEWAY=bdo
```

No application code changes needed!

---

## Step 1: Create Omnipay Gateway Driver

Create the Omnipay gateway driver for your payment provider.

**Location:** `packages/payment-gateway/src/Omnipay/Bdo/Gateway.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Bdo;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'BDO';
    }
    
    public function getDefaultParameters(): array
    {
        return [
            'apiKey' => '',
            'apiEndpoint' => '',
            // ... BDO-specific parameters
        ];
    }
    
    // Implement required methods:
    // - disburse()
    // - generateQr()
    // - checkDisbursementStatus() (optional but recommended)
    // - checkBalance() (optional but recommended)
}
```

**Related Files:**
- `src/Omnipay/Bdo/Message/DisburseRequest.php` - Disburse request
- `src/Omnipay/Bdo/Message/DisburseResponse.php` - Disburse response
- `src/Omnipay/Bdo/Message/CheckDisbursementStatusRequest.php` - Status check
- `src/Omnipay/Bdo/Message/CheckDisbursementStatusResponse.php` - Status response
- `src/Omnipay/Bdo/Message/CheckBalanceRequest.php` - Balance check (optional)
- `src/Omnipay/Bdo/Message/CheckBalanceResponse.php` - Balance response (optional)

See `packages/payment-gateway/src/Omnipay/Netbank/` for reference implementation.

---

## Step 2: Add Gateway Configuration

Add BDO configuration to `config/omnipay.php`:

```php
'gateways' => [
    'netbank' => [
        'class' => \LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway::class,
        'options' => [
            'apiKey' => env('NETBANK_API_KEY'),
            // ... NetBank config
        ],
    ],
    
    // NEW: BDO Gateway
    'bdo' => [
        'class' => \LBHurtado\PaymentGateway\Omnipay\Bdo\Gateway::class,
        'options' => [
            'apiKey' => env('BDO_API_KEY'),
            'apiEndpoint' => env('BDO_API_ENDPOINT'),
            'apiSecret' => env('BDO_API_SECRET'),
            // ... BDO-specific config
        ],
    ],
],
```

---

## Step 3: Add Status Mapping

Add BDO status mapping to the `DisbursementStatus` enum.

**File:** `packages/payment-gateway/src/Enums/DisbursementStatus.php`

```php
/**
 * Map BDO-specific status to generic status
 */
private static function fromBdo(string $status): self
{
    return match(strtoupper($status)) {
        'PENDING' => self::PENDING,
        'PROCESSING', 'IN_PROGRESS' => self::PROCESSING,
        'COMPLETED', 'SUCCESS' => self::COMPLETED,
        'FAILED', 'REJECTED' => self::FAILED,
        'CANCELLED' => self::CANCELLED,
        'REFUNDED' => self::REFUNDED,
        default => self::PENDING,
    };
}
```

Update the `fromGateway()` method:

```php
public static function fromGateway(string $gateway, string $status): self
{
    return match(strtolower($gateway)) {
        'netbank' => self::fromNetbank($status),
        'bdo' => self::fromBdo($status),  // NEW
        default => self::fromGeneric($status),
    };
}
```

---

## Step 4 (Optional): Create BDO Data Enricher

If BDO has rich response data you want to extract, create a custom enricher.

**File:** `app/Services/DataEnrichers/BdoDataEnricher.php`

```php
<?php

namespace App\Services\DataEnrichers;

use Illuminate\Support\Facades\Log;

class BdoDataEnricher extends AbstractDataEnricher
{
    public function supports(string $gateway): bool
    {
        return strtolower($gateway) === 'bdo';
    }
    
    public function extract(array &$metadata, array $raw): void
    {
        // Extract BDO-specific fields
        if (isset($raw['reference_id'])) {
            $metadata['disbursement']['reference_number'] = $raw['reference_id'];
        }
        
        if (isset($raw['completed_at'])) {
            $metadata['disbursement']['settled_at'] = $raw['completed_at'];
        }
        
        if (isset($raw['transaction_fee'])) {
            $metadata['disbursement']['fees'] = [
                'amount' => $raw['transaction_fee'],
                'currency' => 'PHP',
            ];
        }
        
        // ... extract more BDO-specific fields
        
        Log::debug('[BdoEnricher] Extracted BDO rich data', [
            'has_reference' => isset($metadata['disbursement']['reference_number']),
            'has_settled_at' => isset($metadata['disbursement']['settled_at']),
        ]);
    }
}
```

**Register in `DataEnricherRegistry`:**

**File:** `app/Services/DataEnrichers/DataEnricherRegistry.php`

```php
public function __construct()
{
    // Gateway-specific enrichers
    $this->register(new NetBankDataEnricher());
    $this->register(new BdoDataEnricher());  // NEW
    
    // Default enricher must be last (fallback)
    $this->register(new DefaultDataEnricher());
}
```

**Note:** If you skip this step, the system will use `DefaultDataEnricher` which just logs that raw data is available.

---

## Step 4.5 (Optional): Implement Balance Check

If your gateway supports balance checking, implement the `checkBalance()` method.

**File:** `packages/payment-gateway/src/Omnipay/Bdo/Message/CheckBalanceRequest.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Bdo\Message;

use Omnipay\Common\Message\AbstractRequest;

class CheckBalanceRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('accountNumber');
        return [];
    }
    
    public function sendData($data)
    {
        $token = $this->getAccessToken();
        
        $httpResponse = $this->httpClient->request(
            'GET',
            $this->getEndpoint(),
            [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ]
        );
        
        $responseData = json_decode($httpResponse->getBody()->getContents(), true);
        
        return new CheckBalanceResponse($this, $responseData);
    }
    
    public function getEndpoint(): string
    {
        $baseUrl = $this->getParameter('balanceEndpoint');
        $accountNumber = $this->getParameter('accountNumber');
        return rtrim($baseUrl, '/') . '/' . $accountNumber;
    }
}
```

**File:** `packages/payment-gateway/src/Omnipay/Bdo/Message/CheckBalanceResponse.php`

```php
<?php

namespace LBHurtado\PaymentGateway\Omnipay\Bdo\Message;

use Omnipay\Common\Message\AbstractResponse;

class CheckBalanceResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        // Adapt to BDO's response structure
        return isset($this->data['balance']);
    }
    
    public function getBalance(): ?int
    {
        // Return balance in centavos (minor units)
        return $this->data['balance'] ?? null;
    }
    
    public function getAvailableBalance(): ?int
    {
        return $this->data['available_balance'] ?? $this->getBalance();
    }
    
    public function getCurrency(): string
    {
        return $this->data['currency'] ?? 'PHP';
    }
    
    public function getAccountNumber(): ?string
    {
        return $this->data['account_number'] ?? null;
    }
    
    public function getAsOf(): ?string
    {
        return $this->data['as_of'] ?? null;
    }
}
```

**Add to Gateway:**

```php
public function checkBalance(array $options = []): CheckBalanceRequest
{
    return $this->createRequest(CheckBalanceRequest::class, $options);
}
```

**Note:** The PaymentGatewayInterface will automatically use this via `checkAccountBalance()` method - no additional wiring needed!

---

## Step 5: Update Environment Variables

Update `.env` to use the new BDO gateway:

```bash
# Payment Gateway Configuration
PAYMENT_GATEWAY=bdo  # Changed from 'netbank'

# BDO API Credentials
BDO_API_KEY=your-bdo-api-key
BDO_API_ENDPOINT=https://api.bdo.com.ph/v1
BDO_API_SECRET=your-bdo-secret
```

---

## Step 6: Test the Integration

### Test Disbursement
```bash
# Generate a voucher and redeem it
# The system will automatically use BDO gateway
```

### Test Status Tracking
```bash
php artisan disbursement:update-status --voucher=YOUR_VOUCHER_CODE
```

### Test Gateway Switching
```bash
# Switch back to NetBank
PAYMENT_GATEWAY=netbank php artisan disbursement:update-status --voucher=E4JE

# Switch to BDO
PAYMENT_GATEWAY=bdo php artisan disbursement:update-status --voucher=YOUR_CODE
```

---

## Architecture Benefits

### ✅ Zero Code Changes
- Change `.env` variable → Gateway switched
- No modifications to `DisburseCash.php`
- No modifications to `DisbursementStatusService.php`
- No modifications to commands or controllers

### ✅ Extensible
- Add new enricher = create new class
- No registry modification needed (just instantiate in constructor)
- Clear separation of concerns

### ✅ Safe
- Unknown gateways work (DefaultEnricher fallback)
- Raw data always preserved for audit (`status_raw` field)
- Comprehensive logging

---

## File Structure

```
packages/payment-gateway/
├── src/
│   ├── Omnipay/
│   │   ├── Netbank/ (existing)
│   │   │   ├── Gateway.php
│   │   │   └── Message/
│   │   │       ├── DisburseRequest.php
│   │   │       ├── DisburseResponse.php
│   │   │       ├── CheckDisbursementStatusRequest.php
│   │   │       └── CheckDisbursementStatusResponse.php
│   │   └── Bdo/ (NEW)
│   │       ├── Gateway.php
│   │       └── Message/
│   │           ├── DisburseRequest.php
│   │           ├── DisburseResponse.php
│   │           ├── CheckDisbursementStatusRequest.php
│   │           └── CheckDisbursementStatusResponse.php
│   ├── Enums/
│   │   └── DisbursementStatus.php (UPDATED - add fromBdo)
│   └── Data/
│       └── Disburse/ (GENERIC - works for all gateways)
│           ├── DisburseInputData.php
│           └── DisburseResponseData.php

app/Services/DataEnrichers/
├── AbstractDataEnricher.php (base class)
├── DataEnricherRegistry.php (registry)
├── NetBankDataEnricher.php (NetBank-specific)
├── BdoDataEnricher.php (NEW - BDO-specific)
└── DefaultDataEnricher.php (fallback)
```

---

## Troubleshooting

### Gateway Not Found
```
Error: Gateway 'bdo' not found
```

**Solution:** Check `config/omnipay.php` - ensure BDO gateway is registered.

### Status Not Updating
```
Status stays 'pending' even after transaction completes
```

**Solution:** 
1. Check if BDO gateway implements `checkDisbursementStatus()`
2. Add `fromBdo()` mapping in `DisbursementStatus` enum
3. Verify `BDO_API_ENDPOINT` is correct

### Missing Enriched Data
```
Raw data available but no fields extracted
```

**Solution:** Create `BdoDataEnricher` (Step 4) or check existing enricher logic.

---

## Summary

Adding a new gateway requires:

1. **Create Omnipay driver** (30-120 min depending on API complexity)
2. **Add configuration** (2 min)
3. **Add status mapping** (5 min)
4. **Create enricher** (10 min - optional)
5. **Update .env** (1 min)

**Total:** ~50-140 minutes depending on API complexity and whether you need custom data extraction.

**After implementation:** Switching gateways = **1 line change** in `.env`! 🚀
