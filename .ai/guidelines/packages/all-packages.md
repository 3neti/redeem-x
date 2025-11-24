# Mono-Repo Packages

## Overview
Redeem-X uses a mono-repo structure with 9 custom packages in the `packages/` directory. Each package is symlinked via Composer's path repository feature and follows PSR-4 autoloading.

## Package Structure
```
packages/
├── voucher/            # Digital voucher system with cash entities
├── cash/               # Cash entity management
├── payment-gateway/    # Payment gateway abstraction (Omnipay)
├── wallet/             # Wallet and top-up system
├── contact/            # Contact management
├── model-channel/      # Model-specific notification channels
├── model-input/        # Dynamic model input handling
├── omnichannel/        # Multi-channel communication
└── money-issuer/       # Money issuance logic
```

---

## voucher Package (`lbhurtado/voucher`)

### Purpose
Core voucher system with support for cash entities, redemption validation, input collection, and instructions management.

### Key Classes

**Models:**
- `Voucher` - Main voucher model with code generation
- `Cash` - Monetary entity with secure redemption

**DTOs (Spatie Laravel Data):**
- `VoucherInstructionsData` - Complete voucher configuration
- `CashInstructionData` - Cash-specific settings
- `CashValidationRulesData` - Validation rules for cash redemption
- `InputFieldData` - Input field configuration
- `FeedbackChannelData` - Notification channel settings

**Enums:**
- `VoucherInputField` - Available input types (MOBILE, EMAIL, LOCATION, SIGNATURE, SELFIE, NAME, ADDRESS)
- `CashStatus` - Cash statuses (MINTED, EXPIRED, DISBURSED, CANCELLED)

### Key Features
- Voucher code generation with prefix and mask support
- Cash entity with hashed secret redemption
- Status tracking via Spatie statuses trait
- Tagging support via Spatie tags trait
- Polymorphic relationships for flexibility
- Expiration handling
- Redemption validation pipeline

### Factory Support
```php
// Create voucher with cash
$voucher = Voucher::factory()->withCash()->create();

// Create expired voucher
$voucher = Voucher::factory()->expired()->create();

// Create with specific instructions
$voucher = Voucher::factory()->create([
    'metadata' => [
        'instructions' => VoucherInstructionsData::from([...]),
    ],
]);
```

### Usage Example
```php
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Models\Cash;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

// Create voucher with instructions
$voucher = Voucher::create([
    'code' => 'TEST-123',
    'metadata' => [
        'instructions' => VoucherInstructionsData::from([
            'cash' => ['amount' => 1000, 'currency' => 'PHP'],
            'inputs' => ['MOBILE', 'EMAIL'],
        ]),
    ],
]);

// Verify cash redemption
if ($voucher->cash->canRedeem($secret)) {
    // Process redemption
}
```

---

## cash Package (`lbhurtado/cash`)

### Purpose
Specialized package for cash entity management, separate from voucher concerns.

### Key Classes
- `Cash` model with amount, currency, metadata
- Status management with Spatie HasStatuses
- Tagging with Spatie HasTags
- Secure secret hashing and verification

### Key Methods
```php
// Create cash
$cash = Cash::create([
    'amount' => Money::of(1000, 'PHP'),
    'secret' => 'secure-secret',
    'expires_on' => now()->addWeek(),
]);

// Verify redemption
$cash->canRedeem('provided-secret'); // bool

// Status management
$cash->setStatus(CashStatus::DISBURSED, 'Funds sent');
$cash->hasStatus(CashStatus::DISBURSED); // bool

// Tagging
$cash->attachTags(['finance', 'budget']);
$cashe

s = Cash::withAnyTags(['finance'])->get();
```

---

## payment-gateway Package (`lbhurtado/payment-gateway`)

### Purpose
Payment gateway abstraction layer with support for multiple implementations (Omnipay framework and direct API calls).

### Key Classes

**Interfaces:**
- `PaymentGatewayInterface` - Contract for all gateways
- `TopUpInterface` - Contract for top-up models

**Implementations:**
- `OmnipayPaymentGateway` - Omnipay-based implementation (recommended)
- `NetbankPaymentGateway` - Direct API implementation (legacy)

**DTOs:**
- `TopUpResultData` - Gateway response data
- `DisbursementResultData` - Disbursement response

**Traits:**
- `HasTopUps` - Add to any model for top-up functionality
- `CanCollect` - NetBank Direct Checkout integration

### Configuration
Switch implementations via environment:
```bash
USE_OMNIPAY=true  # Use Omnipay (recommended)
```

### Key Features (Omnipay)
- Settlement rail validation (INSTAPAY/PESONET)
- EMI detection (GCash/PayMaya require INSTAPAY)
- Amount limit validation
- OAuth2 token caching
- Bank capability checking
- Comprehensive logging

### Usage Example
```php
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

$gateway = app(PaymentGatewayInterface::class);

// Disburse funds
$result = $gateway->disburse(
    amount: 100,
    mobile: '09173011987',
    bic: 'GXCHPHM2XXX',
    rail: 'INSTAPAY'
);

if ($result->success) {
    // Disbursement successful
}
```

### Artisan Commands
- `omnipay:disburse` - Test disbursement
- `omnipay:qr` - Generate payment QR
- `omnipay:balance` - Check account balance

---

## wallet Package (`lbhurtado/wallet`)

### Purpose
Wallet management and top-up functionality built on Bavix Wallet with NetBank Direct Checkout integration.

