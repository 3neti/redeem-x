# Automation & Testing Guide

Comprehensive documentation for automated testing, scripts, and integrations.

## Overview

This directory contains guides for:
- **Console Commands** - Artisan commands for testing and operations
- **Shell Scripts** - Bash scripts for end-to-end testing workflows
- **Pipedream Integration** - SMS gateway integration via Pipedream

## Quick Start

### For Developers

**Most common testing commands:**
```bash
# Test complete notification flow
php artisan test:notification --email=user@example.com

# Test SMS sending
php artisan test:sms 09173011987

# Test wallet top-up
php artisan test:topup 500

# Test settlement voucher lifecycle
./scripts/testing/test-settlement-voucher-flow.sh VOUCHER-CODE
```

### For QA Engineers

**Comprehensive test scripts:**
```bash
# End-to-end settlement voucher testing (4 phases)
./scripts/testing/test-settlement-voucher-flow.sh SETTLE-ABC123 --verbose

# Webhook classification testing
./scripts/testing/test-netbank-webhook-flow.sh 100 VOUCHER-CODE --send-sms

# Test all voucher scenarios
php artisan test:vouchers --scenario=full
```

### For DevOps

**Operational commands:**
```bash
# Check gateway balances
php artisan balances:check

# Collect revenue
php artisan revenue:collect --preview

# Manage feature flags
php artisan feature:manage settlement-vouchers user@example.com --enable
```

## Documentation Files

### CONSOLE_COMMANDS.md
**Complete reference for all Artisan commands**

- 29 commands organized by category:
  - Test commands (`test:*`) - 14 commands
  - Payment gateway (`omnipay:*`) - 4 commands
  - Feature management (`feature:*`) - 2 commands
  - Operations (`revenue:*`, `balances:*`, `voucher:*`, `topup:*`) - 8 commands
  - Simulation (`simulate:*`) - 1 command

**Read this when:**
- Looking for specific test command
- Need command usage examples
- Understanding command parameters and options
- Adding new console commands

[View full documentation →](CONSOLE_COMMANDS.md)

### SHELL_SCRIPTS.md
**Guide to shell scripts for end-to-end testing**

- 2 comprehensive test scripts:
  - `test-settlement-voucher-flow.sh` - 4-phase settlement testing
  - `test-netbank-webhook-flow.sh` - Webhook classification testing

**Read this when:**
- Running E2E tests
- Troubleshooting test script failures
- Understanding test workflows
- Writing new shell scripts

[View full documentation →](SHELL_SCRIPTS.md)

### PIPEDREAM_INTEGRATION.md
**SMS gateway integration via Pipedream**

- Architecture overview
- Deployment guide
- Workflow versions (v2.1 and v3.0)
- Migration guide

**Read this when:**
- Setting up Pipedream workflows
- Deploying SMS integration
- Troubleshooting SMS issues
- Migrating between workflow versions

[View full documentation →](PIPEDREAM_INTEGRATION.md)

Also see: [integrations/pipedream/README.md](../../../integrations/pipedream/README.md) for complete technical documentation.

## Command Categories

### Testing Commands

**Notifications & SMS:**
- `test:notification` - Complete redemption notification flow
- `test:sms` - Direct SMS sending
- `test:sms-balance` - SMS BALANCE command
- `test:sms-redeem` - SMS voucher redemption
- `test:sms-router` - Local SMS command routing

**Payment & Top-Up:**
- `test:topup` - Wallet top-up flow
- `test:direct-checkout` - NetBank Direct Checkout
- `simulate:deposit` - Deposit webhook simulation

**Vouchers:**
- `test:vouchers` - Generate test vouchers
- `test:voucher-traits` - Test voucher metadata/timing/validation

**Gateway & Disbursement:**
- `omnipay:disburse` - ⚠️ Real disbursement transaction
- `omnipay:balance` - Check gateway balance
- `omnipay:qr` - Generate payment QR code
- `test:disbursement-failure` - Test failure alerting

### Operational Commands

**Feature Management:**
- `feature:list` - List user features
- `feature:manage` - Enable/disable features

**Revenue & Balances:**
- `revenue:collect` - Collect revenue from wallets
- `balances:check` - Check account balances

**Voucher Operations:**
- `voucher:confirm` - Confirm settlement payment
- `voucher:confirm-payment` - Manually confirm payment
- `voucher:disburse` - Disburse settlement funds

**Top-Up Operations:**
- `topup:confirm` - Confirm pending top-ups

## Best Practices

### Development

✅ **DO:**
- Use `test:*` commands for development and QA
- Add `--fake` or `--preview` flags when testing with real services
- Check `.env` configuration before running gateway commands
- Enable verbose mode (`--verbose`) for troubleshooting
- Capture script output to log files for review

❌ **DON'T:**
- Run `test:*` commands in production
- Execute `omnipay:disburse` without confirming parameters
- Skip `--preview` flag for operational commands
- Commit `.env` with real credentials

### Testing Strategy

1. **Unit Tests** - Pest PHP (`php artisan test`)
2. **Console Commands** - Artisan test commands (`test:*`)
3. **Shell Scripts** - E2E workflows (`scripts/testing/*.sh`)
4. **Manual Testing** - Postman collections (`docs/api/postman/`)

### Safety

⚠️ Commands that execute REAL TRANSACTIONS:
- `omnipay:disburse` - Sends actual money
- `omnipay:test-gcash-memo` - Real InstaPay transactions
- `revenue:collect` (without `--preview`) - Actual wallet transfers

**Always:**
- Test in sandbox environment first
- Use small amounts for testing
- Verify credentials before execution
- Review output before confirming

## Troubleshooting

### Common Issues

**Command not found:**
```bash
# Refresh Composer autoload
composer dump-autoload

# Clear cache
php artisan cache:clear
php artisan config:clear
```

**Script permission denied:**
```bash
# Make scripts executable
chmod +x scripts/testing/*.sh
```

**Webhook tests failing:**
```bash
# Ensure queue worker is running
php artisan queue:work

# Check webhook endpoint
curl -X POST http://redeem-x.test/api/webhooks/netbank/payment
```

**SMS not sending:**
```bash
# Verify queue worker
php artisan queue:work

# Check SMS logs
php artisan pail

# Test SMS gateway
php artisan test:sms 09173011987
```

## Related Documentation

### Internal
- **WARP.md** - Quick reference for common commands
- **Testing Strategy:** `docs/guides/testing/`
- **Architecture:** `docs/architecture/`
- **API Docs:** `docs/api/`

### External Resources
- **Scripts Location:** `scripts/`
- **Pipedream Workflows:** `integrations/pipedream/`
- **Postman Collections:** `docs/api/postman/`

## Contributing

When adding new automation:

### New Console Command
1. Create command class in `app/Console/Commands/`
2. Use consistent prefix (`test:`, `feature:`, etc.)
3. Add comprehensive description and help text
4. Update `CONSOLE_COMMANDS.md`
5. Add to `WARP.md` if commonly used

### New Shell Script
1. Place in `scripts/testing/` (or appropriate subdirectory)
2. Use descriptive name with `.sh` extension
3. Include header documentation
4. Implement `--help` flag
5. Update `SHELL_SCRIPTS.md` and `scripts/README.md`

### New Integration
1. Create subdirectory in `integrations/`
2. Add comprehensive README.md
3. Document architecture and deployment
4. Add cross-references to this guide

## Support

For questions or issues:
- Review relevant documentation file
- Check troubleshooting sections
- Review Laravel logs (`storage/logs/`)
- Enable verbose/debug mode for detailed output
