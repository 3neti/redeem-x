# Scripts & Automation

This directory contains shell scripts and automation utilities for the redeem-x project.

## Directory Structure

```
scripts/
├── testing/          # Test automation scripts
└── deployment/       # Future: Deployment scripts
```

## Testing Scripts

Located in `scripts/testing/`:

### test-settlement-voucher-flow.sh
**Purpose:** End-to-end testing of settlement voucher lifecycle (loan disbursement + repayment)

**Phases:**
1. Query voucher details via API
2. Disburse funds to GCash/Maya account
3. Simulate QR code payment from redeemer
4. Confirm repayment and validate balance

**Usage:**
```bash
./scripts/testing/test-settlement-voucher-flow.sh <voucher_code> [--mobile=09171234567]
```

**Requirements:**
- Valid settlement voucher code
- NetBank API credentials configured
- Mobile number (optional, defaults to configured test number)

### test-netbank-webhook-flow.sh
**Purpose:** Test NetBank webhook classification (top-up vs payment)

**Test Flows:**
1. **Direct wallet credit** - Payment without voucher code (top-up)
2. **Unconfirmed voucher credit** - Payment with voucher code + SMS confirmation

**Usage:**
```bash
# Test top-up (no voucher)
./scripts/testing/test-netbank-webhook-flow.sh 100

# Test voucher payment with SMS
./scripts/testing/test-netbank-webhook-flow.sh 100 VOUCHER-CODE --mobile=09171234567 --send-sms
```

**Options:**
- `--mobile=NUMBER` - Mobile number for SMS notifications
- `--send-sms` - Trigger SMS confirmation after payment

## Development Notes

- All scripts preserve executable permissions via git
- Scripts use `.env` for configuration (API endpoints, credentials)
- Exit codes: 0 = success, 1 = failure
- Comprehensive logging to stdout for debugging

## Related Documentation

- **Console Commands:** `docs/guides/automation/CONSOLE_COMMANDS.md`
- **Pipedream Integration:** `integrations/pipedream/README.md`
- **Development Commands:** See `WARP.md` for artisan commands
