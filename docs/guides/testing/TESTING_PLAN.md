# Testing Plan: Redeem-X

**Project**: Redeem-X  
**Testing Framework**: Pest PHP  
**Date**: 2025-11-08  
**Status**: Phase 1 - Foundation Testing

---

## 🎯 Testing Philosophy

**Test-Driven Development (TDD) Approach:**
1. ✅ Write tests BEFORE implementation
2. ✅ Tests define expected behavior
3. ✅ Run tests to confirm they fail (red)
4. ✅ Implement minimum code to pass (green)
5. ✅ Refactor while keeping tests green

**Coverage Goals:**
- **Phase 1**: >80% coverage on core package integration
- **Phase 2**: >75% coverage on API endpoints
- **Phase 3**: >70% coverage on frontend components (Vitest)
- **Overall**: >70% total coverage

---

## 📋 Phase 1: Foundation Tests

### Test Categories

1. **Package Integration Tests** - Verify all packages load and work
2. **Authentication Tests** - WorkOS + Sanctum integration
3. **Database Tests** - Migrations and model relationships
4. **Configuration Tests** - Environment and package configs

---

## 🧪 Test Suite Structure

```
tests/
├── Feature/
│   ├── PackageIntegration/
│   │   ├── VoucherPackageTest.php
│   │   ├── WalletPackageTest.php
│   │   ├── MoneyIssuerPackageTest.php
│   │   ├── PaymentGatewayPackageTest.php
│   │   ├── CashPackageTest.php
│   │   ├── ContactPackageTest.php
│   │   ├── ModelChannelPackageTest.php
│   │   ├── ModelInputPackageTest.php
│   │   └── OmnichannelPackageTest.php
│   ├── Authentication/
│   │   ├── WorkOSAuthenticationTest.php
│   │   ├── SanctumApiTokenTest.php
│   │   └── HybridAuthenticationTest.php
│   ├── Database/
│   │   ├── MigrationTest.php
│   │   └── ModelRelationshipTest.php
│   └── Configuration/
│       ├── EnvironmentTest.php
│       └── PackageConfigTest.php
├── Unit/
│   ├── Models/
│   │   ├── UserTest.php
│   │   ├── VoucherTest.php
│   │   └── WalletTest.php
│   └── Services/
│       └── MoneyIssuerTest.php
└── Pest.php
```

---

## ✅ Phase 1: Package Integration Tests

### Test 1: Voucher Package Integration

**File**: `tests/Feature/PackageIntegration/VoucherPackageTest.php`

```php
<?php

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;

test('voucher package is loaded and autoloaded', function () {
    expect(class_exists(Voucher::class))->toBeTrue();
});

test('voucher service provider is registered', function () {
    $providers = app()->getLoadedProviders();
    expect($providers)->toHaveKey('LBHurtado\Voucher\VoucherServiceProvider');
});

test('can create a voucher', function () {
    $user = User::factory()->create();
    
    $voucher = Voucher::create([
        'user_id' => $user->id,
        'amount' => 100,
        'code' => 'TEST-' . uniqid(),
    ]);
    
    expect($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->amount)->toBe(100)
        ->and($voucher->user_id)->toBe($user->id);
});

test('voucher belongs to user', function () {
    $user = User::factory()->create();
    
    $voucher = Voucher::create([
        'user_id' => $user->id,
        'amount' => 100,
        'code' => 'TEST-' . uniqid(),
    ]);
    
    expect($voucher->user)->toBeInstanceOf(User::class)
        ->and($voucher->user->id)->toBe($user->id);
});

test('can query user vouchers', function () {
    $user = User::factory()->create();
    
    Voucher::factory()->count(3)->create(['user_id' => $user->id]);
    
    expect($user->vouchers)->toHaveCount(3);
});
```

### Test 2: Wallet Package Integration

**File**: `tests/Feature/PackageIntegration/WalletPackageTest.php`

```php
<?php

use App\Models\User;
use LBHurtado\Wallet\Models\Wallet;

test('wallet package is loaded', function () {
    expect(class_exists(Wallet::class))->toBeTrue();
});

test('user can have wallet', function () {
    $user = User::factory()->create();
    
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 1000,
    ]);
    
    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->balance)->toBe(1000);
});

test('wallet balance can be updated', function () {
    $user = User::factory()->create();
    
    $wallet = Wallet::create([
        'user_id' => $user->id,
        'balance' => 1000,
    ]);
    
    $wallet->update(['balance' => 1500]);
    
    expect($wallet->fresh()->balance)->toBe(1500);
});
```

### Test 3: Money Issuer Package Integration

**File**: `tests/Feature/PackageIntegration/MoneyIssuerPackageTest.php`

```php
<?php

use LBHurtado\MoneyIssuer\Facades\MoneyIssuer;

test('money issuer package is loaded', function () {
    expect(class_exists(MoneyIssuer::class))->toBeTrue();
});

test('can get default money issuer driver', function () {
    $driver = MoneyIssuer::driver();
    
    expect($driver)->not->toBeNull();
});

test('money issuer config is loaded', function () {
    $config = config('money-issuer');
    
    expect($config)->toBeArray()
        ->and($config)->toHaveKey('default')
        ->and($config)->toHaveKey('drivers');
});
```

