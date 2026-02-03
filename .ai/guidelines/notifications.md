# Notification System - AI Development Guidelines

This document provides guidelines for AI agents (Claude Code, GitHub Copilot, etc.) when working with the notification system.

## Core Principles

1. **All notifications extend BaseNotification** - Never create standalone Notification classes
2. **Use config-driven channels** - Never hardcode channel lists in notification classes
3. **Localize all user-facing text** - Never hardcode messages in notification code
4. **Implement NotificationInterface** - Always implement the 3 required methods
5. **Test with real delivery** - Always verify actual email/SMS delivery

## File Structure

```
app/
├── Contracts/
│   └── NotificationInterface.php          # Contract for all notifications
├── Notifications/
│   ├── BaseNotification.php               # Abstract base class
│   ├── BalanceNotification.php            # SMS balance inquiries
│   ├── DisbursementFailedNotification.php # Critical failure alerts
│   ├── HelpNotification.php               # SMS help messages
│   ├── LowBalanceAlert.php                # Gateway balance warnings
│   ├── PaymentConfirmationNotification.php # Payment confirmations
│   ├── SendFeedbacksNotification.php      # Voucher redemption (complex)
│   ├── TestSimpleNotification.php         # Testing
│   └── VouchersGeneratedSummary.php       # Generation confirmations
config/
└── notifications.php                       # Centralized configuration
lang/en/
└── notifications.php                       # All templates
tests/Feature/Notifications/
├── BaseNotificationTest.php                # Base class tests (26 tests)
├── SendFeedbacksNotificationTest.php       # Complex notification tests
└── Week3IntegrationTest.php                # Integration tests
```

## Creating a New Notification

### Step 1: Create Notification Class

```php
<?php

namespace App\Notifications;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;
use LBHurtado\EngageSpark\EngageSparkMessage;

/**
 * My Notification
 * 
 * Brief description of what triggers this notification.
 * 
 * Migration to BaseNotification:
 * - Extends BaseNotification for standardized behavior
 * - Uses config/notifications.php for channel configuration
 * - Uses lang/en/notifications.php for localization templates
 * - Implements NotificationInterface
 */
class MyNotification extends BaseNotification
{
    public function __construct(
        protected MyModel $model
    ) {}

    /**
     * Get the notification type identifier.
     */
    public function getNotificationType(): string
    {
        return 'my_notification';
    }

    /**
     * Get the notification data payload.
     */
    public function getNotificationData(): array
    {
        return [
            'field1' => $this->model->field1,
            'field2' => $this->model->field2,
            'formatted_field1' => $this->formatMoney($this->model->field1),
        ];
    }

    /**
     * Get audit metadata for this notification.
     */
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'model_id' => $this->model->id,
            'custom_audit_field' => $this->model->some_field,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $context = $this->buildTemplateContext($notifiable);
        $context['field1'] = $this->model->field1;
        $context['field2'] = $this->model->field2;
        
        $subject = $this->getLocalizedTemplate('notifications.my_notification.email.subject', $context);
        $greeting = $this->getLocalizedTemplate('notifications.my_notification.email.greeting', $context);
        $body = $this->getLocalizedTemplate('notifications.my_notification.email.body', $context);
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($body);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        $context = $this->buildTemplateContext($notifiable);
        $context['field1'] = $this->model->field1;
        
        $message = $this->getLocalizedTemplate('notifications.my_notification.sms', $context);
        
        return (new EngageSparkMessage())->content($message);
    }
}
```

### Step 2: Add Configuration

Edit `config/notifications.php`:

```php
'channels' => [
    // ... existing channels
    'my_notification' => explode(',', env('MY_NOTIFICATION_CHANNELS', 'mail,engage_spark')),
],

'queue' => [
    'queues' => [
        'normal' => [
            // ... existing notifications
            'my_notification',
        ],
    ],
],
```

### Step 3: Add Localization

Edit `lang/en/notifications.php`:

