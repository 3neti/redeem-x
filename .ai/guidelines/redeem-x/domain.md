# Redeem-X Domain Guidelines

## Overview
Redeem-X is a digital voucher redemption system with integrated payment processing, wallet management, and multi-channel notifications. This document covers the core domain concepts and business logic.

## Voucher System Architecture

### Voucher Lifecycle
1. **Generation** - Create vouchers with configurable instructions
2. **Distribution** - Share vouchers via QR codes, email, SMS, or direct links
3. **Redemption** - Validate and redeem vouchers with input collection
4. **Disbursement** - Automatically disburse cash to recipients via payment gateway

### VoucherInstructionsData Structure
The `VoucherInstructionsData` DTO defines all aspects of a voucher:
- `cash` - Cash amount, currency, validation rules, TTL
- `inputs` - Required input fields (mobile, email, location, signature, selfie)
- `validations` - Validation rules for each input field
- `feedback` - Notification channels (email, SMS, webhook)
- `rider` - Additional information displayed during redemption
- `count` - Number of vouchers to generate (for bulk generation)
- `prefix` - Code prefix for voucher identification
- `mask` - Code format pattern

### Campaign System
**Campaigns** are reusable voucher templates:
- Store complete `VoucherInstructionsData` for quick reuse
- Each user gets 2 default campaigns: "Blank Template" and "Standard Campaign"
- Many-to-many relationship with vouchers via `campaign_voucher` pivot table
- Pivot table stores `instructions_snapshot` for historical auditability
- Use dedicated `CampaignVoucher` pivot model for type-safe access
- Managed via Settings > Campaigns UI

### Input Field Validation
Supported input fields (via `VoucherInputField` enum):
- **MOBILE** - Phone number with country code validation
- **EMAIL** - Email address validation
- **LOCATION** - Geographic coordinates (latitude/longitude)
- **SIGNATURE** - Digital signature image (base64)
- **SELFIE** - Photo upload (base64)
- **NAME** - Text field for recipient name
- **ADDRESS** - Text field for full address

## Cash Entity System

### Cash Model Attributes
- `amount` - Monetary value using Brick\Money library (stored in minor units)
- `currency` - Currency code (default: PHP)
- `meta` - Custom metadata stored as ArrayObject
- `secret` - Hashed value for secure redemption (never stored plain-text)
- `expires_on` - Expiration timestamp
- `status` - Current status (MINTED, EXPIRED, DISBURSED, etc.)
- `reference` - Polymorphic relationship to voucher or other entity

### Status Management
Cash entities use Spatie's `HasStatuses` trait:
- `setStatus(CashStatus $status, string $reason = null)` - Change status with reason
- `hasStatus(CashStatus $status)` - Check current status
- `hasHadStatus(CashStatus $status)` - Check historical status
- `getCurrentStatus()` - Get current status object

**Common statuses:**
- `MINTED` - Cash created and ready
- `EXPIRED` - Past expiration date
- `DISBURSED` - Funds sent to recipient
- `CANCELLED` - Cash invalidated

### Secure Redemption Flow
1. User submits voucher code
2. System finds associated Cash entity
3. Validates with `canRedeem($providedSecret)` which checks:
   - Secret matches hashed value
   - Not expired (expires_on > now)
4. Collects required inputs based on VoucherInstructionsData
5. Validates inputs against defined rules
6. Creates redemption record
7. Triggers post-redemption pipeline (notifications, disbursement)

### Tagging Support
Cash entities support Spatie's `HasTags` trait:
- `attachTags(['finance', 'budget'])` - Add tags
- `detachTags(['finance'])` - Remove tags
- `Cash::withAnyTags(['finance'])->get()` - Query by tags
- Use for classification, reporting, filtering

## Payment Gateway Integration

### Dual Gateway Support
The application supports two payment gateway implementations:
- **Legacy**: `NetbankPaymentGateway` - Direct API calls (x-change style)
- **New (Recommended)**: `OmnipayPaymentGateway` - Omnipay framework

Switch via environment variable:
```bash
USE_OMNIPAY=true  # Use Omnipay (recommended)
USE_OMNIPAY=false # Use legacy implementation
```

### OmnipayPaymentGateway Features
- **Settlement Rail Validation** - Automatic INSTAPAY vs PESONET selection
- **EMI Detection** - GCash and PayMaya require INSTAPAY
- **Amount Limits** - INSTAPAY: ₱50k max, PESONET: ₱1M max
- **OAuth2 Token Caching** - Tokens cached for performance
- **Bank Capability Checking** - Validates bank supports selected rail
- **KYC Address Workaround** - Uses default address for testing

### Settlement Rails
**INSTAPAY:**
- Real-time transfers (seconds)
- Maximum: ₱50,000 per transaction
- Required for EMI (GCash, PayMaya)
- Operating hours: 24/7

**PESONET:**
- Batch processing (next business day)
- Maximum: ₱1,000,000 per transaction
- Traditional banks
- Cut-off times apply

### Disbursement Pipeline
Configured in `config/voucher-pipeline.php`:
1. Post-redemption event fires
2. Pipeline stages execute in order:
   - Validate redemption
   - Check disbursement enabled (`DISBURSE_DISABLE=false`)
   - Resolve payment gateway (based on USE_OMNIPAY)
   - Execute disbursement via gateway
   - Log transaction
   - Fire completion events

### Disbursement Control
```bash
DISBURSE_DISABLE=true   # Disable auto-disbursement (testing)
DISBURSE_DISABLE=false  # Enable auto-disbursement (production)
```