### Test 4: Payment Gateway Package Integration

**File**: `tests/Feature/PackageIntegration/PaymentGatewayPackageTest.php`

```php
<?php

test('payment gateway package is loaded', function () {
    // Check if package classes exist
    expect(interface_exists('LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface'))
        ->toBeTrue();
});

test('payment gateway config is loaded', function () {
    $config = config('payment-gateway');
    
    expect($config)->toBeArray();
});

test('can instantiate payment gateway drivers', function () {
    $drivers = config('payment-gateway.drivers', []);
    
    expect($drivers)->toBeArray();
});
```

### Tests 5-9: Remaining Packages

Create similar tests for:
- **CashPackageTest.php** - Cash transaction functionality
- **ContactPackageTest.php** - Contact management
- **ModelChannelPackageTest.php** - Channel abstraction
- **ModelInputPackageTest.php** - Input handling
- **OmnichannelPackageTest.php** - Multi-channel communication

---

## 🔐 Phase 1: Authentication Tests

### Test 10: WorkOS Authentication

**File**: `tests/Feature/Authentication/WorkOSAuthenticationTest.php`

```php
<?php

use App\Models\User;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

test('workos package is installed', function () {
    expect(class_exists(ValidateSessionWithWorkOS::class))->toBeTrue();
});

test('unauthenticated users are redirected to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('authenticated users can access dashboard', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('workos login route exists', function () {
    $response = $this->get('/login');
    
    // WorkOS will redirect to AuthKit
    expect($response->status())->toBe(302);
});
```

### Test 11: Sanctum API Token

**File**: `tests/Feature/Authentication/SanctumApiTokenTest.php`

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('sanctum package is installed', function () {
    expect(trait_exists('Laravel\Sanctum\HasApiTokens'))->toBeTrue();
});

test('user model has api tokens trait', function () {
    $user = new User();
    
    expect($user)->toHaveMethod('createToken')
        ->and($user)->toHaveMethod('tokens');
});

test('user can create api token', function () {
    $user = User::factory()->create();
    
    $token = $user->createToken('Test Token');
    
    expect($token)->not->toBeNull()
        ->and($token->plainTextToken)->toBeString();
});

test('api token can have abilities', function () {
    $user = User::factory()->create();
    
    $token = $user->createToken('Test Token', ['voucher:create', 'wallet:read']);
    
    expect($token->accessToken->abilities)
        ->toContain('voucher:create')
        ->toContain('wallet:read');
});

test('can authenticate with sanctum token', function () {
    $user = User::factory()->create();
    
    Sanctum::actingAs($user, ['*']);
    
    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson(['user' => ['id' => $user->id]]);
});

