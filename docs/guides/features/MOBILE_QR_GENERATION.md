# Mobile-Based QR Code Generation

This document describes the mobile-based QR code generation system that uses the HasChannels trait for storing user mobile numbers and generates QR codes with properly formatted account numbers for NetBank webhook processing.

## Overview

The system generates QR codes using the user's mobile number in national format (e.g., `09173011987`), which is automatically prefixed with the NetBank client alias (`91500`) by the Omnipay gateway to create the destination account number.

## Architecture

### 1. Mobile Storage (HasChannels Trait)

Mobile numbers are stored using the `HasChannels` trait from the `model-channel` package:

- **Input Format**: Any Philippine mobile format (e.g., `09173011987`, `+639173011987`, `639173011987`)
- **Stored Format**: E.164 without `+` (e.g., `639173011987`)
- **Storage Location**: `channels` table (polymorphic relationship)

```php
// Set mobile number
$user->mobile = '09173011987';
// Automatically converted to: 639173011987

// Get mobile number
$mobile = $user->mobile; // Returns: 639173011987
```

### 2. Account Number Accessor

The `User` model provides an `accountNumber` accessor that converts the E.164 mobile to national format:

```php
// app/Models/User.php
public function getAccountNumberAttribute(): ?string
{
    $mobile = $this->mobile; // 639173011987
    
    if (!$mobile) {
        return null;
    }
    
    // Convert to national format: 09173011987
    if (str_starts_with($mobile, '63') && strlen($mobile) === 12) {
        return '0' . substr($mobile, 2);
    }
    
    return $mobile;
}
```

**Returns**: `09173011987` (national format)

### 3. QR Code Generation

The `GenerateQrCode` action uses the account number for QR generation:

```php
// app/Actions/Api/Wallet/GenerateQrCode.php
$account = $user->accountNumber; // 09173011987

if (!$account) {
    return response()->json([
        'success' => false,
        'message' => 'Mobile number is required to generate QR code.',
    ], 422);
}
```

### 4. Omnipay Gateway Processing

The Omnipay NetBank Gateway adds the client alias prefix:

```php
// packages/payment-gateway/src/Omnipay/Netbank/Message/GenerateQrRequest.php (line 131)
'destination_account' => $clientAlias . $this->getAccountNumber()
// Result: 91500 + 09173011987 = 9150009173011987
```

## Data Flow

### QR Generation Flow

```
1. User Profile
   ├─ Input: 09173011987
   └─ Stored: 639173011987 (E.164 via HasChannels)

2. QR Generation Request
   ├─ $user->accountNumber
   ├─ Converts: 639173011987 → 09173011987
   └─ Returns: 09173011987

3. Omnipay Gateway
   ├─ Receives: 09173011987
   ├─ Adds Alias: 91500 + 09173011987
   └─ Sends to API: 9150009173011987

4. NetBank API
   ├─ Generates QR code
   └─ QR encodes: 9150009173011987
```

### Webhook Processing Flow

```
1. Customer Scans QR
   └─ Pays via GCash/Maya/Bank

2. Webhook Payload Received
   ├─ recipientAccountNumber: 9150009173011987
   ├─ alias: 91500
   └─ referenceCode: 09173011987

3. RecipientAccountNumberData Parser
   ├─ Input: 9150009173011987
   ├─ Strips Alias: 91500
   └─ Returns referenceCode: 09173011987

4. CheckMobile Pipeline
   ├─ Input: 09173011987
   ├─ Converts: 09173011987 → 639173011987
   └─ Calls: User::findByMobile('639173011987')

5. User Found & Credited
   └─ Wallet topup executed
```

## Account Number Format

### User Account Number
- **Length**: 11 digits
- **Format**: National mobile format with leading zero
- **Example**: `09173011987`

### Destination Account Number (in QR)
- **Length**: 16 digits
- **Format**: `{alias}{national_mobile}`
- **Example**: `91500` + `09173011987` = `9150009173011987`

### Reference Code (in Webhook)
- **Length**: 11 digits
- **Format**: National mobile (same as user account number)
- **Example**: `09173011987`

## Configuration

### Environment Variables

```bash
# NetBank Client Alias (prepended to mobile number)
NETBANK_CLIENT_ALIAS=91500

# Payment Gateway
PAYMENT_GATEWAY=netbank
USE_OMNIPAY=true
```

### Config Files

**`config/omnipay.php`**
```php
'gateways' => [
    'netbank' => [
        'options' => [
            'clientAlias' => env('NETBANK_CLIENT_ALIAS'),
            // ...
        ],
    ],
],
```

