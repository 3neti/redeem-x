# Shell Scripts Guide

Documentation for shell scripts in the redeem-x project.

## Location

All shell scripts are located in `scripts/testing/`.

## Available Scripts

### test-settlement-voucher-flow.sh

**Purpose:** End-to-end testing of settlement voucher lifecycle including loan disbursement and repayment confirmation.

**File size:** 27KB (executable)  
**Last updated:** Jan 23, 2026

#### Features
- Complete settlement voucher workflow testing
- Four-phase testing process
- NetBank API integration
- GCash/Maya payment simulation
- Comprehensive logging and error handling

#### Test Phases

**Phase 1: Query Voucher**
- Fetches voucher details via `/api/v1/vouchers/{code}` endpoint
- Validates voucher status (unredeemed)
- Extracts amount and recipient information
- Displays voucher metadata

**Phase 2: Disburse Funds**
- Initiates bank transfer to GCash/Maya account
- Uses NetBank InstaPay rail
- Validates recipient bank code (GXCHPHM2XXX for GCash)
- Captures transaction reference ID
- Logs disbursement confirmation

**Phase 3: Simulate QR Payment**
- Generates payment QR code
- Simulates redeemer scanning and paying via GCash
- Posts webhook to `/api/webhooks/netbank/payment`
- Validates payment confirmation

**Phase 4: Confirm Repayment**
- Verifies payment credited to settlement voucher
- Checks balance updates
- Validates transaction reconciliation
- Generates completion report

#### Usage

```bash
./scripts/testing/test-settlement-voucher-flow.sh <voucher_code> [options]
```

**Required arguments:**
- `voucher_code` - Settlement voucher code to test

**Options:**
- `--mobile=NUMBER` - Mobile number for notifications (default: from .env)
- `--verbose` - Enable detailed logging
- `--skip-disburse` - Skip disbursement phase (testing only)
- `--skip-payment` - Skip payment simulation phase

**Examples:**

```bash
# Basic usage
./scripts/testing/test-settlement-voucher-flow.sh SETTLE-ABC123

# With custom mobile number
./scripts/testing/test-settlement-voucher-flow.sh SETTLE-ABC123 --mobile=09171234567

# Verbose mode with full logs
./scripts/testing/test-settlement-voucher-flow.sh SETTLE-ABC123 --verbose

# Test partial flow (skip disbursement)
./scripts/testing/test-settlement-voucher-flow.sh SETTLE-ABC123 --skip-disburse
```

#### Prerequisites

**Environment variables (`.env`):**
```bash
NETBANK_BASE_URL=https://api-sandbox.netbank.ph
NETBANK_ACCESS_KEY=your_access_key
NETBANK_SECRET_KEY=your_secret_key
NETBANK_ACCOUNT_NUMBER=113-001-00001-9
DEFAULT_TEST_MOBILE=09173011987
```

**Required tools:**
- `curl` - HTTP requests
- `jq` - JSON parsing
- `bash` 4.0+ - Shell scripting

**API access:**
- Valid NetBank sandbox credentials
- Active settlement voucher in database
- Configured webhook endpoints

#### Exit Codes

- `0` - Success (all phases completed)
- `1` - Voucher query failed
- `2` - Disbursement failed
- `3` - Payment simulation failed
- `4` - Repayment confirmation failed
- `5` - Configuration error (missing .env variables)

#### Output

Script generates detailed output including:

```
==============================================
SETTLEMENT VOUCHER FLOW TEST
==============================================
Voucher Code: SETTLE-ABC123
Mobile: 09173011987
Started: 2026-02-03 10:30:00
==============================================

[Phase 1] Querying voucher...
✓ Voucher found
  Amount: ₱5,000.00
  Status: unredeemed
  Recipient: 09171234567

[Phase 2] Disbursing funds to GCash...
✓ Disbursement initiated
  Transaction ID: NB-123456789
  Reference: DISB-SETTLE-ABC123

[Phase 3] Simulating QR payment...
✓ QR code generated
✓ Payment webhook sent
✓ Payment confirmed

[Phase 4] Confirming repayment...
✓ Balance updated: ₱5,000.00
✓ Voucher status: confirmed
✓ Transaction reconciled

==============================================
TEST COMPLETED SUCCESSFULLY
Total time: 45 seconds
==============================================
```

#### Troubleshooting

**Error: "Voucher not found"**
- Verify voucher code is correct
- Check database for voucher existence
- Ensure voucher is settlement type

