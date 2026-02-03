# Phase 1: Repository Setup & Package Integration

**Status**: 🟡 In Progress  
**Started**: 2025-11-08  
**Duration**: Week 1-2  
**Target Completion**: TBD

---

## 📋 Overview

Phase 1 focuses on setting up the foundation for the Redeem-X project by:
1. Initializing the repository structure
2. Copying and integrating packages from the production `x-change` system
3. Configuring local development environment with Laravel Herd
4. Setting up database and running initial migrations
5. Verifying all packages work correctly

---

## ✅ Prerequisites

Before starting Phase 1, ensure you have:

- [x] PHP 8.3+ installed
- [x] Composer installed
- [x] Node.js 20+ installed
- [x] Laravel Herd running
- [x] Git configured with GitHub access
- [x] PhpStorm (recommended) or VS Code
- [x] Access to production `x-change` codebase at `/Users/rli/PhpstormProjects/x-change`

---

## 📦 Step-by-Step Implementation

### Step 1: Verify Current Repository

**Location**: `/Users/rli/PhpstormProjects/redeem-x`

```bash
cd /Users/rli/PhpstormProjects/redeem-x

# Verify Laravel installation
php artisan --version
# Should show: Laravel Framework 12.x.x

# Verify Herd is serving the app
open http://redeem-x.test
# Should load the Laravel welcome page
```

**Status**: ✅ Complete

---

### Step 2: Copy Packages from x-change

Copy the mono-repo packages exactly as they are from the production system.

```bash
# Create packages directory if it doesn't exist
mkdir -p /Users/rli/PhpstormProjects/redeem-x/packages

# Copy the entire lbhurtado package directory
cp -R /Users/rli/PhpstormProjects/x-change/packages/lbhurtado \
      /Users/rli/PhpstormProjects/redeem-x/packages/

# Verify the copy
ls -la /Users/rli/PhpstormProjects/redeem-x/packages/lbhurtado
```

**Expected Output:**
```
cash/
contact/
model-channel/
model-input/
money-issuer/
omnichannel/
payment-gateway/
voucher/
wallet/
```

**Status**: ⬜ Pending

---

### Step 3: Configure Composer for Path Repositories

Edit `composer.json` to add path repositories for local package development.

**File**: `composer.json`

