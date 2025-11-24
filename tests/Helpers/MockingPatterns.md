# Laravel Testing: Mocking Patterns Guide

This guide documents mocking patterns used in the redeem-x test suite. Mocking is the hardest part of testing - use this as a reference.

## Table of Contents

1. [Facade Mocking](#facade-mocking) (Easiest)
2. [Service Container Mocking](#service-container-mocking) (Intermediate)
3. [Payment Gateway Mocking](#payment-gateway-mocking) (Advanced)
4. [Notification Channel Mocking](#notification-channel-mocking)
5. [Config and Environment Mocking](#config-and-environment-mocking)
6. [Event and Queue Mocking](#event-and-queue-mocking)
7. [Time and Date Mocking](#time-and-date-mocking)
8. [Database Mocking](#database-mocking)
9. [Common Pitfalls](#common-pitfalls)

---

## Facade Mocking

Laravel's facade fake methods are the **easiest and cleanest** way to mock external services.

### HTTP Client (External API Calls)

```php
use Illuminate\Support\Facades\Http;

// Basic fake - all HTTP requests return 200
Http::fake();

// Pattern-based responses
Http::fake([
    'webhook.site/*' => Http::response(['success' => true], 200),
    'api.example.com/*' => Http::response(['error' => 'Failed'], 500),
    '*' => Http::response(['data' => 'default'], 200), // Catch-all
]);

// Sequence of responses (for retries)
Http::fake([
    'api.example.com/*' => Http::sequence()
        ->push(['status' => 'pending'], 200)
        ->push(['status' => 'complete'], 200),
]);

// Simulate timeout
Http::fake([
    'slow.api.com/*' => function () {
        sleep(2);
        return Http::response(['error' => 'timeout'], 408);
    },
]);

// Assertions
Http::assertSent(function ($request) {
    return $request->url() === 'https://webhook.site/test' &&
           $request->method() === 'POST' &&
           $request['event'] === 'voucher.redeemed';
});

Http::assertSentCount(3);
Http::assertNothingSent();
```

### Notifications

```php
use Illuminate\Support\Facades\Notification;

// Fake all notifications
Notification::fake();

// Assert notification sent to specific notifiable
Notification::assertSentTo(
    $user,
    SendFeedbacksNotification::class,
    function ($notification, $channels) {
        return in_array('mail', $channels);
    }
);

// Assert notification sent to anonymous notifiable (array)
Notification::assertSentTo(
    ['email' => 'admin@example.com'],
    SendFeedbacksNotification::class
);

// Assert notification count
Notification::assertSentTimes(SendFeedbacksNotification::class, 3);

// Assert no notifications sent
Notification::assertNothingSent();
```

### Mail

```php
use Illuminate\Support\Facades\Mail;

// Fake all mail
Mail::fake();

// Assert mail sent
Mail::assertSent(VoucherRedeemedMail::class, function ($mail) {
    return $mail->hasTo('user@example.com') &&
           $mail->voucher->code === 'TEST-1234';
});

// Assert mail queued (not sent immediately)
Mail::assertQueued(VoucherRedeemedMail::class);

// Assert mail count
Mail::assertSentTimes(VoucherRedeemedMail::class, 5);

// Assert no mail sent
Mail::assertNothingSent();
```

### Queue

```php
use Illuminate\Support\Facades\Queue;

// Fake queue
Queue::fake();

// Assert job pushed
Queue::assertPushed(SendNotificationJob::class, function ($job) {
    return $job->voucher->id === 1;
});

// Assert job pushed to specific queue
Queue::assertPushedOn('notifications', SendNotificationJob::class);

// Assert job pushed with delay
Queue::assertPushed(SendNotificationJob::class, function ($job) {
    return $job->delay->totalSeconds === 300; // 5 minutes
});

// Assert job not pushed
Queue::assertNotPushed(SendNotificationJob::class);
```

### Events

```php
use Illuminate\Support\Facades\Event;

// Fake all events
Event::fake();

// Fake specific events only
Event::fake([
    DisburseInputPrepared::class,
    VoucherRedeemed::class,
]);

// Assert event dispatched
Event::assertDispatched(DisburseInputPrepared::class, function ($event) {
    return $event->voucher->code === 'TEST';
});

// Assert event dispatched times
Event::assertDispatchedTimes(VoucherRedeemed::class, 3);

// Assert event not dispatched
Event::assertNotDispatched(DisburseInputPrepared::class);

// Assert listener attached
Event::assertListening(
    VoucherRedeemed::class,
    SendFeedbacksListener::class
);
```

### Storage (File System)

```php
use Illuminate\Support\Facades\Storage;

// Fake storage disk
Storage::fake('local');

// Use storage normally
Storage::put('vouchers/test.json', json_encode(['code' => 'TEST']));

// Assertions
Storage::assertExists('vouchers/test.json');
Storage::assertMissing('vouchers/missing.json');

// Read faked file
$content = Storage::get('vouchers/test.json');
```

---

## Service Container Mocking

Mock services bound to Laravel's service container using Mockery.

### Basic Interface Mocking

```php
use Mockery;

// 1. Create mock
$mockGateway = Mockery::mock(PaymentGatewayInterface::class);

// 2. Define expectations
$mockGateway->shouldReceive('disburse')
    ->once()
    ->with(Mockery::type('float'), Mockery::type('string'))
    ->andReturn(true);

// 3. Bind to container
app()->instance(PaymentGatewayInterface::class, $mockGateway);

// 4. Use in code
$gateway = app(PaymentGatewayInterface::class);
$result = $gateway->disburse(100.0, '09171234567');
```

### Mock with Specific Arguments

```php
$mockGateway->shouldReceive('disburse')
    ->once()
    ->with(
        500.0,                              // Exact amount
        '09171234567',                      // Exact mobile
        'GCASH',                            // Exact bank code
        Mockery::any()                      // Any rail (INSTAPAY/PESONET)
    )
    ->andReturn(true);
```

### Mock with Return Objects

```php
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;

$mockGateway->shouldReceive('disburse')
    ->andReturn(
        DisburseResponseData::from([
            'transaction_id' => 'TEST-TXN-123',
            'uuid' => 'test-uuid-456',
            'status' => 'success',
        ])
    );
```

### Spy Pattern (Verify Without Stubbing)

```php
// Use spy when you want to verify calls but still use real methods
$spy = Mockery::spy(PaymentGatewayInterface::class);
app()->instance(PaymentGatewayInterface::class, $spy);

// ... run code ...

// Verify after the fact
$spy->shouldHaveReceived('disburse')
    ->once()
    ->with(100.0, Mockery::any());
```

### Partial Mocking

```php
// Mock specific methods, let others work normally
$mock = Mockery::mock(OmnipayPaymentGateway::class)->makePartial();

// Only mock authenticate method
$mock->shouldReceive('authenticate')
    ->andReturn(true);

// All other methods use real implementation
```

---

## Payment Gateway Mocking

Specific patterns for mocking the payment gateway in this project.

### Standard Gateway Mock

```php
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Data\Disburse\DisburseResponseData;

// Complete example
$mockGateway = Mockery::mock(PaymentGatewayInterface::class);

$mockGateway->shouldReceive('disburse')
    ->once()
    ->andReturn(
        DisburseResponseData::from([
            'transaction_id' => 'DISB-789',
            'uuid' => 'uuid-abc-123',
            'status' => 'completed',
        ])
    );

app()->instance(PaymentGatewayInterface::class, $mockGateway);
```

### Gateway Failure Simulation

```php
// Return false (failure)
$mockGateway->shouldReceive('disburse')
    ->andReturn(false);

// Throw exception
$mockGateway->shouldReceive('disburse')
    ->andThrow(new \RuntimeException('Network error'));
```

### Gateway with Multiple Calls

```php
// Different responses for different calls
$mockGateway->shouldReceive('disburse')
    ->times(3)
    ->andReturn(
        DisburseResponseData::from(['status' => 'success']),
        DisburseResponseData::from(['status' => 'pending']),
        DisburseResponseData::from(['status' => 'failed'])
    );
```

### Verify Gateway Called with Correct Data

```php
$mockGateway->shouldReceive('disburse')
    ->once()
    ->withArgs(function ($cash, $input) {
        // Verify cash entity
        expect($cash)->toBeInstanceOf(\LBHurtado\Cash\Models\Cash::class);
        expect($cash->amount->getAmount()->toFloat())->toBe(500.0);
        
        // Verify input data
        expect($input)->toBeInstanceOf(DisburseInputData::class);
        expect($input->bank)->toBe('GCASH');
        expect($input->via)->toBe('INSTAPAY');
        
        return true;
    })
    ->andReturn(DisburseResponseData::from(['status' => 'success']));
```

---

## Notification Channel Mocking

Mock custom notification channels.

### Mock Custom Channel

```php
use App\Notifications\Channels\EngageSparkChannel;

$mockChannel = Mockery::mock(EngageSparkChannel::class);

$mockChannel->shouldReceive('send')
    ->once()
    ->with(
        Mockery::type('array'),  // Notifiable
        Mockery::type(SendFeedbacksNotification::class)
    );

app()->instance(EngageSparkChannel::class, $mockChannel);
```

### Verify Channel Payload

```php
$mockChannel->shouldReceive('send')
    ->once()
    ->withArgs(function ($notifiable, $notification) {
        expect($notifiable['mobile'])->toBe('+639171234567');
        expect($notification->voucher->code)->toStartWith('TEST');
        return true;
    });
```

---

## Config and Environment Mocking

Override configuration for tests.

### Config Override (Runtime)

```php
// Override config value
config(['payment-gateway.use_omnipay' => true]);

// Multiple configs
config([
    'payment-gateway.use_omnipay' => true,
    'payment-gateway.default' => 'netbank',
]);

// In test
test('uses omnipay gateway', function () {
    config(['payment-gateway.use_omnipay' => true]);
    
    // Test code here
});
```

### Environment Override (Limited)

```php
// Note: env() values are cached at config load time
// This only works if config reads directly, not via env()

putenv('DISBURSE_DISABLE=true');

// Better: Override in phpunit.xml
// <env name="DISBURSE_DISABLE" value="true"/>
```

### Config Override Limitations

**Problem**: If config file uses `env()` directly, runtime changes won't work:

```php
// config/payment-gateway.php
'disable' => env('DISBURSE_DISABLE', false), // ❌ Cached at load time
```

**Solution 1**: Use config value in tests
```php
config(['payment-gateway.disburse_disable' => true]); // ✅ Works
```

**Solution 2**: Set in `phpunit.xml`
```xml
<env name="DISBURSE_DISABLE" value="true"/>
```

**Solution 3**: Document behavior instead of forcing override
```php
// Note: With DISBURSE_DISABLE=true in phpunit.xml,
// disbursement won't run. This test documents expected behavior.
```

---

## Event and Queue Mocking

### Event Faking with Assertions

```php
Event::fake([DisburseInputPrepared::class]);

// Trigger code that dispatches event
$voucher->disburse();

// Assert
Event::assertDispatched(DisburseInputPrepared::class, function ($event) {
    return $event->input->amount === 500.0;
});
```

### Queue Job with Closure

```php
Queue::fake();

// Dispatch job
dispatch(function () use ($voucher) {
    $voucher->sendNotifications();
});

// Assert
Queue::assertPushed(\Illuminate\Queue\CallQueuedClosure::class);
```

### Queue with Specific Connection

```php
Queue::fake();

dispatch(new SendEmailJob($voucher))->onQueue('emails');

Queue::assertPushedOn('emails', SendEmailJob::class);
```

---

## Time and Date Mocking

Use Carbon test helpers to control time.

### Set Fixed Time

```php
use Carbon\Carbon;

// Set test time
Carbon::setTestNow('2024-01-15 12:00:00');

// Now all Carbon::now() calls return this time
expect(Carbon::now()->toDateString())->toBe('2024-01-15');

// Reset to real time
Carbon::setTestNow();
```

### Travel Forward in Time

```php
// Set starting time
Carbon::setTestNow('2024-01-01 00:00:00');

// Do something
$voucher = createVoucher();

// Travel forward 7 days
Carbon::setTestNow(Carbon::now()->addDays(7));

// Test expiration
expect($voucher->expires_at->isPast())->toBeTrue();
```

### Test Example: Voucher Expiration

```php
test('voucher expires after TTL', function () {
    Carbon::setTestNow('2024-01-01 12:00:00');
    
    $voucher = createVoucherWithTTL('P7D'); // 7 days
    
    expect($voucher->expires_at)->toBe(Carbon::parse('2024-01-08 12:00:00'));
    
    // Travel to expiration date
    Carbon::setTestNow('2024-01-08 12:00:01');
    
    expect($voucher->isExpired())->toBeTrue();
    
    Carbon::setTestNow(); // Reset
});
```

---

## Database Mocking

Most tests use real database with `RefreshDatabase`. But sometimes you need more control.

### Transaction Testing

```php
use Illuminate\Support\Facades\DB;

test('rolls back on failure', function () {
    DB::beginTransaction();
    
    try {
        // Code that might fail
        $voucher->redeem();
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
    }
    
    // Verify rollback worked
    expect(Voucher::count())->toBe(0);
});
```

### Database Assertions

```php
// Assert record exists
$this->assertDatabaseHas('vouchers', [
    'code' => 'TEST-1234',
    'redeemed_at' => null,
]);

// Assert record doesn't exist
$this->assertDatabaseMissing('vouchers', [
    'code' => 'INVALID',
]);

// Assert count
$this->assertDatabaseCount('vouchers', 5);
```

### Soft Deletes Testing

```php
$voucher->delete();

// Still in database
$this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);

// But soft deleted
expect($voucher->trashed())->toBeTrue();

// Force delete
$voucher->forceDelete();

$this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
```

---

## Common Pitfalls

### 1. Forgetting to Fake Before Running Code

❌ **Wrong:**
```php
RedeemVoucher::run($contact, $voucher->code);

Http::fake(); // Too late!
```

✅ **Right:**
```php
Http::fake();

RedeemVoucher::run($contact, $voucher->code);
```

### 2. Not Resetting Mocks in `afterEach()`

❌ **Wrong:**
```php
test('first test', function () {
    Http::fake();
    // ...
});

test('second test', function () {
    // Http is still faked from previous test!
});
```

✅ **Right:**
```php
beforeEach(function () {
    Http::fake();
    Notification::fake();
});

afterEach(function () {
    Mockery::close(); // Clean up Mockery mocks
});
```

### 3. Expecting Mock to Be Called When Feature Is Disabled

❌ **Wrong:**
```php
// phpunit.xml has DISBURSE_DISABLE=true

$mockGateway->shouldReceive('disburse')
    ->once(); // ❌ Will never be called!

app()->instance(PaymentGatewayInterface::class, $mockGateway);
```

✅ **Right:**
```php
// Don't expect call if feature is disabled
$mockGateway = Mockery::mock(PaymentGatewayInterface::class);
app()->instance(PaymentGatewayInterface::class, $mockGateway);

// Document that it won't be called
// Note: With DISBURSE_DISABLE=true, gateway won't be invoked
```

### 4. Mocking Facade Instead of Using fake()

❌ **Wrong (Complex):**
```php
$mock = Mockery::mock('alias:' . Http::class);
$mock->shouldReceive('post')->andReturn(...);
```

✅ **Right (Simple):**
```php
Http::fake([
    'api.example.com/*' => Http::response(['success' => true], 200),
]);
```

### 5. Not Understanding Mock vs Spy

**Mock** - Stubbed methods (return fake data):
```php
$mock = Mockery::mock(MyService::class);
$mock->shouldReceive('process')->andReturn('fake result');
```

**Spy** - Real methods, verify calls after:
```php
$spy = Mockery::spy(MyService::class);
// ... code runs with real methods ...
$spy->shouldHaveReceived('process')->once();
```

### 6. Overly Strict Mock Expectations

❌ **Too Strict:**
```php
$mock->shouldReceive('disburse')
    ->once()
    ->with(500.0, '09171234567', 'GCASH', 'INSTAPAY') // Exact match
    ->andReturn(true);
```

✅ **More Flexible:**
```php
$mock->shouldReceive('disburse')
    ->once()
    ->with(
        Mockery::type('float'),   // Any float
        Mockery::any(),           // Any string
        Mockery::any(),           // Any bank
        Mockery::in(['INSTAPAY', 'PESONET']) // Either rail
    )
    ->andReturn(true);
```

### 7. Asserting on Fakes Without Triggering Code

❌ **Wrong:**
```php
Http::fake();

// Never actually make HTTP call
// ...

Http::assertSent(...); // Will fail - nothing was sent
```

✅ **Right:**
```php
Http::fake();

// Trigger code that makes HTTP call
RedeemVoucher::run($contact, $voucher->code);

// Now assert
Http::assertSent(...);
```

---

## Real-World Examples from This Project

### Example 1: Complete E2E Test

```php
test('complete voucher lifecycle', function () {
    // 1. Fake external services
    Notification::fake();
    Http::fake(['webhook.site/*' => Http::response(['ok' => true], 200)]);
    
    // 2. Mock payment gateway
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mockGateway->shouldReceive('disburse')
        ->andReturn(DisburseResponseData::from(['status' => 'success']));
    app()->instance(PaymentGatewayInterface::class, $mockGateway);
    
    // 3. Setup test data
    $user = User::factory()->create();
    $user->deposit(100000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1);
    $voucher = $vouchers->first();
    
    // 4. Execute
    $contact = Contact::factory()->create();
    RedeemVoucher::run($contact, $voucher->code);
    
    // 5. Assert
    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect($voucher->cash)->not->toBeNull();
});
```

### Example 2: Multi-Voucher Concurrency

```php
test('concurrent redemption race conditions', function () {
    $user = User::factory()->create();
    $user->deposit(100000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1);
    $voucher = $vouchers->first();
    
    $contact1 = Contact::factory()->create();
    $contact2 = Contact::factory()->create();
    
    // First succeeds
    $first = RedeemVoucher::run($contact1, $voucher->code);
    expect($first)->toBeTrue();
    
    // Second fails (already redeemed)
    $second = RedeemVoucher::run($contact2, $voucher->code);
    expect($second)->toBeFalse();
    
    // Only first contact is redeemer
    $voucher->refresh();
    expect($voucher->redeemers)->toHaveCount(1);
    expect($voucher->redeemers->first()->redeemer_id)->toBe($contact1->id);
});
```

### Example 3: Webhook Timeout Handling

```php
test('webhook timeout does not block redemption', function () {
    // Simulate slow/timeout webhook
    Http::fake([
        'webhook.site/*' => function () {
            sleep(1);
            return Http::response(['error' => 'timeout'], 408);
        },
    ]);
    
    $user = User::factory()->create();
    $user->deposit(100000);
    
    $vouchers = VoucherTestHelper::createVouchersWithInstructions($user, 1, '', [
        'feedback' => ['webhook' => 'https://webhook.site/timeout-test'],
    ]);
    
    // Redemption should still succeed
    $contact = Contact::factory()->create();
    $redeemed = RedeemVoucher::run($contact, $vouchers->first()->code);
    
    expect($redeemed)->toBeTrue();
});
```

---

## Quick Reference Cheat Sheet

```php
// Facades
Http::fake()
Notification::fake()
Mail::fake()
Queue::fake()
Event::fake()
Storage::fake('disk')

// Service Container
$mock = Mockery::mock(Interface::class);
$mock->shouldReceive('method')->andReturn('value');
app()->instance(Interface::class, $mock);

// Spy
$spy = Mockery::spy(Class::class);
$spy->shouldHaveReceived('method')->once();

// Config
config(['key' => 'value'])

// Time
Carbon::setTestNow('2024-01-01 12:00:00')

// Assertions
Http::assertSent(function ($request) { ... })
Notification::assertSentTo($user, NotificationClass::class)
Event::assertDispatched(EventClass::class)
$this->assertDatabaseHas('table', ['key' => 'value'])
```

---

## Resources

- [Laravel Testing Docs](https://laravel.com/docs/testing)
- [Mockery Documentation](http://docs.mockery.io/)
- [Pest PHP Expectations](https://pestphp.com/docs/expectations)
- [Carbon Testing Helpers](https://carbon.nesbot.com/docs/#api-testing)

---

**Remember**: The goal of mocking is to **isolate the code under test**. Mock external dependencies (APIs, notifications) but use real implementations for your own code whenever possible.