test('unauthenticated api requests are rejected', function () {
    $this->getJson('/api/v1/vouchers')
        ->assertUnauthorized();
});
```

### Test 12: Hybrid Authentication

**File**: `tests/Feature/Authentication/HybridAuthenticationTest.php`

```php
<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('web routes use workos authentication', function () {
    $user = User::factory()->create();
    
    // Web route with WorkOS session
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('api routes use sanctum authentication', function () {
    $user = User::factory()->create();
    
    // API route with Sanctum token
    Sanctum::actingAs($user, ['voucher:read']);
    
    $this->getJson('/api/v1/vouchers')
        ->assertOk();
});

test('sanctum token cannot access web routes', function () {
    $user = User::factory()->create();
    
    Sanctum::actingAs($user);
    
    // Sanctum tokens don't work for web routes
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('workos session cannot access api routes without token', function () {
    $user = User::factory()->create();
    
    // Acting as user (WorkOS session)
    $this->actingAs($user);
    
    // But API routes need Sanctum token
    $this->getJson('/api/v1/vouchers')
        ->assertUnauthorized();
});
```

---

## 💾 Phase 1: Database Tests

### Test 13: Migration Test

**File**: `tests/Feature/Database/MigrationTest.php`

```php
<?php

use Illuminate\Support\Facades\Schema;

test('personal access tokens table exists', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();
});

test('users table has required columns', function () {
    expect(Schema::hasColumns('users', [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('vouchers table exists', function () {
    expect(Schema::hasTable('vouchers'))->toBeTrue();
});

test('wallets table exists', function () {
    expect(Schema::hasTable('wallets'))->toBeTrue();
});

test('all package migrations ran successfully', function () {
    $tables = [
        'personal_access_tokens', // Sanctum
        'users',
        'vouchers',
        'wallets',
        // Add other expected tables from packages
    ];
    
    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});
```

### Test 14: Model Relationship Test

**File**: `tests/Feature/Database/ModelRelationshipTest.php`

```php
<?php

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Models\Wallet;

test('user has vouchers relationship', function () {
    $user = User::factory()->create();
    
    expect($user->vouchers)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
});

test('user has wallet relationship', function () {
    $user = User::factory()->create();
    
    expect(method_exists($user, 'wallet'))->toBeTrue();
});

test('voucher belongs to user', function () {
    $user = User::factory()->create();
    
    $voucher = Voucher::factory()->create(['user_id' => $user->id]);
    
    expect($voucher->user)->toBeInstanceOf(User::class)
        ->and($voucher->user->id)->toBe($user->id);
});
```

---

## ⚙️ Phase 1: Configuration Tests

### Test 15: Environment Test

**File**: `tests/Feature/Configuration/EnvironmentTest.php`

```php
<?php

test('app is in testing environment', function () {
    expect(app()->environment())->toBe('testing');
});

test('app url is configured', function () {
    expect(config('app.url'))->not->toBeNull();
});

test('database is using sqlite for testing', function () {
    expect(config('database.default'))->toBe('sqlite');
});

test('sanctum stateful domains are configured', function () {
    $domains = config('sanctum.stateful');
    
    expect($domains)->toBeArray()
        ->and($domains)->toContain('localhost');
});

test('workos credentials are configured', function () {
    // In testing, these might be null, but keys should exist
    expect(config('workos'))->toHaveKey('client_id')
        ->and(config('workos'))->toHaveKey('api_key');
});
```

### Test 16: Package Config Test

**File**: `tests/Feature/Configuration/PackageConfigTest.php`

```php
<?php

test('all package configs are loaded', function () {
    $expectedConfigs = [
        'voucher',
        'wallet',
        'money-issuer',
        'payment-gateway',
        'cash',
        'contact',
        // Add others as needed
    ];
    
    foreach ($expectedConfigs as $config) {
        expect(config($config))->not->toBeNull();
    }
});

test('money issuer has default driver', function () {
    $default = config('money-issuer.default');
    
    expect($default)->not->toBeNull();
});

test('payment gateway has drivers configured', function () {
    $drivers = config('payment-gateway.drivers');
    
    expect($drivers)->toBeArray();
});
```

---

## 🚀 Running Tests

### Run All Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run with coverage minimum threshold
php artisan test --coverage --min=80
```

### Run Specific Test Suites

```bash
# Run only package integration tests
php artisan test --testsuite=Feature --filter=PackageIntegration

# Run only authentication tests
php artisan test --testsuite=Feature --filter=Authentication

# Run specific test file
php artisan test tests/Feature/PackageIntegration/VoucherPackageTest.php

# Run specific test
php artisan test --filter=voucher_package_is_loaded
```

### Run Tests in Parallel

```bash
# Install parallel plugin
composer require --dev brianium/paratest

# Run tests in parallel
php artisan test --parallel
```

---

## 📊 Test Coverage Goals

### Phase 1 Coverage Targets

| Package | Target Coverage | Critical Paths |
|---------|----------------|----------------|
| Voucher | >85% | Create, redeem, cancel |
| Wallet | >85% | Balance, top-up, transactions |
| Money Issuer | >80% | Driver loading, disbursement |
| Payment Gateway | >80% | Gateway drivers, API calls |
| Cash | >75% | Transaction handling |
| Contact | >75% | Contact management |
| Authentication | >90% | WorkOS + Sanctum integration |
| Configuration | 100% | All configs loaded |

---

## ✅ Pre-Implementation Checklist

Before starting Phase 1 implementation, ensure:

- [ ] All test files are created
- [ ] Tests are written and failing (red)
- [ ] Test structure follows Pest conventions
- [ ] Database factories are ready
- [ ] Test environment is configured
- [ ] Coverage tools are installed
- [ ] CI/CD will run tests on push

---

## 🔄 TDD Workflow

### For Each Feature:

1. **Write Test** (Red)
   ```bash
   php artisan test --filter=feature_name
   # Expected: FAIL
   ```

2. **Implement Minimum Code** (Green)
   ```bash
   # Write minimal code to pass test
   php artisan test --filter=feature_name
   # Expected: PASS
   ```

3. **Refactor** (Clean)
   ```bash
   # Improve code quality
   php artisan test --filter=feature_name
   # Expected: PASS
   ```

4. **Commit**
   ```bash
   git add .
   git commit -m "feat: add feature_name with tests"
   ```

---

## 📝 Test Documentation Standards

### Test Naming Convention

```php
// Good ✅
test('user can create voucher with valid data')
test('voucher requires amount to be positive')
test('api token can have multiple abilities')

// Bad ❌
test('test1')
test('voucher test')
test('check_if_works')
```

### Test Structure (AAA Pattern)

```php
test('example test', function () {
    // Arrange - Set up test data
    $user = User::factory()->create();
    
    // Act - Execute the action
    $voucher = $user->vouchers()->create([
        'amount' => 100,
        'code' => 'TEST-123',
    ]);
    
    // Assert - Verify the result
    expect($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->amount)->toBe(100);
});
```

---

## 🎯 Next Steps

1. **Create Test Files** - Use the structure above
2. **Run Tests** - Confirm they all fail (no implementation yet)
3. **Review Test Coverage** - Ensure all critical paths tested
4. **Begin Implementation** - Follow TDD workflow
5. **Monitor Coverage** - Keep >80% throughout Phase 1

---

**Last Updated**: 2025-11-08  
**Status**: Ready for Phase 1 Implementation  
**Next Review**: After first test implementation
