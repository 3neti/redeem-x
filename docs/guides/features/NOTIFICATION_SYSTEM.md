# Notification System

This document describes the standardized notification system architecture implemented across all notifications in the application.

## Overview

The notification system provides a centralized, consistent approach to sending notifications via multiple channels (email, SMS, database, webhook). All notifications extend `BaseNotification` and implement `NotificationInterface` for standardized behavior.

## Architecture

### Core Components

**BaseNotification** (`app/Notifications/BaseNotification.php`)
- Abstract base class that all notifications extend
- Implements `NotificationInterface` and `ShouldQueue`
- Provides standardized methods for channel resolution, queue management, database logging, and localization
- Handles automatic database logging for User models
- Manages queue priorities (high/normal/low) based on notification type

**NotificationInterface** (`app/Contracts/NotificationInterface.php`)
- Contract defining required methods for all notifications:
  - `getNotificationType(): string` - Returns notification type identifier
  - `getNotificationData(): array` - Returns notification-specific data payload
  - `getAuditMetadata(): array` - Returns audit metadata for logging

**Configuration** (`config/notifications.php`)
- Centralized channel configuration per notification type
- Queue priority settings (high/normal/low)
- Database logging rules
- Environment variable overrides

**Localization** (`lang/en/notifications.php`)
- All notification templates stored in translation files
- Supports `{{ variable }}` syntax for dynamic values
- Processed by `TemplateProcessor` service

### Notification Types

All 7 notifications follow the BaseNotification pattern:

| Notification | Type | Channels | Queue | Description |
|-------------|------|----------|-------|-------------|
| `BalanceNotification` | `balance` | SMS | Low | User/system balance inquiries |
| `HelpNotification` | `help` | SMS | Low | SMS command help messages |
| `VouchersGeneratedSummary` | `vouchers_generated` | SMS | Low | Voucher generation confirmation |
| `DisbursementFailedNotification` | `disbursement_failed` | Email | High | Critical disbursement failure alerts |
| `LowBalanceAlert` | `low_balance_alert` | Email | High | Gateway balance warnings |
| `PaymentConfirmationNotification` | `payment_confirmation` | SMS | Normal | Settlement payment confirmations |
| `SendFeedbacksNotification` | `voucher_redeemed` | Email, SMS | Normal | Voucher redemption notifications |

## Usage

### Sending Notifications

**To User Models** (automatically logs to database):
```php
use App\Models\User;
use App\Notifications\BalanceNotification;

$user = User::find(1);
$user->notify(new BalanceNotification('user', $balance));
```

**To Anonymous Recipients** (email/SMS only):
```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendFeedbacksNotification;

Notification::route('mail', 'user@example.com')
    ->route('engage_spark', '09171234567')
    ->notify(new SendFeedbacksNotification($voucherCode));
```

**Via Queue** (automatic based on notification type):
```php
// All notifications are automatically queued
// Queue priority determined by config/notifications.php
$user->notify(new DisbursementFailedNotification($voucher, $error, $exceptionType));
// ↑ Automatically sent to 'high' queue
```

### Creating New Notifications

1. **Extend BaseNotification**:
```php
use App\Notifications\BaseNotification;

class MyNotification extends BaseNotification
{
    public function __construct(
        protected MyModel $model
    ) {}
    
    public function getNotificationType(): string
    {
        return 'my_notification';
    }
    
    public function getNotificationData(): array
    {
        return [
            'field1' => $this->model->field1,
            'field2' => $this->model->field2,
        ];
    }
    
    public function getAuditMetadata(): array
    {
        return array_merge(parent::getAuditMetadata(), [
            'model_id' => $this->model->id,
        ]);
    }
}
```

2. **Add channel configuration** (`config/notifications.php`):
```php
'channels' => [
    'my_notification' => explode(',', env('MY_NOTIFICATION_CHANNELS', 'mail,engage_spark')),
],

'queue' => [
    'queues' => [
        'normal' => [
            'my_notification',
        ],
    ],
],
```

3. **Add localization templates** (`lang/en/notifications.php`):
```php
'my_notification' => [
    'email' => [
        'subject' => 'Subject with {{ variable }}',
        'greeting' => 'Hello {{ name }}',
        'body' => 'Message body with {{ data }}',
    ],
    'sms' => 'SMS message with {{ variable }}',
],
```