### Key Classes
- `TopUp` model - Tracks payment sessions
- `HasTopUps` trait - Adds top-up methods to models
- `CanCollect` trait - Direct Checkout API integration

### TopUp Lifecycle
1. **PENDING** - Payment initiated
2. **PAID** - Payment confirmed, wallet credited
3. **FAILED** - Payment failed
4. **EXPIRED** - Session expired

### Key Methods (via HasTopUps)
```php
// Initiate top-up
$topUp = $user->initiateTopUp(
    amount: 500,
    gateway: 'netbank',
    institution: 'GCASH'
);

// Query top-ups
$user->getTopUps();                          // All
$user->getPendingTopUps();                   // Pending
$user->getPaidTopUps();                      // Successful
$user->getTopUpByReference('TOPUP-123');     // By reference
$user->getTotalTopUps();                     // Sum

// Credit wallet
$user->creditWalletFromTopUp($topUp);
```

### Mock Mode
```bash
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true  # No real API calls
```

---

## contact Package (`lbhurtado/contact`)

### Purpose
Contact management with mobile number normalization and validation.

### Key Classes
- `Contact` model
- Mobile number formatting utilities
- Contact verification

### Usage
```php
use LBHurtado\Contact\Models\Contact;

$contact = Contact::create([
    'mobile' => '09173011987',
    'email' => 'user@example.com',
]);

// Normalize mobile number
$normalized = Contact::normalizeMobile('09173011987'); // +639173011987
```

---

## model-channel Package (`lbhurtado/laravel-model-channel`)

### Purpose
Model-specific notification channels that use model attributes to determine delivery address.

### Key Features
- Route notifications based on model attributes
- Support for SMS, email, webhook channels
- Polymorphic notification handling

### Usage
```php
namespace App\Notifications;

use LBHurtado\ModelChannel\Channels\ModelSmsChannel;

class VoucherRedemptionNotification extends Notification
{
    public function via($notifiable)
    {
        return [ModelSmsChannel::class, 'mail'];
    }
    
    public function routeNotificationFor($channel, $notifiable)
    {
        if ($channel === ModelSmsChannel::class) {
            return $notifiable->mobile; // Uses model's mobile attribute
        }
    }
}
```

---

## model-input Package (`lbhurtado/laravel-model-input`)

### Purpose
Dynamic input field handling based on model configuration.

### Key Features
- Collect inputs dynamically
- Validate against defined rules
- Store inputs as metadata
- Support for various input types (text, image, location)

### Usage
```php
use LBHurtado\ModelInput\Traits\HasInputs;

class Redemption extends Model
{
    use HasInputs;
    
    protected $inputFields = ['mobile', 'email', 'location'];
}

// Collect inputs
$redemption->collectInputs([
    'mobile' => '09173011987',
    'email' => 'user@example.com',
]);
```

---

## omnichannel Package (`lbhurtado/omnichannel`)

### Purpose
Unified multi-channel communication orchestration.

### Key Features
- Send messages across multiple channels simultaneously
- Channel priority and fallback
- Delivery status tracking
- Template support per channel

### Usage
```php
use LBHurtado\Omnichannel\Omnichannel;

Omnichannel::send($user, [
    'email' => ['subject' => 'Test', 'body' => 'Message'],
    'sms' => ['message' => 'SMS Text'],
    'webhook' => ['url' => 'https://...', 'payload' => [...]]
]);
```

---

## money-issuer Package (`lbhurtado/money-issuer`)

### Purpose
Money issuance logic and tracking.

### Key Features
- Issue money to users
- Track issuance history
- Support multiple currencies
- Integration with wallet system

### Usage
```php
use LBHurtado\MoneyIssuer\MoneyIssuer;

$issuer = new MoneyIssuer();

// Issue money
$issuer->issue(
    user: $user,
    amount: 1000,
    currency: 'PHP',
    reason: 'Voucher redemption'
);
```

---

## Package Development Guidelines

### Creating New Package Classes
When adding classes to packages, follow these conventions:

1. **Use proper namespacing**: `LBHurtado\{PackageName}\...`
2. **Add tests**: Every package should have test coverage
3. **Use factories**: Create factories for models
4. **Document APIs**: Add PHPDoc blocks
5. **Follow Laravel conventions**: Use Eloquent, follow naming patterns

### Testing Packages
```bash
# Test specific package
./vendor/bin/pest packages/voucher/tests

# Test all packages
./vendor/bin/pest packages/*/tests
```

### Package Dependencies
Packages can depend on other packages:
- **voucher** depends on **cash**
- **wallet** depends on **payment-gateway**
- **omnichannel** depends on **contact**, **model-channel**

### Adding New Packages
1. Create directory in `packages/`
2. Add `composer.json` with proper autoloading
3. Add to root `composer.json` repositories
4. Run `composer update`
5. Create service provider if needed
6. Register in `bootstrap/providers.php`

---

## Integration Points

### Application to Packages
- Application uses packages via Composer autoloading
- Service providers auto-discovered
- Migrations run from package directories
- Factories available via `\LBHurtado\Package\Database\Factories\`

### Package to Package
- Direct class imports
- Shared interfaces (e.g., `PaymentGatewayInterface`)
- Event-driven communication
- Trait composition

### External Dependencies
Packages use these external libraries:
- `brick/money` - Money value objects
- `spatie/laravel-data` - DTOs
- `spatie/laravel-model-status` - Status tracking
- `spatie/laravel-tags` - Tagging
- `bavix/laravel-wallet` - Wallet functionality
- `omnipay/omnipay` - Payment gateway abstraction