**Error: "Disbursement failed: Invalid credentials"**
- Verify NetBank API credentials in `.env`
- Check access key and secret key are valid
- Ensure sandbox mode is enabled

**Error: "Payment simulation timeout"**
- Check webhook endpoint is reachable
- Verify Laravel queue worker is running
- Review Laravel logs for errors

**Error: "Balance mismatch"**
- Verify wallet balance before test
- Check for concurrent transactions
- Review transaction logs

---

### test-netbank-webhook-flow.sh

**Purpose:** Test NetBank webhook classification (top-up vs settlement payment) and SMS confirmation flow.

**File size:** 21KB (executable)  
**Last updated:** Jan 22, 2026

#### Features
- Webhook payload simulation
- Top-up vs payment classification
- SMS notification testing
- Wallet credit verification
- Unconfirmed payment flow

#### Test Flows

**Flow 1: Direct Wallet Credit (Top-Up)**
- Simulates payment WITHOUT voucher code
- Webhook classifies as top-up
- Credits user wallet immediately
- No SMS confirmation required

**Flow 2: Unconfirmed Voucher Credit + SMS**
- Simulates payment WITH voucher code
- Webhook classifies as settlement payment
- Credits wallet as "unconfirmed"
- Sends SMS confirmation to redeemer
- Manual/auto-confirmation required

#### Usage

```bash
./scripts/testing/test-netbank-webhook-flow.sh <amount> [voucher_code] [options]
```

**Required arguments:**
- `amount` - Payment amount in PHP (e.g., 100 for ₱100)

**Optional arguments:**
- `voucher_code` - Settlement voucher code (if testing Flow 2)

**Options:**
- `--mobile=NUMBER` - Mobile number for SMS (required for `--send-sms`)
- `--send-sms` - Trigger SMS confirmation after payment
- `--skip-webhook` - Skip webhook POST (testing only)
- `--verbose` - Enable detailed logging

**Examples:**

```bash
# Test top-up (Flow 1)
./scripts/testing/test-netbank-webhook-flow.sh 100

# Test voucher payment without SMS (Flow 2)
./scripts/testing/test-netbank-webhook-flow.sh 500 SETTLE-ABC123

# Test voucher payment with SMS
./scripts/testing/test-netbank-webhook-flow.sh 500 SETTLE-ABC123 --mobile=09171234567 --send-sms

# Verbose mode
./scripts/testing/test-netbank-webhook-flow.sh 1000 --verbose
```

#### Prerequisites

**Environment variables (`.env`):**
```bash
APP_URL=https://redeem-x.test
WEBHOOK_ENDPOINT=/api/webhooks/netbank/payment
DEFAULT_TEST_USER=user@example.com
DEFAULT_TEST_MOBILE=09173011987
SMS_ENABLED=true
```

**Required tools:**
- `curl` - HTTP requests
- `jq` - JSON parsing
- `uuidgen` - Generate transaction IDs

**Services:**
- Laravel application running
- Queue worker active (for SMS)
- EngageSpark configured (if using `--send-sms`)

#### Webhook Payload Structure

**Top-up payload:**
```json
{
  "transaction_id": "TXN-UUID",
  "amount": 100.00,
  "currency": "PHP",
  "sender_name": "John Doe",
  "sender_mobile": "09171234567",
  "status": "SUCCESS",
  "timestamp": "2026-02-03T10:30:00Z",
  "memo": null
}
```

**Settlement payment payload:**
```json
{
  "transaction_id": "TXN-UUID",
  "amount": 500.00,
  "currency": "PHP",
  "sender_name": "Jane Smith",
  "sender_mobile": "09173011987",
  "status": "SUCCESS",
  "timestamp": "2026-02-03T10:30:00Z",
  "memo": "SETTLE-ABC123"
}
```

#### Exit Codes

- `0` - Success
- `1` - Webhook POST failed
- `2` - Wallet credit verification failed
- `3` - SMS sending failed
- `4` - Configuration error

#### Output

```
==============================================
NETBANK WEBHOOK FLOW TEST
==============================================
Amount: ₱500.00
Voucher: SETTLE-ABC123
Flow: Unconfirmed Payment
Mobile: 09173011987
==============================================

[Step 1] Generating webhook payload...
✓ Transaction ID: TXN-550e8400-e29b-41d4-a716-446655440000
✓ Payload created

[Step 2] Posting webhook to /api/webhooks/netbank/payment...
✓ Webhook accepted (200 OK)

[Step 3] Verifying wallet credit...
✓ User wallet: user@example.com
✓ Balance before: ₱1,000.00
✓ Balance after: ₱1,500.00 (unconfirmed)
✓ Credit amount: ₱500.00 ✓

[Step 4] Sending SMS confirmation...
✓ SMS queued
✓ Recipient: 09173011987
✓ Message: "You have received ₱500.00 for voucher SETTLE-ABC123. Reply CONFIRM to accept."

==============================================
TEST COMPLETED SUCCESSFULLY
Check SMS inbox and confirm payment.
==============================================
```