```php
'my_notification' => [
    'email' => [
        'subject' => 'Subject with {{ field1 }}',
        'greeting' => 'Hello {{ name }},',
        'body' => 'Your {{ field1 }} is {{ field2 }}.',
    ],
    'sms' => 'Brief SMS: {{ field1 }} - {{ field2 }}',
],
```

### Step 4: Write Tests

```php
<?php

use App\Notifications\MyNotification;
use App\Models\User;

test('my notification follows BaseNotification structure', function () {
    $model = MyModel::factory()->create();
    $notification = new MyNotification($model);
    
    expect($notification)->toBeInstanceOf(App\Notifications\BaseNotification::class);
    expect($notification)->toBeInstanceOf(App\Contracts\NotificationInterface::class);
    expect($notification->getNotificationType())->toBe('my_notification');
    
    $user = User::factory()->create();
    $data = $notification->toArray($user);
    
    expect($data)->toHaveKeys(['type', 'timestamp', 'data', 'audit']);
    expect($data['type'])->toBe('my_notification');
    expect($data['data'])->toHaveKey('field1');
});

test('my notification sends to correct channels', function () {
    Notification::fake();
    
    $user = User::factory()->create();
    $model = MyModel::factory()->create();
    
    $user->notify(new MyNotification($model));
    
    Notification::assertSentTo($user, MyNotification::class);
});
```

## Common Patterns

### Email with Attachments

See `SendFeedbacksNotification` for complete example:

```php
public function toMail(object $notifiable): MailMessage
{
    $mail = (new MailMessage)
        ->subject($subject)
        ->line($body);
    
    // Attach base64 image
    if ($imageData && str_starts_with($imageData, 'data:image/')) {
        [, $encodedImage] = explode(',', $imageData, 2);
        preg_match('/^data:image\/(\w+);base64/', $imageData, $matches);
        $extension = $matches[1] ?? 'png';
        
        $mail->attachData(
            base64_decode($encodedImage),
            "filename.{$extension}",
            ['mime' => "image/{$extension}"]
        );
    }
    
    return $mail;
}
```

### Conditional Templates

```php
public function toEngageSpark(object $notifiable): EngageSparkMessage
{
    $context = $this->buildTemplateContext($notifiable);
    
    // Choose template based on data
    $templateKey = $context['has_address']
        ? 'notifications.my_notification.sms.with_address'
        : 'notifications.my_notification.sms.without_address';
    
    $message = $this->getLocalizedTemplate($templateKey, $context);
    
    return (new EngageSparkMessage())->content($message);
}
```

### Custom Channel Logic

Only override `via()` if you need special logic beyond config:

```php
public function via(object $notifiable): array
{
    // For special notifiable types
    if ($notifiable instanceof SpecialType) {
        return ['custom_channel'];
    }
    
    // Otherwise use BaseNotification's config-driven approach
    return parent::via($notifiable);
}
```

## Testing Patterns

### Unit Tests (Structure)

```php
test('notification implements interface correctly', function () {
    $notification = new MyNotification($model);
    
    expect($notification)->toBeInstanceOf(BaseNotification::class);
    expect($notification->getNotificationType())->toBeString();
    expect($notification->getNotificationData())->toBeArray();
    expect($notification->getAuditMetadata())->toBeArray();
});
```

### Integration Tests (Channels)

```php
test('notification uses correct channels', function () {
    $notification = new MyNotification($model);
    $user = User::factory()->create();
    
    $channels = $notification->via($user);
    
    expect($channels)->toContain('database');
    expect($channels)->toContain('mail');
});
```

### Manual Testing (Real Delivery)

```bash
# Create test command
php artisan make:command TestMyNotificationCommand

# Implement command
php artisan test:my-notification --email=user@example.com --mobile=09171234567

# Run and verify delivery
php artisan queue:work --once
```

## Common Mistakes to Avoid

### ❌ DON'T: Hardcode channels

```php
public function via(object $notifiable): array
{
    return ['mail', 'engage_spark']; // BAD
}
```

### ✅ DO: Use config-driven channels

```php
// Rely on BaseNotification's via() method (no override needed)
// OR if you need custom logic:
public function via(object $notifiable): array
{
    $channels = parent::via($notifiable); // Get from config
    // Add custom logic if needed
    return $channels;
}
```

