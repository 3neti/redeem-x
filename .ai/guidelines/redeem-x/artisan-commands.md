# Custom Artisan Commands

## Overview
Redeem-X includes several custom Artisan commands for testing and debugging various system components. These commands are essential for development workflows and integration testing.

## Notification Testing

### test:notification
End-to-end testing of the complete notification system including voucher generation, redemption, and multi-channel notifications.

**Basic Usage:**
```bash
php artisan test:notification --fake
```

**All Options:**
```bash
# Test with real email
php artisan test:notification --email=your@email.com

# Test with email and SMS
php artisan test:notification --email=your@email.com --sms=+639171234567

# Test with location data
php artisan test:notification --fake --with-location

# Test with signature upload
php artisan test:notification --fake --with-signature

# Test with selfie upload
php artisan test:notification --fake --with-selfie

# Test with all rich inputs
php artisan test:notification --email=your@email.com --with-location --with-signature --with-selfie

# Test specific combinations
php artisan test:notification --fake --with-signature --with-selfie  # Images only
```

**How It Works:**
1. Generates a test voucher with ₱1 value
2. Automatically disables disbursement (config override)
3. Waits for cash entity creation (avoids race conditions)
4. Redeems the voucher with specified inputs
5. Sends notifications via configured channels (or previews with --fake)
6. Uses templates from `lang/en/notifications.php`
7. Loads test data from `tests/Fixtures/` (location.json, signature.png, selfie.jpg)

**Requirements:**
- Queue worker must be running for non-fake mode: `php artisan queue:listen`
- Notification channels configured in .env

**Use Cases:**
- Verify notification templates render correctly
- Test email/SMS delivery
- Validate input handling (location, images)
- Debug notification pipeline issues
- Preview notifications before sending to real users

## SMS Testing

### test:sms
Direct SMS testing via EngageSpark API, bypassing the notification system.

**Basic Usage:**
```bash
php artisan test:sms 09173011987
```

**With Custom Message:**
```bash
php artisan test:sms 09173011987 "Custom test message"
```

**With Custom Sender ID:**
```bash
php artisan test:sms 09173011987 --sender=TXTCMDR
```

**What It Tests:**
- EngageSpark API credentials
- SMS delivery to Philippine mobile numbers
- Sender ID configuration
- Message encoding and formatting

**Use Cases:**
- Verify EngageSpark integration
- Test SMS delivery before production
- Debug SMS sending issues
- Validate mobile number formatting

## Payment Gateway Testing

### omnipay:disburse
Test disbursement transactions via NetBank payment gateway.

**Syntax:**
```bash
php artisan omnipay:disburse <amount> <mobile> <bic> <rail>
```

**Examples:**
```bash
# Disburse to GCash via INSTAPAY
php artisan omnipay:disburse 100 09173011987 GXCHPHM2XXX INSTAPAY

# Disburse to PayMaya via INSTAPAY
php artisan omnipay:disburse 500 09171234567 MAIMPHM1XXX INSTAPAY

# Disburse to bank via PESONET
php artisan omnipay:disburse 10000 09173011987 BOPIPHMM PESONET
```

**Parameters:**
- `amount` - Amount in PHP (major units, e.g., 100 = ₱100.00)
- `mobile` - Recipient mobile number (Philippine format)
- `bic` - Bank Identifier Code (BIC/SWIFT code)
  - GCash: GXCHPHM2XXX
  - PayMaya: MAIMPHM1XXX
  - Banks: Use appropriate BIC
- `rail` - Settlement rail: INSTAPAY or PESONET

**Key Features:**
- Settlement rail validation (INSTAPAY vs PESONET)
- EMI detection (GCash, PayMaya must use INSTAPAY)
- KYC address workaround for testing
- OAuth2 token caching
- Comprehensive logging to console

**Use Cases:**
- Test real disbursement flow
- Verify gateway credentials
- Validate settlement rail logic
- Debug disbursement errors

### omnipay:qr
Generate QR codes for payment collection.

**Basic Usage:**
```bash
php artisan omnipay:qr 09173011987 100
```

**Save to File:**
```bash
php artisan omnipay:qr 09173011987 100 --save=qr_code.txt
```

**Parameters:**
- First argument: Mobile number
- Second argument: Amount (major units)
- `--save` option: File path to save QR code

