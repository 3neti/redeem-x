# Notification Dispatch Reference

This document maps commands, events, and actions to the notifications they dispatch.

## Overview

Notifications are dispatched from various parts of the application in response to user actions, system events, and background jobs. This reference helps developers understand when and where notifications are sent.

## Command-to-Notification Mapping

### Artisan Commands

| Command | Notification | Recipient | Trigger |
|---------|-------------|-----------|---------|
| `test:notification` | `TestSimpleNotification` | User (config) | Manual testing |
| `test:balance-notification` | `BalanceNotification` | User (config) | Manual testing |
| `test:help-notification` | `HelpNotification` | User (config) | Manual testing |
| `test:disbursement-failure` | `DisbursementFailedNotification` | Admin users | Manual testing |
| `sms:router` | `BalanceNotification` | SMS sender | BALANCE command |
| `sms:router` | `HelpNotification` | SMS sender | HELP command |
| N/A | `VouchersGeneratedSummary` | Voucher owner | After voucher generation |
| N/A | `PaymentConfirmationNotification` | Payer | After settlement payment |
| N/A | `LowBalanceAlert` | Admin users | Balance threshold breach |
| N/A | `SendFeedbacksNotification` | Voucher owner | After voucher redemption |

### SMS Commands

| SMS Text | Handler | Notification | Recipient | Response |
|----------|---------|-------------|-----------|----------|
| `BALANCE` | `SmsBalanceCommand` | `BalanceNotification` | Sender | User wallet balance |
| `BALANCE SYSTEM` | `SmsBalanceCommand` | `BalanceNotification` | Sender | System-wide balances |
| `HELP` | `SmsHelpCommand` | `HelpNotification` | Sender | General help message |
| `HELP <command>` | `SmsHelpCommand` | `HelpNotification` | Sender | Command-specific help |
| `REDEEM <code>` | `SmsRedeemCommand` | `SendFeedbacksNotification` | Voucher owner | Redemption confirmation |
| `GENERATE <amount>` | `SmsGenerateCommand` | `VouchersGeneratedSummary` | Sender | Voucher code(s) |

## Action-to-Notification Mapping

### Voucher Actions

**GenerateVouchers** (`LBHurtado\Voucher\Actions\GenerateVouchers`)
- **Notification**: `VouchersGeneratedSummary`
- **Recipient**: User who generated vouchers (via `feedback.mobile`)
- **Trigger**: After successful voucher generation
- **Channel**: SMS
- **Data**: Voucher codes, amount, count, share links

**RedeemVoucher** (`LBHurtado\Voucher\Actions\RedeemVoucher`)
- **Notification**: `SendFeedbacksNotification`
- **Recipient**: Voucher owner (via `feedback.email`, `feedback.mobile`, `feedback.webhook`)
- **Trigger**: After successful voucher redemption
- **Channels**: Email, SMS, (Webhook - disabled)
- **Data**: Voucher code, redeemer mobile, amount, location, attachments (signature, selfie, location map)

### Payment Actions

**NetBankWebhookController** (`app/Http/Controllers/NetBankWebhookController`)
- **Notification**: `PaymentConfirmationNotification`
- **Recipient**: User who initiated payment
- **Trigger**: After successful settlement payment webhook
- **Channel**: SMS
- **Data**: Payment amount, voucher code, confirmation URL

### Disbursement Actions

**DisburseCash** (Pipeline stage in voucher redemption)
- **Notification**: `DisbursementFailedNotification`
- **Recipient**: Admin users (via `DISBURSEMENT_ALERT_EMAILS`)
- **Trigger**: When disbursement to redeemer fails
- **Channel**: Email
- **Data**: Voucher code, redeemer mobile, amount, error message, exception type

### Balance Monitoring

**BalanceService::checkBalances** (`app/Services/BalanceService`)
- **Notification**: `LowBalanceAlert`
- **Recipient**: Admin users (configured recipients)
- **Trigger**: When account balance falls below threshold
- **Channel**: Email
- **Data**: Account number, gateway, current balance, available balance, threshold

## Event-to-Notification Mapping

### Voucher Events

| Event | Notification | Recipient | Notes |
|-------|-------------|-----------|-------|
| `VoucherGenerated` | `VouchersGeneratedSummary` | Owner | Sent after all vouchers in batch generated |
| `VoucherRedeemed` | `SendFeedbacksNotification` | Owner | Includes location, signature, selfie if captured |

### Payment Events

| Event | Notification | Recipient | Notes |
|-------|-------------|-----------|-------|
| `PaymentReceived` | `PaymentConfirmationNotification` | Payer | Via NetBank webhook |

### System Events

| Event | Notification | Recipient | Notes |
|-------|-------------|-----------|-------|
| `DisbursementFailed` | `DisbursementFailedNotification` | Admins | Critical alert - high priority queue |
| `BalanceThresholdBreached` | `LowBalanceAlert` | Admins | Critical alert - high priority queue |

## Job-to-Notification Mapping

### Background Jobs

| Job | Notification | Recipient | Queue | Notes |
|-----|-------------|-----------|-------|-------|
| `SendVoucherGeneratedNotification` | `VouchersGeneratedSummary` | Owner | Low | Dispatched after voucher generation |
| `SendPaymentConfirmationSms` | `PaymentConfirmationNotification` | Payer | Normal | Dispatched after payment webhook |
| `ProcessRedemption` | `SendFeedbacksNotification` | Owner | Normal | Part of redemption pipeline |

## Notification Flow Diagrams

### Voucher Generation Flow

```
User → GenerateVouchers Action
  ↓
Vouchers Created
  ↓
VoucherGenerated Event Dispatched
  ↓
SendVoucherGeneratedNotification Job (Queue: low)
  ↓
VouchersGeneratedSummary Notification
  ↓
SMS to feedback.mobile
```

