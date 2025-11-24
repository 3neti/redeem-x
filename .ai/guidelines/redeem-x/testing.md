# Testing Patterns

## Overview
Redeem-X uses Pest v4 for all testing. This document covers testing conventions, factory usage, common patterns, and strategies for testing vouchers, cash entities, payments, and integrations.

## Test Organization

### Directory Structure
```
tests/
├── Unit/           # Unit tests for isolated components
├── Feature/        # Feature tests for HTTP endpoints and workflows
├── Browser/        # Browser tests using Pest v4 (UI testing)
└── Fixtures/       # Test data files (location.json, signature.png, selfie.jpg)
```

### Naming Conventions
- Test files: `{FeatureName}Test.php`
- Test functions: Use descriptive `it('...')` or `test('...')` syntax
- Group related tests using `describe()` blocks

### Example Test Structure
```php
<?php

use App\Models\User;
use LBHurtado\Voucher\Models\Voucher;

describe('Voucher Redemption', function () {
    it('can redeem a valid voucher', function () {
        $voucher = Voucher::factory()->create();
        
        $response = $this->post('/redeem', [
            'code' => $voucher->code,
        ]);
        
        $response->assertSuccessful();
    });
    
    it('rejects expired vouchers', function () {
        $voucher = Voucher::factory()->expired()->create();
        
        $response = $this->post('/redeem', [
            'code' => $voucher->code,
        ]);
        
        $response->assertForbidden();
    });
});
```

## Factory Patterns

### Using Factories
Always use factories to create test data:

```php
// Create single instance
$user = User::factory()->create();
$voucher = Voucher::factory()->create();
$cash = Cash::factory()->create();

// Create with attributes
$voucher = Voucher::factory()->create([
    'amount' => 1000,
    'currency' => 'PHP',
]);

// Create multiple
$vouchers = Voucher::factory()->count(5)->create();

// Use factory states
$expiredCash = Cash::factory()->expired()->create();
$disbursedCash = Cash::factory()->disbursed()->create();
```

### Common Factory States
Check factory files for available states before manually setting up models:

**Voucher Factory States:**
- `expired()` - Creates expired voucher
- `withCash()` - Creates voucher with associated cash
- `redeemed()` - Creates already redeemed voucher

**Cash Factory States:**
- `expired()` - Sets expires_on to past date
- `disbursed()` - Sets status to DISBURSED
- `minted()` - Sets status to MINTED (default)

**User Factory States:**
- `withWallet()` - Creates user with initialized wallet
- `withBalance($amount)` - Creates user with specific wallet balance

### Creating Complex Scenarios
```php
// Voucher with cash and specific instructions
$voucher = Voucher::factory()
    ->withCash()
    ->create([
        'metadata' => [
            'instructions' => VoucherInstructionsData::from([
                'cash' => [
                    'amount' => 500,
                    'currency' => 'PHP',
                ],
                'inputs' => ['MOBILE', 'EMAIL'],
                'validations' => [
                    'mobile' => ['required', 'regex:/^09\d{9}$/'],
                ],
            ]),
        ],
    ]);
```

## Testing Voucher System

### Voucher Generation
```php
it('generates vouchers with correct instructions', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/vouchers/generate', [
            'cash' => ['amount' => 1000, 'currency' => 'PHP'],
            'count' => 10,
            'prefix' => 'TEST',
        ])
        ->assertSuccessful();
    
    expect(Voucher::count())->toBe(10);
    expect(Voucher::first()->code)->toStartWith('TEST');
});
```

### Voucher Redemption
```php
it('redeems voucher and collects inputs', function () {
    $voucher = Voucher::factory()->withCash()->create();
    
    $response = $this->post('/redeem', [
        'code' => $voucher->code,
        'mobile' => '09173011987',
        'email' => 'test@example.com',
    ]);
    
    $response->assertSuccessful();
    
    $voucher->refresh();
    expect($voucher->isRedeemed())->toBeTrue();
    expect($voucher->redemption->mobile)->toBe('09173011987');
});
```

### Campaign System
```php
it('creates campaign with instructions', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->post('/campaigns', [
            'name' => 'Test Campaign',
            'instructions' => [
                'cash' => ['amount' => 500],
                'inputs' => ['MOBILE'],
            ],
        ])
        ->assertSuccessful();
    
    $campaign = $user->campaigns()->first();
    expect($campaign->name)->toBe('Test Campaign');
});
```

## Testing Cash Entities

### Cash Creation and Status
```php
it('creates cash with correct attributes', function () {
    $cash = Cash::factory()->create([
        'amount' => Money::of(1000, 'PHP'),
        'secret' => 'test-secret',
    ]);
    
    expect($cash->amount->getMinorAmount()->toInt())->toBe(100000);
    expect($cash->verifySecret('test-secret'))->toBeTrue();
});

it('tracks status changes', function () {
    $cash = Cash::factory()->create();
    
    $cash->setStatus(CashStatus::DISBURSED, 'Funds sent');
    
    expect($cash->hasStatus(CashStatus::DISBURSED))->toBeTrue();
    expect($cash->getCurrentStatus()->reason)->toBe('Funds sent');
});
```

