# Money Issuer - Bank Registry Package

Lightweight package providing Philippine bank and EMI directory.

## Purpose

Provides bank/EMI data without payment operation dependencies.  
Use this when you need to:

* List available banks and EMIs
* Check settlement rail support
* Build bank directory applications  
* Query EMI restrictions

## Usage

```php
use LBHurtado\MoneyIssuer\Support\BankRegistry;

$registry = new BankRegistry();

// Get all banks
$banks = $registry->all(); // array of 146 banks

// Find specific bank
$gcash = $registry->find('GXCHPHM2XXX');
// ['full_name' => 'G-Xchange / GCash', ...]

// Get EMIs only
$emis = $registry->getEMIs(); // Collection

// Check rail support (respects EMI restrictions)
$rails = $registry->getAllowedRails('GXCHPHM2XXX');
// ['INSTAPAY'] - GCash only supports INSTAPAY

// Check if bank is EMI
$isEmi = $registry->isEMI('GXCHPHM2XXX'); // true
$isEmi = $registry->isEMI('BOPIPHMM'); // false (BPI)

// Get human-readable name
$name = $registry->getBankName('GXCHPHM2XXX'); // 'GCash'
```

## Installation

```bash
composer require lbhurtado/money-issuer
php artisan vendor:publish --tag=banks-registry
```

## Configuration

### Bank Restrictions

EMI restrictions are defined in `config/bank-restrictions.php`:

```php
return [
    'emi_restrictions' => [
        'GXCHPHM2XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'GCash',
            'reason' => 'EMI - Real-time transfers only'
        ],
        // ... more EMIs
    ],
];
```

Publish config:
```bash
php artisan vendor:publish --tag=config --provider="LBHurtado\MoneyIssuer\MoneyIssuerServiceProvider"
```

## Architecture

**Separation of Concerns:**
- `money-issuer` = Bank/EMI directory (data + registry)
- `payment-gateway` = Payment operations (depends on money-issuer)

**Benefits:**
- Lightweight: Use in apps that need bank data without payment logic
- Reusable: Build bank directories, EMI comparison tools, routing services
- Dependency direction: Lower-level package holds foundational data

## Updating Bank Data

See [`docs/BANKS_JSON_UPDATE.md`](../../docs/BANKS_JSON_UPDATE.md) for update procedures.

## Package Dependencies

This package depends on:
- Laravel Framework
- No payment or transaction packages

Packages that depend on this:
- `lbhurtado/payment-gateway`