4. **Implement channel methods** (email/SMS):
```php
public function toMail(object $notifiable): MailMessage
{
    $context = $this->buildTemplateContext($notifiable);
    $context['custom_field'] = $this->model->custom_field;
    
    $subject = $this->getLocalizedTemplate('notifications.my_notification.email.subject', $context);
    $greeting = $this->getLocalizedTemplate('notifications.my_notification.email.greeting', $context);
    
    return (new MailMessage)
        ->subject($subject)
        ->greeting($greeting)
        ->line($this->getLocalizedTemplate('notifications.my_notification.email.body', $context));
}

public function toEngageSpark(object $notifiable): EngageSparkMessage
{
    $context = $this->buildTemplateContext($notifiable);
    $context['custom_field'] = $this->model->custom_field;
    
    $message = $this->getLocalizedTemplate('notifications.my_notification.sms', $context);
    
    return (new EngageSparkMessage())->content($message);
}
```

## Database Logging

### Automatic Logging

Database logging is automatic for User models. The notification data is stored with this structure:

```json
{
  "type": "notification_type",
  "timestamp": "2026-02-03T11:43:24.295329Z",
  "data": {
    "field1": "value1",
    "field2": "value2"
  },
  "audit": {
    "sent_via": "engage_spark",
    "queued": true,
    "queue": "normal",
    "notification_specific_fields": "..."
  }
}
```

### Querying Notifications

**User notifications**:
```php
// All notifications
$notifications = $user->notifications;

// Unread only
$unread = $user->unreadNotifications;

// By type
$balanceNotifications = $user->notifications()
    ->where('type', 'App\Notifications\BalanceNotification')
    ->get();

// Mark as read
$user->unreadNotifications->markAsRead();
```

**Database indexes** (optimized for common queries):
- `(notifiable_type, notifiable_id, type)` - User notifications by type
- `(type, created_at)` - Notification reporting by type and date
- `(read_at)` - Unread notification counts

## Queue Configuration

### Queue Priorities

Notifications are distributed across 3 queue priorities:

**High Priority** (critical alerts):
- `disbursement_failed` - Failed disbursements requiring immediate action
- `low_balance_alert` - Gateway balance warnings

**Normal Priority** (user-facing):
- `voucher_redeemed` - Redemption confirmations
- `payment_confirmation` - Payment confirmations

**Low Priority** (informational):
- `vouchers_generated` - Generation confirmations
- `balance` - Balance inquiries
- `help` - Help messages

### Running Queue Workers

**Development**:
```bash
php artisan queue:work --queue=high,normal,low
```

**Production** (via Supervisor):
```ini
[program:queue-worker]
command=php /path/to/artisan queue:work --queue=high,normal,low --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
```

## Localization

### Template Syntax

Use `{{ variable }}` syntax (NOT `:variable`):

```php
// ✅ Correct
'subject' => 'Voucher {{ code }} redeemed for {{ formatted_amount }}'

// ❌ Wrong
'subject' => 'Voucher :code redeemed for :formatted_amount'
```

### Available Variables

See `lang/en/notifications.php` for complete list. Common variables:

- `{{ code }}` - Voucher code
- `{{ formatted_amount }}` - Formatted currency amount
- `{{ mobile }}` - Contact mobile number
- `{{ formatted_address }}` - Location address
- `{{ owner_email }}` - Voucher owner email
- Custom fields from notification data

### Processing Templates

Templates are processed by `TemplateProcessor::process($template, $context)`:

```php
$context = [
    'code' => 'TEST-123',
    'formatted_amount' => '₱100.00',
];

$template = 'Voucher {{ code }} worth {{ formatted_amount }}';
$result = TemplateProcessor::process($template, $context);
// Result: "Voucher TEST-123 worth ₱100.00"
```

## Testing

### Unit Tests

Test notification structure and behavior:

```php
use App\Notifications\MyNotification;

test('notification follows BaseNotification structure', function () {
    $notification = new MyNotification($model);
    
    expect($notification)->toBeInstanceOf(BaseNotification::class);
    expect($notification->getNotificationType())->toBe('my_notification');
    
    $data = $notification->toArray($user);
    expect($data)->toHaveKeys(['type', 'timestamp', 'data', 'audit']);
    expect($data['type'])->toBe('my_notification');
});
```