## Top-Up System

### NetBank Direct Checkout Integration
Allows users to add funds to wallet via NetBank payment gateway.

### TopUp Model Lifecycle
1. **PENDING** - Payment initiated, awaiting confirmation
2. **PAID** - Payment confirmed, wallet credited
3. **FAILED** - Payment failed or cancelled
4. **EXPIRED** - Payment session expired

### Hybrid Architecture
**Package Layer** (`lbhurtado/payment-gateway`):
- `TopUpInterface` - Contract for top-up models
- `TopUpResultData` - DTO for gateway responses
- `HasTopUps` trait - Reusable logic for any model
- `CanCollect` trait - NetBank Direct Checkout API integration

**Application Layer** (`app/`):
- `TopUp` model - Implements TopUpInterface
- `User` model - Uses HasTopUps trait
- `TopUpController` - Handles initiation, callback, status
- `NetBankWebhookController` - Processes payment confirmations

### Top-Up Flow
1. User visits `/topup` and enters amount
2. Backend calls `$user->initiateTopUp(500, 'netbank', 'GCASH')`
3. Creates TopUp record with PENDING status
4. Redirects to NetBank payment page (or mock in fake mode)
5. User completes payment in GCash/Maya/Bank app
6. NetBank webhook POSTs to `/webhooks/netbank/payment`
7. Webhook marks TopUp as PAID and credits wallet
8. User returns to callback page, sees success status

### Mock Mode for Testing
```bash
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true  # Mock mode (no real API calls)
NETBANK_DIRECT_CHECKOUT_USE_FAKE=false # Real NetBank sandbox/production
```

In mock mode:
- No API credentials required
- Automatic redirect to callback
- Simulated webhook responses
- Perfect for local development

### Available Methods (via HasTopUps trait)
```php
$user->initiateTopUp(500, 'netbank', 'GCASH')  // Start top-up
$user->getTopUps()                              // All top-ups
$user->getPendingTopUps()                       // Pending payments
$user->getPaidTopUps()                          // Successful top-ups
$user->getTopUpByReference('TOPUP-ABC123')     // Find by reference
$user->getTotalTopUps()                         // Sum of paid top-ups
$user->creditWalletFromTopUp($topUp)           // Credit wallet
```

## Notification Templates

### Template System
Admin-level customizable templates for redemption notifications stored in `lang/en/notifications.php`.

### Template Syntax
Use `{{ variable }}` syntax for dynamic values:
- `{{ code }}` - Voucher code
- `{{ formatted_amount }}` - Currency-formatted amount (e.g., "₱1,000.00")
- `{{ mobile }}` - Recipient mobile number
- `{{ email }}` - Recipient email
- `{{ formatted_address }}` - Full formatted address
- `{{ location }}` - Geographic coordinates
- `{{ signature_url }}` - URL to signature image
- `{{ selfie_url }}` - URL to selfie image

### TemplateProcessor Service
- Supports dot notation for nested variables
- Recursive variable search in data arrays
- Escapes HTML by default for safety
- Used across all notification channels

### VoucherTemplateContextBuilder
Flattens voucher redemption data for easy templating:
- Converts nested structures to dot notation
- Formats monetary values
- Generates URLs for uploaded files
- Prepares data for template processor

### Multi-Channel Support
Templates used in:
- **Email Notifications** - HTML and plain text versions
- **SMS Notifications** - Via EngageSpark
- **Webhook Payloads** - JSON with template values

### Example Template
```php
'redemption_success' => [
    'subject' => 'Voucher {{ code }} Redeemed Successfully',
    'body' => 'Your voucher of {{ formatted_amount }} has been redeemed. Funds will be sent to {{ mobile }}.',
],
```

## Business Rules

### Validation Rules Priority
1. Cash-level validation (secret, expiration)
2. Input field validation (format, required fields)
3. Custom validation rules in VoucherInstructionsData
4. Gateway-level validation (disbursement limits, rails)

### Expiration Handling
- Vouchers can have TTL (time-to-live) in instructions
- Cash entities have explicit `expires_on` timestamp
- Expired vouchers cannot be redeemed
- Status automatically updated to EXPIRED

### Redemption Limits
- One-time use by default (first redemption wins)
- Multiple redemptions require explicit configuration
- Track redemption attempts for security

### Error Handling
- Validation failures return user-friendly messages
- Gateway errors logged with full context
- Failed disbursements can be retried manually
- All failures fire events for monitoring

## Integration Points

### WorkOS AuthKit
- User authentication and management
- Password resets and email verification
- Two-factor authentication support

### Bavix Wallet
- User wallet balance management
- Transaction history
- Atomic balance updates
- Top-up integration

### EngageSpark
- SMS sending via API
- Sender ID configuration
- Delivery status tracking
- Rate limiting

### Spatie Packages
- `spatie/laravel-permission` - Role-based access control
- `spatie/laravel-settings` - Application settings management
- `spatie/laravel-model-status` - Status tracking for models
- `spatie/laravel-tags` - Tagging support

## Key Configuration Files
- `config/voucher.php` - Voucher system settings
- `config/voucher-pipeline.php` - Post-redemption pipeline stages
- `config/payment-gateway.php` - Gateway configuration and switching
- `config/wallet.php` - Bavix wallet settings
- `config/redeem.php` - Redemption widget configuration
- `lang/en/notifications.php` - Notification templates