#### Troubleshooting

**Error: "Webhook endpoint not found"**
- Verify `APP_URL` is correct in `.env`
- Check Laravel routes include webhook endpoint
- Ensure application is running

**Error: "Wallet credit failed"**
- Verify user exists in database
- Check Bavix Wallet configuration
- Review Laravel logs for wallet errors

**Error: "SMS not sent"**
- Verify queue worker is running: `php artisan queue:work`
- Check EngageSpark credentials in `.env`
- Review SMS logs: `php artisan pail`

**Error: "Unconfirmed balance not updated"**
- Check settlement voucher exists
- Verify voucher is in correct status
- Review transaction logs

---

## General Best Practices

### Running Scripts

**Development environment:**
```bash
# Make scripts executable
chmod +x scripts/testing/*.sh

# Run from project root
./scripts/testing/test-settlement-voucher-flow.sh VOUCHER-CODE

# Or with absolute path
/Users/rli/PhpstormProjects/redeem-x/scripts/testing/test-settlement-voucher-flow.sh VOUCHER-CODE
```

**Production/staging:**
- Always test in sandbox environment first
- Use verbose mode for troubleshooting
- Capture output to log files: `./script.sh > output.log 2>&1`
- Review logs before running destructive operations

### Debugging

**Enable verbose mode:**
```bash
./scripts/testing/test-settlement-voucher-flow.sh VOUCHER-CODE --verbose
```

**Check environment:**
```bash
# Verify .env loaded
env | grep NETBANK

# Test API connectivity
curl -H "Authorization: Bearer $NETBANK_ACCESS_KEY" $NETBANK_BASE_URL/health
```

**Review logs:**
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Webhook logs
tail -f storage/logs/webhooks.log

# Queue worker logs
php artisan pail
```

### Error Handling

All scripts include comprehensive error handling:
- Exit on first error (unless `--continue-on-error` specified)
- Detailed error messages with context
- Stack traces for debugging
- Cleanup on exit (temporary files, state)

### Security

**Credentials:**
- Never commit `.env` file with real credentials
- Use sandbox credentials for testing
- Rotate credentials after testing in production-like environments

**Sensitive data:**
- Scripts sanitize output (mask mobile numbers, API keys)
- Use `--no-output` flag to suppress sensitive data
- Review output before sharing logs

### Integration with CI/CD

**GitHub Actions example:**
```yaml
- name: Test Settlement Voucher Flow
  run: |
    ./scripts/testing/test-settlement-voucher-flow.sh ${{ secrets.TEST_VOUCHER_CODE }}
  env:
    NETBANK_ACCESS_KEY: ${{ secrets.NETBANK_SANDBOX_KEY }}
    NETBANK_SECRET_KEY: ${{ secrets.NETBANK_SANDBOX_SECRET }}
```

## Related Documentation

- **Console Commands:** `CONSOLE_COMMANDS.md`
- **Pipedream Integration:** `integrations/pipedream/README.md`
- **API Documentation:** `docs/api/`
- **Webhook Guide:** `docs/guides/features/WEBHOOK_INTEGRATION.md`
- **Testing Strategy:** `docs/guides/testing/`

## Contributing

When adding new shell scripts:

1. **Location:** Place in `scripts/testing/` (or appropriate subdirectory)
2. **Naming:** Use descriptive names with `.sh` extension
3. **Shebang:** Always start with `#!/bin/bash`
4. **Documentation:** Add comprehensive header comments
5. **Error handling:** Use `set -e` and trap errors
6. **Exit codes:** Document all exit codes
7. **Help text:** Implement `--help` flag
8. **Update docs:** Add entry to this file and `scripts/README.md`

**Template:**
```bash
#!/bin/bash
#
# Script: test-new-feature.sh
# Purpose: Test new feature XYZ
# Usage: ./test-new-feature.sh <arg> [options]
# Exit codes: 0=success, 1=failure
#

set -e  # Exit on error
set -u  # Exit on undefined variable

# Your script here
```