### Voucher Redemption Flow

```
User → RedeemVoucher Action
  ↓
Voucher Redeemed
  ↓
Redemption Pipeline:
  - Validate Inputs
  - Process Payment
  - DisburseCash (if enabled)
    ↓ (on failure)
    DisbursementFailedNotification → Admins (Email, High Priority)
  ↓
SendFeedbacksNotification → Owner
  ↓
Email (with attachments) + SMS
```

### Settlement Payment Flow

```
User → Initiates Settlement Payment (NetBank)
  ↓
Payment Completed
  ↓
NetBank Webhook → NetBankWebhookController
  ↓
Webhook Validated & Classified
  ↓
SendPaymentConfirmationSms Job (Queue: normal)
  ↓
PaymentConfirmationNotification
  ↓
SMS to payer mobile
```

### Balance Alert Flow

```
Cron/Schedule → BalanceService::checkBalances
  ↓
Query Gateway Balance
  ↓
Balance < Threshold?
  ↓ Yes
LowBalanceAlert Notification
  ↓
Email to Admins (High Priority)
```

## Recipient Configuration

### User Notifications
Sent to authenticated users via their account:
- `BalanceNotification` (user mode)
- `VouchersGeneratedSummary`
- All notifications support `User->notify()`

### Anonymous Notifications
Sent to dynamic recipients (email/mobile/webhook):
- `SendFeedbacksNotification` (via voucher feedback settings)
- `PaymentConfirmationNotification` (via payment request)
- All notifications support `Notification::route()`

### Admin Notifications
Sent to configured admin emails:
- `DisbursementFailedNotification` (via `DISBURSEMENT_ALERT_EMAILS`)
- `LowBalanceAlert` (via system configuration)

## Environment Configuration

### Notification Channels

Override default channels via environment variables:

```bash
# Balance notification channels (default: engage_spark)
BALANCE_NOTIFICATION_CHANNELS=engage_spark,mail

# Disbursement failure channels (default: mail)
DISBURSEMENT_FAILED_CHANNELS=mail

# Help notification channels (default: engage_spark)
HELP_NOTIFICATION_CHANNELS=engage_spark

# Low balance alert channels (default: mail)
LOW_BALANCE_ALERT_CHANNELS=mail,engage_spark

# Payment confirmation channels (default: engage_spark)
PAYMENT_CONFIRMATION_CHANNELS=engage_spark

# Voucher redeemed channels (default: mail,engage_spark)
VOUCHER_REDEEMED_CHANNELS=mail,engage_spark

# Vouchers generated channels (default: engage_spark)
VOUCHERS_GENERATED_CHANNELS=engage_spark,mail

# Test notification channels (default: engage_spark,mail,database)
TEST_NOTIFICATION_CHANNELS=engage_spark,mail,database
```

### Disbursement Alerts

```bash
# Enable/disable disbursement failure alerts
DISBURSEMENT_ALERT_ENABLED=true

# Comma-separated admin emails
DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com
```

### Queue Configuration

```bash
# Notification queue connection (default: database)
NOTIFICATION_QUEUE_CONNECTION=database

# Default notification queue name
NOTIFICATION_QUEUE=default
```

## Testing Notifications

### Manual Testing Commands

```bash
# Test simple notification
php artisan test:notification --email=user@example.com --mobile=09171234567

# Test balance notification (user mode)
php artisan test:balance-notification --mode=user

# Test balance notification (system mode)
php artisan test:balance-notification --mode=system

# Test help notification
php artisan test:help-notification --type=general

# Test disbursement failure alert
php artisan test:disbursement-failure --type=timeout

# Test SMS balance command
php artisan test:sms-balance

# Test SMS router with BALANCE command
php artisan test:sms-router "BALANCE" --mobile=09171234567
```

### Integration Testing

```bash
# Test voucher generation (triggers VouchersGeneratedSummary)
php artisan voucher:generate --amount=100 --count=1

# Test voucher redemption (triggers SendFeedbacksNotification)
# Requires existing voucher code
php artisan voucher:redeem <code> --mobile=09171234567

# Test payment confirmation (requires payment request)
php artisan test:direct-checkout 100

# Simulate disbursement failure
php artisan test:disbursement-failure --voucher=<code>
```

### Notification Fake Testing

```php
use Illuminate\Support\Facades\Notification;

// Fake all notifications
Notification::fake();

// Perform action that triggers notification
$user->notify(new BalanceNotification('user', $balance));

// Assert notification was sent
Notification::assertSentTo($user, BalanceNotification::class);

// Assert specific data
Notification::assertSentTo($user, BalanceNotification::class, function ($notification) {
    return $notification->getNotificationType() === 'balance';
});
```

## Debugging Notifications

### Check Queue Jobs

```bash
# View pending queue jobs
php artisan queue:work --once --verbose

# View failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all
```

### Check Notification Data

```php
// Query user notifications
$user->notifications()->get();

// Get unread notifications
$user->unreadNotifications;

// Get specific notification type
$user->notifications()
    ->where('type', 'App\Notifications\BalanceNotification')
    ->latest()
    ->first();
```

### Enable Debug Logging

Some notifications have debug flags (e.g., `SendFeedbacksNotification::DEBUG_ENABLED`). Enable for detailed logging:

```php
// app/Notifications/SendFeedbacksNotification.php
private const DEBUG_ENABLED = true;
```

## See Also

- [Notification System Guide](../guides/features/NOTIFICATION_SYSTEM.md) - Architecture and usage
- [Notification Templates](../guides/features/NOTIFICATION_TEMPLATES.md) - Template customization
- [AI Guidelines](../../.ai/guidelines/notifications.md) - Development guidelines
