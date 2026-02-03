# Console Commands Reference

Comprehensive guide to all Artisan console commands in the redeem-x project.

## Quick Reference

**Most used commands:**
- `test:notification` - Test complete redemption notification flow
- `test:sms` - Send test SMS
- `test:topup` - Test wallet top-up
- `feature:manage` - Enable/disable features for users
- `omnipay:disburse` - Test disbursement (⚠️ real transaction)

See `WARP.md` for inline examples.

## Test Commands

Commands prefixed with `test:` for end-to-end testing.

### test:notification
**Purpose:** Test complete voucher generation, redemption, and notification flow

**Usage:**
```bash
# Preview mode (no actual sending)
php artisan test:notification --fake

# Send real notifications
php artisan test:notification --email=user@example.com --sms=+639171234567

# Test with rich inputs
php artisan test:notification --email=user@example.com --with-location --with-signature --with-selfie
```

**Options:**
- `--fake` - Preview mode (don't send notifications)
- `--email=ADDRESS` - Send email notification
- `--sms=NUMBER` - Send SMS notification
- `--with-location` - Include GPS coordinates
- `--with-signature` - Include digital signature
- `--with-selfie` - Include selfie image

**How it works:**
1. Generates ₱1 test voucher
2. Waits for cash entity creation
3. Redeems voucher
4. Sends/previews email and SMS notifications
5. Uses test data from `tests/Fixtures/`

**Configuration:** Automatically disables disbursement during testing

### test:sms
**Purpose:** Test SMS sending directly via EngageSpark (bypasses notification system)

**Usage:**
```bash
# Send default test message
php artisan test:sms 09173011987

# Custom message
php artisan test:sms 09173011987 "Your voucher code is ABC123"

# Custom sender ID
php artisan test:sms 09173011987 --sender=TXTCMDR
```

**Options:**
- `--sender=ID` - Sender ID (default: configured in .env)

**Use case:** Quick SMS gateway testing without voucher workflow

### test:sms-balance
**Purpose:** Test SMS BALANCE command functionality

**Usage:**
```bash
# Test BALANCE command
php artisan test:sms-balance

# Test with specific system
php artisan test:sms-balance --system
```

**How it works:**
1. Creates test user with known balance
2. Simulates SMS command: "BALANCE"
3. Verifies response format and accuracy

### test:sms-redeem
**Purpose:** Test SMS voucher redemption flow

**Usage:**
```bash
php artisan test:sms-redeem VOUCHER-CODE
```

**How it works:**
1. Verifies voucher exists and is unredeemed
2. Simulates SMS redemption via mobile number
3. Validates redemption response

### test:sms-router
**Purpose:** Test SMS command routing locally without actual SMS gateway

**Usage:**
```bash
# Test GENERATE command
php artisan test:sms-router "GENERATE 100" --mobile=09173011987

# Test REDEEM command
php artisan test:sms-router "REDEEM ABC123" --mobile=09171234567

# Test BALANCE command
php artisan test:sms-router "BALANCE" --mobile=09173011987
```

**Options:**
- `--mobile=NUMBER` - Mobile number to simulate SMS from

**Use case:** Offline testing of SMS command parsing and routing logic

### test:topup
**Purpose:** Test complete wallet top-up flow (initiate → simulate payment → credit wallet)

**Usage:**
```bash
# Test with default amount (₱500)
php artisan test:topup

# Custom amount
php artisan test:topup 1000

# Specific user
php artisan test:topup 500 --user=user@example.com

# Preferred institution
php artisan test:topup 500 --institution=GCASH

# Auto-simulate payment (skip manual step)
php artisan test:topup 500 --simulate
```

**Options:**
- `--user=EMAIL` - Test with specific user
- `--institution=CODE` - Preferred payment institution (GCASH, MAYA, BDO, etc.)
- `--simulate` - Automatically simulate payment (don't wait for manual confirmation)

**Flow:**
1. Initiates top-up via NetBank Direct Checkout
2. In fake mode: Auto-redirects to callback
3. Simulates payment webhook
4. Credits user wallet
5. Shows before/after balance

**Configuration:**
```bash
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true  # Mock mode for testing
```

### test:direct-checkout
**Purpose:** Test NetBank Direct Checkout API integration

**Usage:**
```bash
php artisan test:direct-checkout 100
```

**Use case:** Low-level testing of Direct Checkout API without wallet integration

### test:disbursement-failure
**Purpose:** Test disbursement failure alerting system by simulating failures

**Usage:**
```bash
# Test timeout error
php artisan test:disbursement-failure --type=timeout

# Test invalid account error
php artisan test:disbursement-failure --type=invalid_account

# Test insufficient funds error
php artisan test:disbursement-failure --type=insufficient_funds
```

**How it works:**
1. Creates test voucher and redemption
2. Simulates specified failure type
3. Verifies email alert sent to configured addresses
4. Checks database audit trail

**Configuration:**
```bash
DISBURSEMENT_ALERT_ENABLED=true
DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com
```

### test:vouchers
**Purpose:** Generate test vouchers with different input combinations

**Usage:**
```bash
# Full scenario (all inputs)
php artisan test:vouchers --scenario=full

# Minimal scenario
php artisan test:vouchers --scenario=minimal

# Custom count
php artisan test:vouchers --count=10
```

**Scenarios:**
- `full` - All input fields enabled (location, selfie, signature, KYC, etc.)
- `minimal` - Basic fields only (mobile, amount)
- `custom` - Interactive selection of fields

### test:voucher-traits
**Purpose:** Test voucher external metadata, timing, and validation traits

**Usage:**
```bash
php artisan test:voucher-traits
```

**Tests:**
- External metadata storage and retrieval
- Voucher timing (expiration, activation)
- Validation rules (min/max amounts, etc.)

### test:location-handler
**Purpose:** Test Location Handler with real credentials

**Usage:**
```bash
php artisan test:location-handler
```

**Use case:** Verify GPS capture and reverse geocoding integration

### test:otp
**Purpose:** Test OTP request and verification via txtcmdr API

**Usage:**
```bash
php artisan test:otp
```

**Flow:**
1. Requests OTP via API
2. Simulates user entering OTP
3. Verifies OTP validation

### test:deposit-confirmation
**Purpose:** Test deposit confirmation webhook by simulating POST to `/api/confirm-deposit`

**Usage:**
```bash
php artisan test:deposit-confirmation
```

**Use case:** Test webhook endpoint without external payment gateway

### test:sender-tracking
**Purpose:** Test sender contact tracking by creating contact and recording deposit

**Usage:**
```bash
php artisan test:sender-tracking
```

**Use case:** Verify contact creation and deposit attribution

### test:voucher-generation-notification
**Purpose:** Test voucher generation SMS notification without actually generating vouchers

**Usage:**
```bash
php artisan test:voucher-generation-notification
```

**Use case:** Test notification templates and SMS gateway without creating real vouchers

## Payment Gateway Commands

Commands for testing payment gateway integration.

### omnipay:disburse
⚠️ **WARNING: REAL TRANSACTION - SENDS ACTUAL MONEY**

**Purpose:** Test disbursement to recipient via NetBank

**Usage:**
```bash
php artisan omnipay:disburse <amount> <mobile> <bank_code> <rail>

# Example: Send ₱100 to GCash via InstaPay
php artisan omnipay:disburse 100 09173011987 GXCHPHM2XXX INSTAPAY

# Send ₱55000 to BPI via PESONet
php artisan omnipay:disburse 55000 09171234567 BOPIPHMM PESONET
```

**Parameters:**
- `amount` - Amount in PHP (₱)
- `mobile` - Recipient mobile number
- `bank_code` - SWIFT/BIC code (e.g., GXCHPHM2XXX for GCash)
- `rail` - Settlement rail: INSTAPAY or PESONET

**Validation:**
- INSTAPAY: Max ₱50,000
- PESONET: Max ₱1,000,000
- GCash/Maya: Must use INSTAPAY
- KYC address workaround for testing

### omnipay:balance
**Purpose:** Check account balance from payment gateway

**Usage:**
```bash
# Check default account
php artisan omnipay:balance

# Check specific account
php artisan omnipay:balance --account=113-001-00001-9
```

**Options:**
- `--account=NUMBER` - Account number to check

**Requirements:** API credentials with balance inquiry access

### omnipay:qr
**Purpose:** Generate QR code for receiving payments

**Usage:**
```bash
# Generate QR for ₱100 payment
php artisan omnipay:qr 09173011987 100

# Save to file
php artisan omnipay:qr 09173011987 100 --save=qr_code.txt
```

**Options:**
- `--save=PATH` - Save QR code to file

### omnipay:test-gcash-memo
⚠️ **WARNING: REAL TRANSACTIONS**

**Purpose:** Test GCash memo visibility via NetBank InstaPay

**Usage:**
```bash
php artisan omnipay:test-gcash-memo
```

**Use case:** Verify if reference memo appears in GCash transaction history

## Feature Management Commands

Manage per-user feature flags.

### feature:list
**Purpose:** List all feature flags for a user

**Usage:**
```bash
php artisan feature:list user@example.com
```

**Output:** Shows all features and their enabled/disabled status for the user

### feature:manage
**Purpose:** Enable or disable feature flags for individual users

**Usage:**
```bash
# Enable feature
php artisan feature:manage settlement-vouchers user@example.com --enable

# Disable feature
php artisan feature:manage settlement-vouchers user@example.com --disable

# Check status
php artisan feature:manage settlement-vouchers user@example.com --status
```

**Available features:**
- `settlement-vouchers` - Pay-in voucher functionality
- `advanced-pricing-mode` - Advanced pricing features
- `beta-features` - Experimental features

**See also:** `docs/FEATURE_ENABLEMENT_STRATEGY.md`

## Operational Commands

Production and administrative commands.

### revenue:collect
**Purpose:** Collect revenue from InstructionItem wallets to configured destinations

**Usage:**
```bash
# Preview mode (don't execute)
php artisan revenue:collect --preview

# Execute collection
php artisan revenue:collect

# Dry run with detailed output
php artisan revenue:collect --preview --verbose
```

**Options:**
- `--preview` - Show what would be collected without executing
- `--verbose` - Detailed output

**How it works:**
1. Scans InstructionItem wallets
2. Calculates collectible revenue
3. Transfers to configured destination wallets
4. Logs all transactions

### balances:check
**Purpose:** Check account balances and update records

**Usage:**
```bash
# Check all accounts
php artisan balances:check

# Check specific account
php artisan balances:check --account=113-001-00001-9
```

**Use case:** Reconciliation and balance verification

### voucher:confirm
**Purpose:** Confirm pending settlement payment with optional auto-disbursement

**Usage:**
```bash
# Confirm payment for voucher
php artisan voucher:confirm VOUCHER-CODE

# Confirm and disburse immediately
php artisan voucher:confirm VOUCHER-CODE --disburse
```

**Options:**
- `--disburse` - Automatically disburse after confirmation

**Use case:** Manual confirmation of settlement voucher payments

### voucher:confirm-payment
**Purpose:** Manually confirm a payment to a settlement/payable voucher

**Usage:**
```bash
php artisan voucher:confirm-payment VOUCHER-CODE 1000
```

**Parameters:**
- `VOUCHER-CODE` - Voucher code
- `AMOUNT` - Payment amount in PHP

**Use case:** Record external payment confirmation

### voucher:disburse
**Purpose:** Disburse settlement voucher funds to owner's bank account

**Usage:**
```bash
php artisan voucher:disburse VOUCHER-CODE
```

**How it works:**
1. Validates voucher is confirmed and ready
2. Initiates bank transfer via payment gateway
3. Updates voucher status
4. Logs transaction

### topup:confirm
**Purpose:** Confirm pending top-up(s) and credit user wallet

**Usage:**
```bash
# Confirm specific top-up
php artisan topup:confirm TOPUP-REF-123

# Confirm all pending
php artisan topup:confirm --all
```

**Options:**
- `--all` - Confirm all pending top-ups

**Use case:** Manual confirmation when webhook fails

## Simulation Commands

Commands for simulating external events.

### simulate:deposit
**Purpose:** Simulate deposit confirmation webhook for testing

**Usage:**
```bash
# Simulate ₱100 deposit to user wallet
php artisan simulate:deposit user@example.com 100

# Simulate with voucher code
php artisan simulate:deposit user@example.com 100 --voucher=ABC123
```

**Options:**
- `--voucher=CODE` - Associate deposit with voucher

**How it works:**
1. Creates fake webhook payload
2. Posts to `/api/confirm-deposit` endpoint
3. Verifies wallet credited correctly

## Command Categories Summary

| Category | Commands | Purpose |
|----------|----------|---------|
| **Testing** | `test:*` (14 commands) | End-to-end testing workflows |
| **Payment Gateway** | `omnipay:*` (4 commands) | Payment integration testing |
| **Feature Flags** | `feature:*` (2 commands) | User feature management |
| **Operations** | `revenue:collect`, `balances:check`, `voucher:*`, `topup:*` (8 commands) | Production operations |
| **Simulation** | `simulate:deposit` (1 command) | Event simulation |

## Best Practices

### Development
- Use `test:*` commands for development and QA
- Always use `--fake` or `--preview` flags when testing with real services
- Check `.env` configuration before running gateway commands

### Production
- Never run `test:*` commands in production
- Use `--preview` flag for dry runs before executing operations
- Monitor logs after running operational commands
- Back up database before bulk operations

### Safety
- ⚠️ Commands marked with warnings execute REAL TRANSACTIONS
- Test in sandbox environment first
- Use small amounts for testing
- Verify credentials before disbursement commands

## Related Documentation

- **Shell Scripts:** `scripts/README.md`
- **Pipedream Integration:** `integrations/pipedream/README.md`
- **Development Commands:** `WARP.md` (for quick reference)
- **Testing Guide:** `docs/guides/testing/`
- **Feature Flags:** `docs/FEATURE_ENABLEMENT_STRATEGY.md`

## Adding New Commands

When creating new console commands:

1. **Naming convention:** Use prefix (`test:`, `feature:`, etc.)
2. **Description:** Clear, concise description in `$description` property
3. **Help text:** Provide usage examples in `getHelp()` method
4. **Documentation:** Update this file with command details
5. **WARP.md:** Add to Testing & Automation section if commonly used