### Integration Tests

Test end-to-end delivery:

```php
use Illuminate\Support\Facades\Notification;

test('notification sends via configured channels', function () {
    Notification::fake();
    
    $user->notify(new MyNotification($model));
    
    Notification::assertSentTo($user, MyNotification::class);
});
```

### Manual Testing

Test actual delivery to real recipients:

```bash
# Test email notification
php artisan tinker
>>> $user = User::find(1);
>>> $user->notify(new MyNotification($model));

# Check queue jobs
php artisan queue:work --once
```

## Migration Guide

### From Custom Notification to BaseNotification

1. **Change extends**:
```php
// Before
class MyNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // ...
}

// After
class MyNotification extends BaseNotification
{
    // Queueable and ShouldQueue now in BaseNotification
}
```

2. **Add interface methods**:
```php
public function getNotificationType(): string
{
    return 'my_notification';
}

public function getNotificationData(): array
{
    return [
        'field1' => $this->model->field1,
        'field2' => $this->model->field2,
    ];
}

public function getAuditMetadata(): array
{
    return array_merge(parent::getAuditMetadata(), [
        'model_id' => $this->model->id,
    ]);
}
```

3. **Remove custom toArray()** (uses BaseNotification's):
```php
// Before
public function toArray(object $notifiable): array
{
    return [
        'field1' => $this->model->field1,
        'field2' => $this->model->field2,
    ];
}

// After - DELETE toArray() method
// BaseNotification calls getNotificationData() automatically
```

4. **Update via() if needed**:
```php
// Before (custom logic)
public function via(object $notifiable): array
{
    return ['mail', 'engage_spark'];
}

// After - DELETE via() method to use BaseNotification's config-driven approach
// OR override only if you need special logic:
public function via(object $notifiable): array
{
    $channels = parent::via($notifiable); // Get from config
    // Add custom logic if needed
    return $channels;
}
```

5. **Add to config**:
```php
// config/notifications.php
'channels' => [
    'my_notification' => explode(',', env('MY_NOTIFICATION_CHANNELS', 'mail')),
],
```

## Best Practices

1. **Use localization for all user-facing text** - Never hardcode messages in notification classes
2. **Keep notification data focused** - Only include data needed for the notification
3. **Use audit metadata for debugging** - Add context helpful for troubleshooting
4. **Test with real delivery** - Always verify actual email/SMS delivery before deploying
5. **Monitor queue health** - Track failed jobs and queue depth
6. **Use appropriate queue priority** - Don't overuse high priority queue
7. **Handle failures gracefully** - Implement retry logic for transient failures
8. **Document custom behavior** - Comment any overrides to BaseNotification methods

## Troubleshooting

### Notifications Not Sending

**Check queue worker**:
```bash
# Ensure queue worker is running
php artisan queue:work --queue=high,normal,low

# Check failed jobs
php artisan queue:failed
```

**Check configuration**:
```bash
# Verify channels configured
php artisan tinker
>>> config('notifications.channels.my_notification')

# Test notification manually
>>> $user->notify(new MyNotification($model))
```

### Wrong Channels

**Check config override**:
```bash
# .env
MY_NOTIFICATION_CHANNELS=mail,engage_spark
```

**Verify via() method**:
```php
$notification = new MyNotification($model);
$channels = $notification->via($user);
dd($channels); // Should match config
```

### Database Not Logging

**Check notifiable type**:
- Only User models get automatic database logging
- AnonymousNotifiable excluded by default
- Configure in `config/notifications.php` → `database_logging`

### Template Variables Not Replacing

**Check syntax**:
```php
// ✅ Correct
'{{ variable }}'

// ❌ Wrong
':variable'
'{ variable }'
'{{variable}}' // Missing spaces
```

**Verify context includes variable**:
```php
$context = $this->buildTemplateContext($notifiable);
dd($context); // Should contain all variables used in template
```

## See Also

- [Notification Templates](NOTIFICATION_TEMPLATES.md) - Template syntax and available variables
- [Notification Dispatch Reference](../../api/NOTIFICATION_DISPATCH_REFERENCE.md) - Command-to-notification mapping
- [AI Guidelines](../../../.ai/guidelines/notifications.md) - Development guidelines for AI agents