### ❌ DON'T: Hardcode messages

```php
public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Voucher Redeemed') // BAD
        ->line('Your voucher was redeemed.'); // BAD
}
```

### ✅ DO: Use localization

```php
public function toMail(object $notifiable): MailMessage
{
    $context = $this->buildTemplateContext($notifiable);
    $subject = $this->getLocalizedTemplate('notifications.my_notification.email.subject', $context);
    $body = $this->getLocalizedTemplate('notifications.my_notification.email.body', $context);
    
    return (new MailMessage)
        ->subject($subject)
        ->line($body);
}
```

### ❌ DON'T: Custom toArray() structure

```php
public function toArray(object $notifiable): array
{
    return [
        'field1' => $this->model->field1, // BAD - not standardized
        'field2' => $this->model->field2,
    ];
}
```

### ✅ DO: Delete toArray() and use BaseNotification's

```php
// NO toArray() method - BaseNotification uses getNotificationData()
```

### ❌ DON'T: Forget queue configuration

```php
class MyNotification extends Notification // BAD - not queued
{
    // ...
}
```

### ✅ DO: Extend BaseNotification (automatically queued)

```php
class MyNotification extends BaseNotification // GOOD - queued automatically
{
    // Queue priority determined by config/notifications.php
}
```

## Debugging Checklist

When notification isn't working:

1. **Check queue worker is running**
   ```bash
   php artisan queue:work --queue=high,normal,low
   ```

2. **Verify configuration**
   ```php
   config('notifications.channels.my_notification')
   ```

3. **Check notification data structure**
   ```php
   $data = $notification->toArray($user);
   dd($data); // Should have type, timestamp, data, audit
   ```

4. **Verify template variables**
   ```php
   $context = $this->buildTemplateContext($notifiable);
   dd($context); // Should contain all {{ variables }}
   ```

5. **Test channel method directly**
   ```php
   $mail = $notification->toMail($user);
   dd($mail); // Inspect MailMessage object
   ```

6. **Check database logging**
   ```php
   $user->notifications()->latest()->first();
   ```

## Queue Priority Guide

Choose the appropriate queue priority:

**High** - Critical alerts requiring immediate action:
- System failures (disbursement failures, gateway errors)
- Security alerts
- Balance threshold breaches

**Normal** - User-facing notifications:
- Transaction confirmations
- Redemption notifications
- Payment confirmations

**Low** - Informational messages:
- Balance inquiries
- Help messages
- Generation confirmations
- Non-urgent updates

## Template Variable Conventions

Always use these variable naming patterns:

- `{{ code }}` - Voucher/reference code
- `{{ amount }}` - Raw numeric amount
- `{{ formatted_amount }}` - Currency-formatted amount (₱100.00)
- `{{ mobile }}` - Phone number
- `{{ email }}` - Email address
- `{{ formatted_address }}` - Location address
- `{{ <field>_at }}` - Timestamps (created_at, redeemed_at, etc.)
- `{{ owner_<field> }}` - Owner-related fields
- `{{ redeemer_<field> }}` - Redeemer-related fields

## Documentation Requirements

When creating a new notification, update these files:

1. **docs/guides/features/NOTIFICATION_SYSTEM.md** - Add to notification types table
2. **docs/api/NOTIFICATION_DISPATCH_REFERENCE.md** - Document dispatch triggers
3. **lang/en/notifications.php** - Add templates and document available variables
4. **config/notifications.php** - Add channel and queue configuration
5. **README.md** (if user-facing) - Document any new commands

## See Also

- [Notification System Guide](../../docs/guides/features/NOTIFICATION_SYSTEM.md)
- [Notification Dispatch Reference](../../docs/api/NOTIFICATION_DISPATCH_REFERENCE.md)
- [Notification Templates](../../docs/guides/features/NOTIFICATION_TEMPLATES.md)
- [BaseNotification Source](../../app/Notifications/BaseNotification.php)
- [NotificationInterface Source](../../app/Contracts/NotificationInterface.php)