**Output:**
- Base64-encoded QR code image
- Payment collection URL
- Optionally saves to file

**Use Cases:**
- Generate payment QR codes
- Test collection flow
- Create QR codes for testing top-up

### omnipay:balance
Check NetBank account balance.

**Usage:**
```bash
php artisan omnipay:balance --account=113-001-00001-9
```

**Requirements:**
- API access with balance inquiry permissions
- Valid account number

**Output:**
- Current account balance
- Currency
- Last update timestamp

**Use Cases:**
- Verify account status
- Monitor available funds
- Debug balance discrepancies

## Top-Up Testing

### test:topup
Test the complete top-up flow including payment initiation, webhook simulation, and wallet credit.

**Basic Usage:**
```bash
# Test with default amount (₱500)
php artisan test:topup
```

**With Custom Amount:**
```bash
php artisan test:topup 1000
```

**With Specific User:**
```bash
php artisan test:topup 500 --user=user@example.com
```

**With Preferred Institution:**
```bash
php artisan test:topup 500 --institution=GCASH
```

**Auto-Simulate Payment:**
```bash
php artisan test:topup 500 --simulate
```

**How It Works:**
1. Uses first user or creates one if none exists
2. Initiates top-up via NetBank Direct Checkout
3. In fake mode (`USE_FAKE=true`), automatically redirects to callback
4. Simulates payment webhook
5. Credits user wallet via Bavix Wallet
6. Validates amount matches expectation
7. Shows before/after balance comparison

**Configuration:**
```bash
# Enable mock mode (no real API calls)
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true

# Or use real NetBank sandbox
NETBANK_DIRECT_CHECKOUT_USE_FAKE=false
NETBANK_DIRECT_CHECKOUT_ACCESS_KEY=your_access_key
NETBANK_DIRECT_CHECKOUT_SECRET_KEY=your_secret_key
NETBANK_DIRECT_CHECKOUT_ENDPOINT=https://api-sandbox.netbank.ph/v1/collect/checkout
```

**Use Cases:**
- Test top-up flow end-to-end
- Verify wallet integration
- Validate webhook handling
- Debug payment issues
- Test with different payment institutions

## Development Workflow Commands

### composer dev
Start all development services concurrently (defined in composer.json):
```bash
composer dev
```

**Services Started:**
- `php artisan serve` - Web server on port 8000
- `php artisan queue:listen --tries=1` - Queue worker
- `php artisan pail --timeout=0` - Log viewer
- `npm run dev` - Vite HMR for frontend

**With SSR:**
```bash
composer dev:ssr
```
Same services plus Inertia SSR server.

### composer test
Run the full test suite:
```bash
composer test
```

Equivalent to:
```bash
php artisan config:clear --ansi
php artisan test
```

## Best Practices

### Testing Workflow
1. Start development services: `composer dev`
2. Test individual components first (SMS, notifications, payments)
3. Test integrated flows (top-up, redemption with disbursement)
4. Verify with real services in staging before production

### Debugging Failed Tests
1. Check logs: `storage/logs/laravel.log`
2. Use Pail for real-time logs: `php artisan pail`
3. Run with verbosity: `php artisan test:notification --fake -vvv`
4. Check queue status if notifications not sending

### Mock vs Real Mode
**Use Mock Mode When:**
- Local development
- Running automated tests
- No API credentials available
- Testing edge cases without cost

**Use Real Mode When:**
- Integration testing
- Staging environment validation
- Production deployment verification
- Testing actual API behavior

## Common Issues

### Notification Not Sending
- Ensure queue worker is running: `php artisan queue:listen`
- Check notification configuration in .env
- Verify `MAIL_*` and SMS credentials

### Disbursement Failing
- Check `DISBURSE_DISABLE` is false
- Verify `USE_OMNIPAY` is set correctly
- Ensure payment gateway credentials configured
- Check recipient mobile number format

### Top-Up Not Crediting
- Verify webhook endpoint is accessible
- Check `NETBANK_DIRECT_CHECKOUT_*` credentials
- Ensure TopUp model created in database
- Verify wallet package installed

### SMS Delivery Issues
- Validate EngageSpark API credentials
- Check mobile number format (+63 prefix)
- Verify sender ID is approved
- Check SMS balance/limits
