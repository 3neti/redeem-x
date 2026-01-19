# NetBank Direct Checkout Requirements

## Overview
NetBank's Direct Checkout API allows users to pay via redirect to their bank/e-wallet apps (GCash, Maya, BPI, etc.) without scanning QR codes. This provides a better UX for desktop users and mobile users on the same device.

## Current Status
⚠️ **Not Yet Implemented** - API testing unsuccessful

## Use Case
When integrated into the `/pay` page, users would have two payment options:
1. **Direct Checkout**: Click button → Redirect to GCash/Maya app → Complete payment
2. **QR Code**: Scan QR code with camera (existing functionality)

## Benefits
- No camera needed (better desktop UX)
- Faster payment flow (direct wallet integration)
- Custom reference number in request → Perfect payment matching in webhook
- Eliminates need for complex deposit classification logic

## API Endpoint
According to NetBank documentation:
```
POST https://api.netbank.ph/v1/collect/checkout/initiate
```

### Request Format
```json
{
  "reference_no": "PAYMENT-ABC123",
  "amount": {
    "value": 100,
    "currency": "PHP"
  },
  "recipient_account": "09173011987",
  "redirect_url": "https://app.test/pay/callback",
  "webhook_url": "https://app.test/webhooks/netbank/payment",
  "preferred_institution": "GCASH"
}
```

### Expected Response
```json
{
  "checkout_url": "https://checkout.netbank.ph/pay/ABC123",
  "reference_no": "PAYMENT-ABC123",
  "qr_code": "data:image/png;base64,..."
}
```

## Testing Results (2026-01-19)

### Sandbox Environment
- **Endpoint**: `https://api-sandbox.netbank.ph/v1/collect/checkout`
- **Result**: `404 Not Found`
- **Conclusion**: Sandbox may not have Direct Checkout feature

### Production Environment
- **Endpoint**: `https://api.netbank.ph/v1/collect/checkout`
- **Result**: `401 Unauthenticated`
- **Credentials Tested**: 
  - OAuth Client ID/Secret (Basic Auth)
  - Access Key/Secret Key pairs
- **Conclusion**: Credentials may not be enabled for Direct Checkout feature

### Attempted Authentication Methods
1. **OAuth Client Credentials** (`NETBANK_CLIENT_ID` + `NETBANK_CLIENT_SECRET`)
   - Basic Auth: ❌ 401 Unauthenticated
   - Token endpoint: ❌ 404 Not Found
2. **Access Key Headers** (`X-Access-Key` + `X-Secret-Key`)
   - ❌ 401 Unauthenticated

## Requirements for Implementation

### From NetBank
1. **Confirm API Availability**
   - Is Direct Checkout available in sandbox?
   - Is Direct Checkout available in production?
   - What is the exact endpoint structure?

2. **Enable Feature on Account**
   - Request Direct Checkout feature activation
   - Confirm credentials have necessary permissions

3. **Provide Correct Credentials**
   - What type of authentication is required?
   - Are separate credentials needed for Direct Checkout vs other APIs?
   - Provide working credentials for testing

4. **Documentation Clarification**
   - Confirm exact endpoint path (`/initiate` suffix or not?)
   - Provide example requests/responses
   - Clarify webhook payload structure

### Technical Requirements
- Valid NetBank merchant account
- Direct Checkout feature enabled
- Proper credentials configured in `.env`:
  ```bash
  NETBANK_DIRECT_CHECKOUT_ACCESS_KEY=<actual_key>
  NETBANK_DIRECT_CHECKOUT_SECRET_KEY=<actual_secret>
  NETBANK_DIRECT_CHECKOUT_ENDPOINT=<confirmed_endpoint>
  ```

## Testing Command
A test command has been created for future testing:

```bash
php artisan test:direct-checkout [amount] [voucher_code] [institution]

# Example
php artisan test:direct-checkout 100 ABCD-EFGI GCASH
```

**Location**: `app/Console/Commands/TestDirectCheckout.php`

This command will:
- Test authentication (OAuth or Access Key)
- Call the Direct Checkout API
- Display the checkout URL
- Offer to open the URL in browser

## Alternative Solution
Until Direct Checkout is available, the existing **QR Ph + Webhook SMS Confirmation** approach works perfectly:

1. User generates payment QR code
2. User scans and pays via GCash/Maya
3. NetBank webhook fires with payment confirmation
4. System sends SMS with one-click confirmation link
5. User clicks link to confirm payment

This achieves the same goal without dependency on Direct Checkout API.

## Next Steps
1. **Contact NetBank Support** with this document
2. **Request Direct Checkout Access** for account
3. **Get Proper Credentials** for testing
4. **Retest** using `test:direct-checkout` command
5. **Update Documentation** with working configuration

## References
- NetBank API Docs: https://virtual.netbank.ph/docs#operation/Direct-Checkout_InitiateCollection
- Test Command: `app/Console/Commands/TestDirectCheckout.php`
- Related Plan: `docs/PAYMENT_CONFIRMATION_UX_IMPROVEMENTS.md` (if plan was saved)
