# Redeem-X AI Guidelines

## Overview
This directory contains custom AI guidelines for the Redeem-X digital voucher redemption system. These guidelines work alongside Laravel Boost's standard guidelines to provide AI agents with deep contextual understanding of this specific codebase.

## Quick Reference

### Core Concepts
- **Vouchers** - Digital codes with configurable cash value and redemption rules
- **Cash Entities** - Secure monetary units with hashed secrets and status tracking
- **Campaigns** - Reusable voucher templates for bulk generation
- **Top-Up** - Wallet funding via NetBank Direct Checkout
- **Disbursement** - Automatic payment to recipients via Omnipay gateway

### Key Workflows
1. **Voucher Generation** → Distribution → Redemption → Disbursement
2. **Top-Up** → Payment Initiation → Webhook → Wallet Credit
3. **Notification** → Template Processing → Multi-Channel Delivery

## Guidelines Structure

### Domain Knowledge (`redeem-x/`)
Core business logic and system architecture:

#### [`domain.md`](redeem-x/domain.md)
Comprehensive coverage of:
- Voucher System Architecture (lifecycle, instructions, campaigns)
- Cash Entity System (attributes, status, redemption)
- Payment Gateway Integration (Omnipay, settlement rails)
- Top-Up System (NetBank Direct Checkout, wallet)
- Notification Templates (variables, channels, processor)

#### [`artisan-commands.md`](redeem-x/artisan-commands.md)
Custom Artisan commands with examples:
- `test:notification` - End-to-end notification testing
- `test:sms` - Direct SMS testing
- `test:topup` - Top-up flow testing
- `omnipay:disburse` - Payment gateway testing
- `omnipay:qr` - QR code generation
- `omnipay:balance` - Balance checking

#### [`testing.md`](redeem-x/testing.md)
Testing patterns and strategies:
- Pest v4 test organization
- Factory usage patterns
- Mocking payment gateways
- Browser testing with Inertia
- Queue and event testing
- Best practices

#### [`frontend.md`](redeem-x/frontend.md)
Vue 3 + Inertia.js patterns:
- Reusable components (VoucherInstructionsForm, RedeemWidget, QR)
- Wayfinder integration (type-safe routes)
- Inertia.js form handling
- Real-time updates (wallet balance, voucher status)
- Composition API patterns
- Tailwind CSS conventions

### Package Documentation (`packages/`)

#### [`all-packages.md`](packages/all-packages.md)
Mono-repo package reference covering all 9 packages:
- **voucher** - Digital voucher system with cash entities
- **cash** - Cash entity management
- **payment-gateway** - Payment gateway abstraction (Omnipay)
- **wallet** - Wallet and top-up system
- **contact** - Contact management
- **model-channel** - Model-specific notification channels
- **model-input** - Dynamic model input handling
- **omnichannel** - Multi-channel communication
- **money-issuer** - Money issuance logic

Each package section includes:
- Purpose and key features
- Key classes and interfaces
- Usage examples
- Factory patterns
- Integration points

## How AI Should Use These Guidelines

### Understanding Domain Logic
When working with vouchers, cash, or payments:
1. Read `domain.md` for business rules
2. Check `all-packages.md` for package APIs
3. Review `testing.md` for test patterns

### Making Code Changes
1. **Check conventions** in relevant guideline first
2. **Use existing patterns** from examples
3. **Follow testing practices** from `testing.md`
4. **Run appropriate commands** from `artisan-commands.md`

### Debugging Issues
1. Check `artisan-commands.md` for relevant test commands
2. Review `testing.md` for mocking strategies
3. Consult `domain.md` for business logic validation

### Adding Features
1. Understand domain concepts in `domain.md`
2. Check package APIs in `all-packages.md`
3. Follow testing patterns in `testing.md`
4. Use frontend patterns from `frontend.md`

## Common Tasks Quick Links