**`config/disbursement.php`**
```php
'client' => [
    'alias' => env('NETBANK_CLIENT_ALIAS', '91500'),
],
```

## Profile Settings

Users can set their mobile number in **Settings > Profile**:

1. Navigate to Settings > Profile
2. Enter mobile number in any format:
   - `09173011987`
   - `+639173011987`
   - `639173011987`
3. System automatically normalizes to E.164 format
4. Mobile number is required for QR generation

## Webhook Handler

The webhook handler processes deposit confirmations at `POST /api/confirm-deposit`.

### Payload Structure

```json
{
  "alias": "91500",
  "amount": 5500,
  "channel": "INSTAPAY",
  "recipientAccountNumber": "9150009173011987",
  "referenceCode": "09173011987",
  "sender": {
    "accountNumber": "09173011987",
    "institutionCode": "GXCHPHM2XXX",
    "name": "LESTER HURTADO"
  },
  "merchant_details": {
    "merchant_code": "0",
    "merchant_account": "09173011987"
  }
}
```

### Processing Steps

1. **Parse Payload**: Extract `recipientAccountNumber` and `referenceCode`
2. **Find User**: Use `referenceCode` to look up user via `findByMobile()`
3. **Credit Wallet**: Top up user's wallet with the amount
4. **Log Transaction**: Store transaction metadata
5. **Fire Events**: Dispatch `DepositConfirmed` event

## Testing

### Test Webhook Locally

```bash
php artisan test:deposit-confirmation \
  --mobile=09173011987 \
  --amount=100 \
  --show-json
```

Expected output:
```
+--------------------------+--------------------+
| Recipient Account Number | 9150009173011987   |
| Reference Code           | 09173011987        |
+--------------------------+--------------------+

✅ Webhook processed successfully (204 No Content)
```

### End-to-End Test

1. **Set Mobile in Profile**
   ```
   Settings > Profile > Mobile Number: 09173011987
   ```

2. **Generate QR Code**
   ```
   Load Wallet > Generate QR Code
   ```

3. **Verify Account Number**
   ```bash
   tail -f storage/logs/laravel.log | grep "accountNumber"
   # Should show: 9150009173011987
   ```

4. **Simulate Payment**
   ```bash
   php artisan test:deposit-confirmation --mobile=09173011987 --amount=5500
   ```

5. **Check Wallet**
   ```
   User wallet should be credited with ₱55.00
   ```

## Troubleshooting

### Issue: "Mobile number is required"

**Cause**: User hasn't set their mobile number in profile settings.

**Solution**: Navigate to Settings > Profile and enter a valid Philippine mobile number.

### Issue: "User not found" in webhook

**Cause**: Mobile number mismatch between QR generation and webhook lookup.

**Debug**:
1. Check user's stored mobile: `$user->mobile`
2. Check accountNumber: `$user->accountNumber`
3. Verify webhook payload: `referenceCode`
4. Confirm conversion logic in `CheckMobile` pipeline

### Issue: Double-encoded account number

**Symptom**: Account number has 21 digits instead of 16 (e.g., `915009150009173011987`)

**Cause**: Alias being added twice (once in User model, once in Omnipay gateway).

**Solution**: Ensure `User->accountNumber` returns only national mobile format (11 digits), not prefixed with alias.

## Implementation Notes

### Why National Format?

The system uses national format (`09173011987`) instead of E.164 (`639173011987`) because:

1. **NetBank API Requirement**: The QR Ph standard expects national format
2. **Consistent with Webhook**: Webhook returns `referenceCode` in national format
3. **User-Friendly**: Filipinos are familiar with the `09XX` format
4. **Alias Separation**: Clear distinction between alias and mobile number

### Conversion Logic

**E.164 to National**:
```php
// 639173011987 → 09173011987
'0' . substr($mobile, 2)
```

**National to E.164** (for lookup):
```php
// 09173011987 → 639173011987
'63' . substr($mobile, 1)
```

## Related Documentation

- [HasChannels Trait Usage](../packages/model-channel/README.md)
- [Omnipay NetBank Integration](./OMNIPAY_INTEGRATION_PLAN.md)
- [Notification Templates](./NOTIFICATION_TEMPLATES.md)
- [Load Wallet Configuration](./LOAD_WALLET_CONFIGURATION.md)

## Future Enhancements

1. **Sender Contact Tracking**: Auto-create contacts from webhook sender data
2. **Multi-Mobile Support**: Allow users to have multiple mobile numbers
3. **Mobile Verification**: SMS OTP verification for mobile numbers
4. **International Support**: Extend to other countries beyond Philippines
5. **QR Customization**: Allow users to customize QR appearance