### Expiration and Redemption
```php
it('prevents redemption of expired cash', function () {
    $cash = Cash::factory()->expired()->create([
        'secret' => 'test-secret',
    ]);
    
    expect($cash->canRedeem('test-secret'))->toBeFalse();
});

it('allows redemption of valid cash', function () {
    $cash = Cash::factory()->create([
        'secret' => 'test-secret',
        'expires_on' => now()->addWeek(),
    ]);
    
    expect($cash->canRedeem('test-secret'))->toBeTrue();
});
```

## Testing Payment Gateway

### Mocking Payment Gateway
```php
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Mockery;

it('disburses funds via payment gateway', function () {
    $gateway = Mockery::mock(PaymentGatewayInterface::class);
    $gateway->shouldReceive('disburse')
        ->once()
        ->with(100, '09173011987', 'GXCHPHM2XXX', 'INSTAPAY')
        ->andReturn(true);
    
    $this->app->instance(PaymentGatewayInterface::class, $gateway);
    
    // Test disbursement flow
    $voucher = Voucher::factory()->withCash()->create();
    $this->post('/redeem', ['code' => $voucher->code]);
    
    // Assert disbursement was called
});
```

### Testing with Fake Gateway
```php
it('uses fake gateway in test mode', function () {
    config(['payment-gateway.use_fake' => true]);
    
    $voucher = Voucher::factory()->withCash()->create();
    
    $response = $this->post('/redeem', ['code' => $voucher->code]);
    
    $response->assertSuccessful();
    // No actual API calls made
});
```

## Testing Top-Up System

### Top-Up Initiation
```php
it('initiates top-up successfully', function () {
    $user = User::factory()->withWallet()->create();
    
    $this->actingAs($user)
        ->post('/topup', [
            'amount' => 500,
            'institution' => 'GCASH',
        ])
        ->assertRedirect();
    
    $topUp = $user->getTopUps()->first();
    expect($topUp->status)->toBe('PENDING');
    expect($topUp->amount)->toBe(500);
});
```

### Webhook Testing
```php
it('processes webhook and credits wallet', function () {
    $user = User::factory()->withWallet()->create();
    $topUp = TopUp::factory()->pending()->create([
        'user_id' => $user->id,
        'amount' => 500,
        'reference' => 'TOPUP-TEST123',
    ]);
    
    $initialBalance = $user->wallet->balance;
    
    $this->post('/webhooks/netbank/payment', [
        'reference' => 'TOPUP-TEST123',
        'status' => 'PAID',
    ]);
    
    $topUp->refresh();
    $user->wallet->refresh();
    
    expect($topUp->status)->toBe('PAID');
    expect($user->wallet->balance)->toBe($initialBalance + 500);
});
```

## Testing Notifications

### Faking Notifications
```php
use Illuminate\Support\Facades\Notification;

it('sends redemption notification', function () {
    Notification::fake();
    
    $voucher = Voucher::factory()->withCash()->create();
    
    $this->post('/redeem', [
        'code' => $voucher->code,
        'mobile' => '09173011987',
    ]);
    
    Notification::assertSentTo(
        [$voucher->owner],
        RedemptionNotification::class
    );
});
```

### Testing Templates
```php
it('renders template with correct variables', function () {
    $templateProcessor = new TemplateProcessor();
    
    $template = 'Voucher {{ code }} of {{ formatted_amount }}';
    $data = [
        'code' => 'TEST123',
        'formatted_amount' => '₱1,000.00',
    ];
    
    $result = $templateProcessor->process($template, $data);
    
    expect($result)->toBe('Voucher TEST123 of ₱1,000.00');
});
```

## Testing with Queues

### Faking Queues
```php
use Illuminate\Support\Facades\Queue;

it('dispatches job to queue', function () {
    Queue::fake();
    
    $voucher = Voucher::factory()->create();
    
    $this->post('/redeem', ['code' => $voucher->code]);
    
    Queue::assertPushed(ProcessRedemptionJob::class);
});
```

### Testing Queue Workers
```php
it('processes queued job successfully', function () {
    $voucher = Voucher::factory()->withCash()->create();
    
    ProcessRedemptionJob::dispatch($voucher);
    
    // Process queue
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
    ]);
    
    $voucher->refresh();
    expect($voucher->isRedeemed())->toBeTrue();
});
```

## Testing Events

### Faking Events
```php
use Illuminate\Support\Facades\Event;

it('fires redemption event', function () {
    Event::fake();
    
    $voucher = Voucher::factory()->create();
    
    $this->post('/redeem', ['code' => $voucher->code]);
    
    Event::assertDispatched(VoucherRedeemed::class);
});
```