**Add to the root object** (before "require" section):
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/lbhurtado/*",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

**Add to "require" section:**
```json
{
    "require": {
        "php": "^8.2",
        "inertiajs/inertia-laravel": "^2.0",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1",
        "laravel/workos": "^0.1.0",
        "laravel/wayfinder": "^0.1.9",
        "lbhurtado/cash": "@dev",
        "lbhurtado/contact": "@dev",
        "lbhurtado/model-channel": "@dev",
        "lbhurtado/model-input": "@dev",
        "lbhurtado/money-issuer": "@dev",
        "lbhurtado/omnichannel": "@dev",
        "lbhurtado/payment-gateway": "@dev",
        "lbhurtado/voucher": "@dev",
        "lbhurtado/wallet": "@dev"
    }
}
```

**Status**: ⬜ Pending

---

### Step 4: Install Package Dependencies

```bash
cd /Users/rli/PhpstormProjects/redeem-x

# Update Composer dependencies
composer update

# This will:
# 1. Symlink local packages from packages/lbhurtado/*
# 2. Install their dependencies
# 3. Register their service providers
```

**Expected Output:**
```
Loading composer repositories with package information
Updating dependencies
Lock file operations: 9 installs, 0 updates, 0 removals
  - Installing lbhurtado/cash (dev-main)
  - Installing lbhurtado/contact (dev-main)
  - Installing lbhurtado/model-channel (dev-main)
  - Installing lbhurtado/model-input (dev-main)
  - Installing lbhurtado/money-issuer (dev-main)
  - Installing lbhurtado/omnichannel (dev-main)
  - Installing lbhurtado/payment-gateway (dev-main)
  - Installing lbhurtado/voucher (dev-main)
  - Installing lbhurtado/wallet (dev-main)
Writing lock file
Generating optimized autoload files
```

**Troubleshooting:**
If you encounter dependency conflicts:
```bash
# Check what's conflicting
composer why-not lbhurtado/voucher

# If needed, update minimum stability
composer config minimum-stability dev
composer config prefer-stable true
```

**Status**: ⬜ Pending

---

### Step 5: Verify Herd Configuration

Ensure Laravel Herd is properly serving the application.

```bash
# Check if Herd is running
herd status

# Verify the link
herd links
# Should show: redeem-x -> /Users/rli/PhpstormProjects/redeem-x

# If not linked, create the link
herd link redeem-x

# Open in browser
open http://redeem-x.test
```

**Expected Result**: Laravel welcome page loads successfully

**Status**: ⬜ Pending

---

### Step 6: Configure Environment

Update `.env` file with proper settings for Herd and package integration.

**File**: `.env`

**Update these values:**
```env
APP_NAME="Redeem-X"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://redeem-x.test

# Database - SQLite for development
DB_CONNECTION=sqlite
DB_DATABASE=/Users/rli/PhpstormProjects/redeem-x/database/database.sqlite

# WorkOS (if using)
WORKOS_CLIENT_ID=
WORKOS_API_KEY=
WORKOS_REDIRECT_URL=http://redeem-x.test/authenticate

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Vite
VITE_APP_NAME="${APP_NAME}"
```

**Status**: ⬜ Pending

---

### Step 7: Database Setup

Create and configure the SQLite database.

```bash
cd /Users/rli/PhpstormProjects/redeem-x

# Create SQLite database file
touch database/database.sqlite

# Verify it exists
ls -la database/database.sqlite
```

**Status**: ⬜ Pending

---

### Step 8: Run Package Migrations

Run migrations from all installed packages.

```bash
# Check what migrations will run
php artisan migrate:status

# Run migrations
php artisan migrate

# Expected migrations from packages:
# - vouchers table (from lbhurtado/voucher)
# - wallets table (from lbhurtado/wallet)
# - cash_transactions table (from lbhurtado/cash)
# - contacts table (from lbhurtado/contact)
# - payment_gateway configs (from lbhurtado/payment-gateway)
# - money_issuer transactions (from lbhurtado/money-issuer)
# Plus standard Laravel migrations
```

**If migrations fail**, check:
1. Package service providers are registered in `bootstrap/providers.php`
2. Package migrations are published: `php artisan vendor:publish --tag=migrations`
3. Database permissions are correct

**Status**: ⬜ Pending

---

### Step 9: Publish Package Assets

Publish configuration files and assets from packages.

```bash
# List what can be published
php artisan vendor:publish

# Publish all package configs
php artisan vendor:publish --tag=config

# Publish package migrations (if not auto-discovered)
php artisan vendor:publish --tag=migrations

# Publish package views (if any)
php artisan vendor:publish --tag=views
```

**Expected files in `config/`:**
- `voucher.php`
- `wallet.php`
- `money-issuer.php`
- `payment-gateway.php`
- `cash.php`
- And others from the packages

**Status**: ⬜ Pending

---

### Step 10: Seed Initial Data

Run seeders to populate development data.

```bash
# Check available seeders
php artisan db:seed --help

# Run default seeder
php artisan db:seed

# Or run specific package seeders
php artisan db:seed --class=VoucherSeeder
php artisan db:seed --class=WalletSeeder
```

**Status**: ⬜ Pending

---

### Step 11: Test Package Integration

Verify that packages are loaded and working correctly.

```bash
# Open Laravel Tinker
php artisan tinker
```

**In Tinker, test each package:**

```php
// Test Voucher package
use LBHurtado\Voucher\Models\Voucher;
Voucher::count(); // Should return 0 or more

// Test Wallet package
use LBHurtado\Wallet\Models\Wallet;
Wallet::count();

// Test Contact package
use LBHurtado\Contact\Models\Contact;
Contact::count();

// Test Money Issuer
use LBHurtado\MoneyIssuer\Facades\MoneyIssuer;
MoneyIssuer::driver(); // Should return default driver

// Exit tinker
exit
```

**If any errors occur:**
1. Check the package's `composer.json` for proper autoloading
2. Verify service providers are registered
3. Check for missing dependencies: `composer show lbhurtado/voucher`

**Status**: ⬜ Pending

---

### Step 12: Verify Frontend Assets

Ensure Vite and Vue 3 are working correctly.

```bash
# Install Node dependencies (if not already done)
npm install

# Run Vite dev server
npm run dev
```

**Expected Output:**
```
VITE v7.0.4  ready in 500 ms

  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
  ➜  press h + enter to show help
```

**Open browser to**: http://redeem-x.test  
**Expected**: Page loads with Vite HMR working

**Status**: ⬜ Pending

---

### Step 13: Create Package Documentation

Document the APIs and usage of each package for future reference.

```bash
# Create package docs directory
mkdir -p docs/packages

# For each package, create a doc file:
# - voucher.md
# - wallet.md
# - money-issuer.md
# - payment-gateway.md
# - cash.md
# - contact.md
# - model-channel.md
# - model-input.md
# - omnichannel.md
```

**Each package doc should include:**
1. Purpose and functionality
2. Models and relationships
3. Key methods and facades
4. Configuration options
5. Usage examples
6. Migration overview

**Status**: ⬜ Pending

---

### Step 14: Run Initial Tests

Verify the setup is working with tests.

```bash
# Run existing Laravel tests
php artisan test

# Should see:
# Tests:    X passed (X assertions)
# Duration: X.XXs
```

**If tests fail:**
1. Check database is properly configured for testing (phpunit.xml)
2. Ensure test database is migrated
3. Review test failures for package-related issues

**Status**: ⬜ Pending

---

### Step 15: Commit Initial Setup

Commit the setup to version control.

```bash
# Check status
git status

# Stage all changes
git add .

# Commit
git commit -m "feat: integrate lbhurtado packages from x-change

- Copy 9 packages from x-change mono-repo
- Configure Composer path repositories
- Setup SQLite database
- Run package migrations
- Verify package integration via tinker
- All packages loading successfully

Packages:
- lbhurtado/voucher
- lbhurtado/wallet
- lbhurtado/money-issuer
- lbhurtado/payment-gateway
- lbhurtado/cash
- lbhurtado/contact
- lbhurtado/model-channel
- lbhurtado/model-input
- lbhurtado/omnichannel"

# Push to GitHub
git push origin main
```

**Status**: ⬜ Pending

---

## 📊 Phase 1 Completion Checklist

| Task | Status | Notes |
|------|--------|-------|
| Repository initialized | ✅ | redeem-x created |
| Packages copied from x-change | ⬜ | Copy packages/lbhurtado/* |
| Composer configured | ⬜ | Path repositories added |
| Package dependencies installed | ⬜ | composer update |
| Herd configuration verified | ⬜ | http://redeem-x.test working |
| Environment configured | ⬜ | .env updated |
| SQLite database created | ⬜ | database.sqlite |
| Package migrations run | ⬜ | All tables created |
| Package assets published | ⬜ | Configs, views, etc. |
| Initial data seeded | ⬜ | Development data |
| Package integration tested | ⬜ | Tinker tests passed |
| Frontend assets verified | ⬜ | Vite HMR working |
| Package docs created | ⬜ | docs/packages/* |
| Tests passing | ⬜ | php artisan test |
| Changes committed | ⬜ | Git push |

---

## 🎯 Success Criteria

Phase 1 is complete when:

- [x] Repository is initialized and on GitHub
- [ ] All 9 packages copied and installed successfully
- [ ] `composer update` runs without errors
- [ ] `http://redeem-x.test` loads the application
- [ ] All package migrations complete successfully
- [ ] Tinker can instantiate models from all packages
- [ ] `npm run dev` starts Vite without errors
- [ ] Application loads with Vite HMR
- [ ] All tests pass
- [ ] Package documentation created
- [ ] Changes committed to version control

**Progress**: 1/11 complete (9%)

---

## ⚠️ Common Issues & Solutions

### Issue: Package not found after composer update

**Solution:**
```bash
# Clear composer cache
composer clear-cache

# Regenerate autoload files
composer dump-autoload

# Try update again
composer update
```

### Issue: Package service provider not registered

**Solution:**
Check `bootstrap/providers.php` includes:
```php
return [
    App\Providers\AppServiceProvider::class,
    LBHurtado\Voucher\VoucherServiceProvider::class,
    LBHurtado\Wallet\WalletServiceProvider::class,
    // ... other package providers
];
```

### Issue: Migration fails with table already exists

**Solution:**
```bash
# Rollback all migrations
php artisan migrate:rollback --step=100

# Fresh migrate
php artisan migrate:fresh
```

### Issue: Herd not serving domain

**Solution:**
```bash
# Restart Herd
herd restart

# Re-link the project
herd unlink redeem-x
herd link redeem-x

# Verify
herd links
```

---

## 📝 Notes

### 2025-11-08 - Setup Started
- Created Phase 1 documentation
- Verified repository initialization
- Ready to copy packages from x-change

### Package Dependencies
Note: Some packages may have dependencies on each other:
- `voucher` may depend on `wallet`
- `payment-gateway` likely depends on `money-issuer`
- `omnichannel` may depend on `contact`

These will be resolved during `composer update`.

---

## 🔜 Next Steps

After Phase 1 completion:
1. Move to Phase 2: Backend API Development
2. Create RESTful controllers for vouchers and wallets
3. Implement Sanctum authentication
4. Build payment gateway driver system

---

**Last Updated**: 2025-11-08  
**Phase Progress**: 9%  
**Next Milestone**: All packages installed and working