### Generate Vouchers
- Domain: [Voucher System Architecture](redeem-x/domain.md#voucher-system-architecture)
- Package: [voucher Package](packages/all-packages.md#voucher-package-lbhurtadovoucher)
- Testing: [Testing Voucher System](redeem-x/testing.md#testing-voucher-system)

### Redeem Vouchers
- Domain: [Secure Redemption Flow](redeem-x/domain.md#secure-redemption-flow)
- Testing: [Voucher Redemption Tests](redeem-x/testing.md#voucher-redemption)

### Handle Payments
- Domain: [Payment Gateway Integration](redeem-x/domain.md#payment-gateway-integration)
- Package: [payment-gateway Package](packages/all-packages.md#payment-gateway-package-lbhurtadopayment-gateway)
- Commands: [Payment Gateway Testing](redeem-x/artisan-commands.md#payment-gateway-testing)

### Top-Up Wallets
- Domain: [Top-Up System](redeem-x/domain.md#top-up-system)
- Package: [wallet Package](packages/all-packages.md#wallet-package-lbhurtadowallet)
- Commands: [test:topup](redeem-x/artisan-commands.md#testtopup)
- Testing: [Testing Top-Up System](redeem-x/testing.md#testing-top-up-system)

### Send Notifications
- Domain: [Notification Templates](redeem-x/domain.md#notification-templates)
- Commands: [test:notification](redeem-x/artisan-commands.md#testnotification)
- Testing: [Testing Notifications](redeem-x/testing.md#testing-notifications)

### Create Frontend Components
- Frontend: [Reusable Components](redeem-x/frontend.md#reusable-components)
- Frontend: [Wayfinder Integration](redeem-x/frontend.md#wayfinder-integration)
- Frontend: [Component Conventions](redeem-x/frontend.md#component-conventions)

## Technology Stack Reference

**Backend:**
- Laravel 12 (PHP 8.2+)
- SQLite/MySQL/PostgreSQL
- Pest v4 (testing)
- Omnipay (payments)
- Bavix Wallet
- WorkOS AuthKit

**Frontend:**
- Vue 3 + TypeScript
- Inertia.js v2
- Tailwind CSS v4
- Wayfinder (type-safe routes)
- reka-ui (headless components)
- Vite

**Packages:**
- Spatie Laravel Data (DTOs)
- Spatie Model Status (status tracking)
- Spatie Tags (tagging)
- Brick Money (monetary values)
- frittenkeez Laravel Vouchers (code generation)

## Environment Configuration

### Key Environment Variables
```bash
# Payment Gateway
USE_OMNIPAY=true
DISBURSE_DISABLE=false
PAYMENT_GATEWAY=netbank

# Top-Up
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true

# Notifications
MAIL_MAILER=resend
SMS_PROVIDER=engagespark
```

See [WARP.md](../../WARP.md) for complete environment setup and development commands.

## Laravel Boost Integration

These custom guidelines are automatically loaded by Laravel Boost alongside ecosystem guidelines. To update guidelines:

```bash
# Refresh Laravel ecosystem guidelines
php artisan boost:update

# Regenerate guideline files
php artisan boost:install
```

Custom guidelines take precedence over generic guidelines when conflicts arise.

## Contributing to Guidelines

When adding new features or patterns:
1. Document in appropriate guideline file
2. Add code examples
3. Link from this README
4. Update relevant quick links

Keep guidelines:
- **Concise** - Only essential information
- **Practical** - Include working examples
- **Current** - Update as code changes
- **Specific** - Redeem-X specific, not generic Laravel

## Additional Resources

- [WARP.md](../../WARP.md) - Development commands and workflows
- [Laravel Boost Docs](https://laravel.com/ai/boost) - Official Boost documentation
- Package READMEs in `packages/*/README.md`
- Test files for usage examples

---

**Last Updated**: Auto-generated by Laravel Boost  
**Boost Version**: 1.8+  
**Laravel Version**: 12.x