## Browser Testing (Pest v4)

### Basic Browser Test
```php
it('displays voucher list', function () {
    $user = User::factory()->create();
    Voucher::factory()->count(3)->create(['user_id' => $user->id]);
    
    $this->actingAs($user);
    
    $page = visit('/vouchers');
    
    $page->assertSee('Vouchers')
        ->assertNoJavascriptErrors();
});
```

### Interactive Browser Test
```php
it('can generate voucher via UI', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $page = visit('/vouchers/generate');
    
    $page->fill('cash.amount', '1000')
        ->fill('count', '5')
        ->fill('prefix', 'TEST')
        ->click('Generate Vouchers')
        ->assertSee('5 vouchers generated');
    
    expect(Voucher::count())->toBe(5);
});
```

### Testing Inertia Pages
```php
it('loads voucher page with correct props', function () {
    $user = User::factory()->create();
    $vouchers = Voucher::factory()->count(3)->create(['user_id' => $user->id]);
    
    $this->actingAs($user)
        ->get('/vouchers')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vouchers/Index')
            ->has('vouchers', 3)
            ->where('vouchers.0.code', $vouchers->first()->code)
        );
});
```

## Test Data Management

### Using Fixtures
```php
it('loads location fixture', function () {
    $location = json_decode(
        file_get_contents(base_path('tests/Fixtures/location.json')),
        true
    );
    
    expect($location)->toHaveKeys(['latitude', 'longitude']);
});
```

### Shared Setup with Datasets
```php
dataset('voucher_amounts', [
    'small' => 100,
    'medium' => 1000,
    'large' => 10000,
]);

it('generates voucher with amount', function (int $amount) {
    $voucher = Voucher::factory()->create([
        'metadata' => [
            'instructions' => [
                'cash' => ['amount' => $amount],
            ],
        ],
    ]);
    
    expect($voucher->cash->amount->getAmount())->toBe($amount);
})->with('voucher_amounts');
```

## Database Considerations

### Using RefreshDatabase
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates voucher in database', function () {
    $voucher = Voucher::factory()->create();
    
    $this->assertDatabaseHas('vouchers', [
        'id' => $voucher->id,
        'code' => $voucher->code,
    ]);
});
```

### Testing Transactions
```php
it('rolls back on validation failure', function () {
    $initialCount = Voucher::count();
    
    try {
        DB::transaction(function () {
            Voucher::factory()->create();
            throw new \Exception('Rollback test');
        });
    } catch (\Exception $e) {
        // Expected
    }
    
    expect(Voucher::count())->toBe($initialCount);
});
```

## Best Practices

### 1. Test Independence
Each test should be independent and not rely on other tests:
```php
// Good
it('creates voucher', function () {
    $voucher = Voucher::factory()->create();
    expect($voucher->exists)->toBeTrue();
});

// Bad - relies on previous test
it('updates voucher', function () {
    $voucher = Voucher::first(); // Assumes voucher exists
    $voucher->update(['amount' => 2000]);
});
```

### 2. Use Descriptive Names
```php
// Good
it('prevents redemption when voucher is expired')
it('sends email notification after successful redemption')

// Bad
it('test1')
it('works')
```

### 3. Test One Concept Per Test
```php
// Good - focused on single behavior
it('validates mobile number format', function () {
    $response = $this->post('/redeem', [
        'code' => 'TEST123',
        'mobile' => 'invalid',
    ]);
    
    $response->assertJsonValidationErrors(['mobile']);
});

// Bad - testing multiple things
it('validates and redeems', function () {
    // Validation test
    // Redemption test
    // Notification test
});
```

### 4. Mock External Services
Always mock external APIs (payment gateways, SMS providers) in tests:
```php
use function Pest\Laravel\mock;

it('sends SMS via EngageSpark', function () {
    $smsMock = mock(SMSService::class);
    $smsMock->shouldReceive('send')
        ->once()
        ->with('09173011987', Mockery::any())
        ->andReturn(true);
    
    // Test code
});
```

### 5. Use Assertions Effectively
```php
// Multiple assertions for thoroughness
it('creates complete voucher', function () {
    $voucher = Voucher::factory()->create();
    
    expect($voucher->code)->toBeString();
    expect($voucher->code)->toHaveLength(10);
    expect($voucher->isRedeemed())->toBeFalse();
    expect($voucher->cash)->not->toBeNull();
});
```

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific File
```bash
php artisan test tests/Feature/VoucherTest.php
```

### Run Specific Test
```bash
php artisan test --filter=test_voucher_redemption
```

### Run with Coverage
```bash
php artisan test --coverage
```

### Run Browser Tests
```bash
php artisan test tests/Browser/
```
