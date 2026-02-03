# Notification Triggers and Recipients Guide

**Last Updated**: 2026-02-03  
**Audience**: Developers, System Administrators, DevOps

This guide documents **what triggers each notification**, **who receives them**, and **how to configure** notification delivery in the redeem-x application.

---

## Table of Contents

1. [Overview](#overview)
2. [Notification Trigger Summary](#notification-trigger-summary)
3. [Detailed Notification Flows](#detailed-notification-flows)
4. [Configuration Guide](#configuration-guide)
5. [Recipient Rules](#recipient-rules)
6. [Testing Notifications](#testing-notifications)
7. [Troubleshooting](#troubleshooting)

---

## Overview

### What Triggers Notifications?

Notifications in redeem-x are triggered by:

1. **Events** - Domain events fired by models/actions (e.g., `DisbursementFailed`)
2. **Pipeline Stages** - Post-generation/redemption pipeline steps (e.g., `SendFeedbacks`)
3. **Jobs** - Queued background jobs (e.g., `SendPaymentConfirmationSms`)
4. **Services** - Business logic in services (e.g., `BalanceService` checking thresholds)
5. **Commands** - Manual triggers via Artisan commands (e.g., SMS commands)

### Who Receives Notifications?

- **Voucher Issuer** (Owner) - The user who created the voucher
- **Voucher Redeemer** - The person redeeming the voucher
- **System Admins** - Users with 'admin' role
- **System User** - Primary admin (resolved via `SystemUserResolverService`)
- **Anonymous Recipients** - Email/SMS recipients without user accounts
- **Configured Recipients** - Email addresses/phone numbers from config files

---

## Notification Trigger Summary

| Notification | Trigger Event/Action | When It Fires | Recipient(s) | Channels |
|--------------|---------------------|---------------|--------------|----------|
| **VouchersGeneratedSummary** | Post-generation pipeline | After vouchers are created | Voucher owner/issuer | SMS (optional: email) |
| **SendFeedbacksNotification** | Post-redemption pipeline | After voucher is redeemed | Voucher owner/issuer | Email, SMS, Webhook (if configured in voucher instructions) |
| **DisbursementFailedNotification** | `DisbursementFailed` event | When disbursement to redeemer fails | System admins + config emails | Email |
| **LowBalanceAlert** | `BalanceService` threshold check | When gateway balance falls below threshold | Alert recipients (from `BalanceAlert` model) | Email, SMS, Webhook |
| **PaymentConfirmationNotification** | `PaymentDetectedButNotConfirmed` event | When settlement payment detected but not confirmed | Payment requester (mobile) | SMS |
| **BalanceNotification** | SMS command `BALANCE` | User sends SMS balance query | SMS sender | SMS |
| **HelpNotification** | SMS command `HELP` | User sends SMS help request | SMS sender | SMS |

---

## Detailed Notification Flows

### 1. VouchersGeneratedSummary

**🎯 Purpose**: Notify the voucher issuer that their vouchers have been generated successfully.

**📍 Trigger Location**: `packages/voucher/src/Pipelines/GeneratedVouchers/NotifyBatchCreator.php`

**⚙️ How It Works**:
```php
// Triggered in post-generation pipeline (config/voucher-pipeline.php)
'post-generation' => [
    // ... other stages
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator::class,
]
```

**🔧 Configuration Requirements**:

1. **Interface Binding** (required):
   ```php
   // app/Providers/AppServiceProvider.php
   $this->app->bind(
       \LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface::class,
       \App\Notifications\VouchersGeneratedSummary::class
   );
   ```

2. **Voucher Owner Must Have Mobile**:
   ```php
   // Only fires if:
   $owner = $voucher->owner; // User model
   $owner->mobile; // Must be set (e.g., "09173011987")
   ```

3. **Channel Configuration** (`.env`):
   ```bash
   # Default: SMS only
   VOUCHERS_GENERATED_CHANNELS=engage_spark
   
   # Enable email + SMS
   VOUCHERS_GENERATED_CHANNELS=engage_spark,mail
   ```

**👤 Recipients**:
- **Primary**: Voucher owner (the user who created the vouchers)
- **Notifiable**: `$voucher->owner` (User model)
- **Delivery**: Via `$owner->notify($notification)`

**📧 Example Message**:
> **Subject**: Vouchers Generated Successfully  
> **SMS**: "✅ 5 vouchers generated! Total: ₱500.00. Codes: ABC-001 to ABC-005. Redeem at http://redeem-x.test/redeem"

**❓ User Question Answered**:
> **Q**: "When I create a voucher, I should get a notification of the voucher codes? Not sure if this is just for SMS."
> 
> **A**: Yes! You receive `VouchersGeneratedSummary` notification after voucher generation. By default it's SMS-only, but you can enable email by setting `VOUCHERS_GENERATED_CHANNELS=engage_spark,mail`. The notification includes voucher codes, total amount, and redemption URL.

---

### 2. SendFeedbacksNotification

**🎯 Purpose**: Notify the voucher issuer when their voucher is redeemed, with optional feedback data (location, signature, selfie).

**📍 Trigger Location**: `app/Pipelines/RedeemedVoucher/SendFeedbacks.php`

**⚙️ How It Works**:
```php
// Triggered in post-redemption pipeline (config/voucher-pipeline.php)
'post-redemption' => [
    // ... other stages
    \App\Pipelines\RedeemedVoucher\SendFeedbacks::class,
]
```

**🔧 Configuration Requirements**:

1. **Voucher Must Have Feedback Instructions**:
   ```json
   // voucher.instructions.feedback (VoucherInstructionsData)
   {
     "feedback": {
       "email": "issuer@example.com",
       "mobile": "09173011987",
       "webhook": "https://api.example.com/webhooks/redemption"
     }
   }
   ```

2. **Feedback Fields Are Optional**:
   - If `feedback.email` is set → Email sent
   - If `feedback.mobile` is set → SMS sent
   - If `feedback.webhook` is set → Webhook POST request
   - If ALL are empty → No notification sent

3. **Channel Configuration** (`.env`):
   ```bash
   # Default: Email + SMS
   VOUCHER_REDEEMED_CHANNELS=mail,engage_spark
   
   # Add webhook support
   VOUCHER_REDEEMED_CHANNELS=mail,engage_spark,webhook
   ```

**👤 Recipients**:
- **Primary**: Voucher issuer (configured in feedback instructions)
- **Notifiable**: `AnonymousNotifiable` (routed to email/mobile/webhook) + `$voucher->owner` (for database audit)
- **Delivery**: 
  - External: `Notification::routes($routes)->notify($notification)`
  - Audit: `$voucher->owner->notify($notification)` (database only)

**📧 Example Message**:
> **Subject**: Voucher Redeemed: ABC-12345  
> **Email**: "Your voucher ABC-12345 (₱100.00) was redeemed on Feb 3, 2026 at 1:30 PM. Redeemer location: 14.5995° N, 120.9842° E (Manila, Philippines). Signature and selfie attached."

**🖼️ Attachments** (if inputs were required):
- Signature image (PNG)
- Selfie image (JPEG)
- Location map snapshot (PNG)

**❓ User Question Answered**:
> **Q**: "The issuer gets a notification if the redeemed voucher has a feedback requirement."
> 
> **A**: Exactly! When a voucher is redeemed, `SendFeedbacksNotification` checks the `feedback` instructions (email, mobile, webhook). If any are configured, the issuer receives a notification with redemption details and optional attachments (signature, selfie, location map). This happens in the post-redemption pipeline automatically.

---

### 3. DisbursementFailedNotification

**🎯 Purpose**: Alert system administrators immediately when a disbursement to a redeemer fails.

**📍 Trigger Location**: `app/Listeners/NotifyAdminOfDisbursementFailure.php`

**⚙️ How It Works**:
```php
// Listens to DisbursementFailed event
Event::listen(
    DisbursementFailed::class,
    NotifyAdminOfDisbursementFailure::class
);
```

**Event Source**: `LBHurtado\Wallet\Events\DisbursementFailed` (fired by payment gateway)

**🔧 Configuration Requirements**:

1. **Enable Alerts** (`.env`):
   ```bash
   DISBURSEMENT_ALERT_ENABLED=true  # Default: true
   ```

2. **Configure Recipients** (`.env`):
   ```bash
   # Comma-separated list of emails (used as fallback if no admin users exist)
   DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com
   ```

3. **Throttling Configuration** (`.env`):
   ```bash
   # Prevent alert spam during outages (suppress duplicate alerts for N minutes)
   DISBURSEMENT_ALERT_THROTTLE_MINUTES=30  # Default: 30
   ```

4. **Channel Configuration** (`.env`):
   ```bash
   # Default: Email only (critical alerts)
   DISBURSEMENT_FAILED_CHANNELS=mail
   ```

**👤 Recipients** (in order of priority):

1. **System User** (primary admin)
   - Resolved via `SystemUserResolverService`
   - First user in database with highest privileges

2. **Admin Role Users**
   - All users with `admin` role: `User::whereHas('roles', fn($q) => $q->where('name', 'admin'))`

3. **Config Emails** (fallback)
   - Only used if no admin users exist
   - Emails from `DISBURSEMENT_ALERT_EMAILS`

**📧 Example Message**:
> **Subject**: 🚨 Disbursement Failed: ABC-12345  
> **Body**: "Failed to disburse ₱100.00 for voucher ABC-12345 to mobile 09173011987. Error: Network timeout. Timestamp: 2026-02-03 13:45:23"

**🛡️ Throttling Behavior**:
- First alert for an error type → Sent immediately
- Subsequent alerts for same error type → Suppressed for 30 minutes (configurable)
- Different error types → Sent independently (timeout vs gateway error)

---

### 4. LowBalanceAlert

**🎯 Purpose**: Warn administrators when a payment gateway account balance falls below a threshold.

**📍 Trigger Location**: `app/Services/BalanceService.php` (method: `checkAlerts()`)

**⚙️ How It Works**:
```php
// Triggered when balance check detects threshold breach
// Command: php artisan balances:check --account=113-001-00001-9

protected function checkAlerts(AccountBalance $balance): void
{
    $alerts = $balance->alerts()
        ->where('enabled', true)
        ->where('threshold', '>', $balance->balance)
        ->get();
    
    foreach ($alerts as $alert) {
        if (!$alert->wasTriggeredToday()) {
            $this->triggerAlert($balance, $alert);
        }
    }
}
```

**🔧 Configuration Requirements**:

1. **Create Balance Alert** (via BalanceService):
   ```php
   $balanceService->createAlert(
       accountNumber: '113-001-00001-9',
       threshold: 50000, // ₱500.00 in centavos
       alertType: 'email', // 'email', 'sms', or 'webhook'
       recipients: ['finance@example.com', 'ops@example.com']
   );
   ```

2. **Database Record** (`balance_alerts` table):
   ```sql
   INSERT INTO balance_alerts (
       account_number, gateway, threshold, alert_type, recipients, enabled
   ) VALUES (
       '113-001-00001-9', 'netbank', 50000, 'email', '["admin@example.com"]', 1
   );
   ```

3. **Channel Configuration** (`.env`):
   ```bash
   # Default: Email only
   LOW_BALANCE_ALERT_CHANNELS=mail
   
   # Add SMS support
   LOW_BALANCE_ALERT_CHANNELS=mail,engage_spark
   ```

**👤 Recipients**:
- **Configured**: Recipients array from `BalanceAlert` model
- **Notifiable**: `AnonymousNotifiable` (routed to email/SMS/webhook)
- **Delivery**: `Notification::route($alertType, $recipients)->notify($notification)`

**📧 Example Message**:
> **Subject**: ⚠️ Low Balance Alert: 113-001-00001-9  
> **Body**: "Account 113-001-00001-9 has ₱437.50 available (threshold: ₱500.00). Please top up to continue disbursements."

**🛡️ Spam Prevention**:
- Alert fires at most **once per day** (checked via `wasTriggeredToday()`)
- `last_triggered_at` timestamp updated after each alert

---

### 5. PaymentConfirmationNotification

**🎯 Purpose**: Send SMS to payer when their settlement payment is detected but awaiting confirmation.

**📍 Trigger Location**: `app/Jobs/SendPaymentConfirmationSms.php` (dispatched by event listener)

**⚙️ How It Works**:
```php
// Event listener in AppServiceProvider
Event::listen(
    \App\Events\PaymentDetectedButNotConfirmed::class,
    function ($event) {
        \App\Jobs\SendPaymentConfirmationSms::dispatch(
            $event->paymentRequestId,
            $event->payerMobile,
            $event->amount,
            $event->voucherCode
        );
    }
);
```

**Event Source**: `App\Events\PaymentDetectedButNotConfirmed` (fired by NetBank webhook classifier)

**🔧 Configuration Requirements**:

1. **Channel Configuration** (`.env`):
   ```bash
   # Default: SMS only (immediate notification)
   PAYMENT_CONFIRMATION_CHANNELS=engage_spark
   ```

2. **Queue Configuration**:
   - Priority queue: `normal` (user-facing notification)
   - Retry strategy: 3 attempts with backoff (10s, 30s, 60s)

**👤 Recipients**:
- **Primary**: Payment requester (payer who sent the settlement payment)
- **Notifiable**: `AnonymousNotifiable` (routed to mobile) + `PaymentRequest` (database audit)
- **Delivery**: 
  - SMS: `Notification::route('engage_spark', $mobile)->notify($notification)`
  - Audit: `$paymentRequest->notify($notification)` (database only)

**📧 Example Message**:
> **SMS**: "Payment of ₱100.00 detected for voucher ABC-12345. To confirm, reply: CONFIRM ABC-12345. To cancel: CANCEL ABC-12345."

**🛡️ Race Condition Protection**:
- Job checks if payment is still `pending` before sending
- Skips notification if payment was already confirmed/cancelled

---

### 6. BalanceNotification

**🎯 Purpose**: Respond to user's SMS balance inquiry with their wallet balance.

**📍 Trigger Location**: `packages/omnichannel/src/Handlers/BalanceSMSHandler.php`

**⚙️ How It Works**:
```bash
# User sends SMS
SMS: "BALANCE"

# System processes via SMS router
# Handler resolves user by mobile number
# Notification sent with current balance
```

**🔧 Configuration Requirements**:

1. **Channel Configuration** (`.env`):
   ```bash
   # Default: SMS only
   BALANCE_NOTIFICATION_CHANNELS=engage_spark
   ```

2. **User Must Exist**:
   - User account must exist with matching mobile number
   - Mobile number format: `09XXXXXXXXX` (Philippine format)

**👤 Recipients**:
- **Primary**: SMS sender
- **Notifiable**: `User` (resolved by mobile number)
- **Delivery**: `$user->notify(new BalanceNotification($balance))`

**📧 Example Message**:
> **SMS**: "Your balance: ₱1,234.56"

---

### 7. HelpNotification

**🎯 Purpose**: Respond to user's SMS help request with available commands.

**📍 Trigger Location**: `packages/omnichannel/src/Handlers/HelpSMSHandler.php`

**⚙️ How It Works**:
```bash
# User sends SMS
SMS: "HELP"

# System processes via SMS router
# Handler sends help text with available commands
```

**🔧 Configuration Requirements**:

1. **Channel Configuration** (`.env`):
   ```bash
   # Default: SMS only
   HELP_NOTIFICATION_CHANNELS=engage_spark
   ```

**👤 Recipients**:
- **Primary**: SMS sender
- **Notifiable**: `AnonymousNotifiable` (routed to sender's mobile)
- **Delivery**: `Notification::route('engage_spark', $mobile)->notify(new HelpNotification())`

**📧 Example Message**:
> **SMS**: "Available commands: BALANCE - Check wallet balance | REDEEM <CODE> - Redeem voucher | HELP - Show this message"

---

## Configuration Guide

### Environment Variables Summary

```bash
#---------------------------------------------------------
# Notification Channels
#---------------------------------------------------------
# Configure which channels to use for each notification type
# Format: Comma-separated list (no spaces)
# Options: engage_spark, mail, database, webhook

BALANCE_NOTIFICATION_CHANNELS=engage_spark
DISBURSEMENT_FAILED_CHANNELS=mail
HELP_NOTIFICATION_CHANNELS=engage_spark
LOW_BALANCE_ALERT_CHANNELS=mail
PAYMENT_CONFIRMATION_CHANNELS=engage_spark
VOUCHER_REDEEMED_CHANNELS=mail,engage_spark
VOUCHERS_GENERATED_CHANNELS=engage_spark
TEST_NOTIFICATION_CHANNELS=engage_spark,mail,database

#---------------------------------------------------------
# Disbursement Failure Alerts
#---------------------------------------------------------
# Enable/disable disbursement failure notifications
DISBURSEMENT_ALERT_ENABLED=true

# Recipient emails (fallback if no admin users exist)
DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com

# Throttle duplicate alerts (minutes)
DISBURSEMENT_ALERT_THROTTLE_MINUTES=30

#---------------------------------------------------------
# Notification Queue Configuration
#---------------------------------------------------------
# Queue connection for notifications
NOTIFICATION_QUEUE_CONNECTION=database

# Default queue name
NOTIFICATION_QUEUE=default

#---------------------------------------------------------
# Database Logging
#---------------------------------------------------------
# Enable database logging for audit trail
NOTIFICATION_DATABASE_LOGGING=true
```

### Enabling/Disabling Notifications

#### Method 1: Channel Configuration (Recommended)

Disable a notification by setting its channels to empty:

```bash
# Disable voucher generation notifications
VOUCHERS_GENERATED_CHANNELS=

# Disable disbursement failure alerts
DISBURSEMENT_ALERT_ENABLED=false
```

#### Method 2: Interface Unbinding

Remove the notification interface binding:

```php
// app/Providers/AppServiceProvider.php

// Comment out to disable VouchersGeneratedSummary
// $this->app->bind(
//     \LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface::class,
//     \App\Notifications\VouchersGeneratedSummary::class
// );
```

#### Method 3: Pipeline Removal

Remove notification stages from pipeline:

```php
// config/voucher-pipeline.php

'post-generation' => [
    // Comment out to disable voucher generation notifications
    // \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator::class,
],

'post-redemption' => [
    // Comment out to disable feedback notifications
    // \App\Pipelines\RedeemedVoucher\SendFeedbacks::class,
],
```

---

## Recipient Rules

### User-Based Recipients (Database)

Notifications sent to `User` models are **always logged to database** (audit trail):

```php
// config/notifications.php
'database_logging' => [
    'always_log_for' => [
        'App\\Models\\User',
        'App\\Models\\PaymentRequest',
    ],
]
```

**Example**:
```php
// Sends SMS + stores in database
$user->notify(new VouchersGeneratedSummary($vouchers));

// Database record created:
// notifications.notifiable_type = 'App\Models\User'
// notifications.notifiable_id = 123
```

### Anonymous Recipients (No Database)

Notifications sent to `AnonymousNotifiable` are **NOT logged to database**:

```php
// config/notifications.php
'database_logging' => [
    'never_log_for' => [
        'Illuminate\\Notifications\\AnonymousNotifiable',
    ],
]
```

**Example**:
```php
// Sends SMS only (no database record)
Notification::route('engage_spark', '09173011987')
    ->notify(new HelpNotification());
```

### Dual Delivery Pattern (External + Audit)

Many notifications use dual delivery for external communication + audit trail:

```php
// 1. Send to external channels (mail/SMS/webhook)
Notification::routes($routes)->notify(new SendFeedbacksNotification($code));

// 2. Store audit copy in database (tied to user)
if ($voucher->owner) {
    $voucher->owner->notify(new SendFeedbacksNotification($code));
}
```

---

## Testing Notifications

### Test Commands

```bash
# Test voucher generation notification
php artisan test:voucher-generation-notification

# Test balance notification
php artisan test:balance-notification lester@hurtado.ph 09173011987

# Test help notification
php artisan test:help-notification 09173011987

# Test disbursement failure alert
php artisan test:disbursement-failure --type=timeout

# Test complete redemption flow with feedback
php artisan test:notification --email=lester@hurtado.ph --sms=09173011987 --with-signature --with-selfie --with-location
```

### Manual Testing via SMS

```bash
# Send SMS to configured number
# Format: <COMMAND> [args]

BALANCE              # Triggers BalanceNotification
HELP                 # Triggers HelpNotification
REDEEM ABC-12345     # Triggers redemption flow → SendFeedbacksNotification (if configured)
CONFIRM ABC-12345    # Triggers payment confirmation (if pending payment exists)
```

### Testing with Fake Mode

```bash
# Enable fake notification delivery (logs only, no actual sending)
NOTIFICATION_FAKE_MODE=true

# Run tests
php artisan test tests/Feature/Notifications/
```

---

## Troubleshooting

### Issue: VouchersGeneratedSummary Not Firing

**Checklist**:
1. ✅ Interface bound in `AppServiceProvider.php`?
   ```php
   dd(app()->bound(\LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface::class)); // true
   ```

2. ✅ Voucher owner has mobile number?
   ```php
   dd($voucher->owner->mobile); // "09173011987"
   ```

3. ✅ Pipeline stage enabled in `config/voucher-pipeline.php`?
   ```php
   // Check: NotifyBatchCreator is in 'post-generation' pipeline
   ```

4. ✅ Channels configured?
   ```bash
   php artisan config:cache
   php artisan tinker
   >>> config('notifications.channels.vouchers_generated')
   // ["engage_spark"]
   ```

### Issue: SendFeedbacksNotification Not Sending

**Checklist**:
1. ✅ Feedback instructions exist in voucher?
   ```php
   dd($voucher->getData()->instructions->feedback->toArray());
   // ['email' => 'issuer@example.com', 'mobile' => '09173011987']
   ```

2. ✅ Pipeline stage enabled?
   ```php
   // Check: SendFeedbacks is in 'post-redemption' pipeline
   ```

3. ✅ Channels not empty?
   ```bash
   >>> config('notifications.channels.voucher_redeemed')
   // ["mail", "engage_spark"]
   ```

### Issue: DisbursementFailedNotification Not Received

**Checklist**:
1. ✅ Alerts enabled?
   ```bash
   >>> config('disbursement.alerts.enabled')
   // true
   ```

2. ✅ Admin users exist OR config emails set?
   ```php
   dd(User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count());
   // > 0
   // OR
   dd(config('disbursement.alerts.emails'));
   // ["support@example.com"]
   ```

3. ✅ Not throttled?
   ```bash
   # Check cache for throttle key
   >>> Cache::has("disbursement_alert_throttle:Brick\\Money\\Exception\\CurrencyConversionException")
   // false (not throttled)
   ```

### Issue: Notification Queued But Not Sent

**Checklist**:
1. ✅ Queue worker running?
   ```bash
   php artisan queue:work --queue=high,normal,low --tries=3
   ```

2. ✅ Check failed jobs table?
   ```bash
   php artisan queue:failed
   ```

3. ✅ Check logs for errors?
   ```bash
   tail -f storage/logs/laravel.log | grep "Notification"
   ```

---

## Related Documentation

- **Architecture**: `docs/guides/features/NOTIFICATION_SYSTEM.md`
- **Dispatch Reference**: `docs/api/NOTIFICATION_DISPATCH_REFERENCE.md`
- **AI Guidelines**: `.ai/guidelines/notifications.md`
- **Template Customization**: `docs/guides/features/NOTIFICATION_TEMPLATES.md`
- **Completion Report**: `docs/completed/implementations/NOTIFICATION_SYSTEM_RATIONALIZATION_COMPLETE.md`

---

## Quick Reference Card

| I want to... | Configuration |
|--------------|---------------|
| **Disable all voucher generation notifications** | `VOUCHERS_GENERATED_CHANNELS=` |
| **Get email when vouchers are generated** | `VOUCHERS_GENERATED_CHANNELS=engage_spark,mail` |
| **Disable disbursement failure alerts** | `DISBURSEMENT_ALERT_ENABLED=false` |
| **Add more admins to failure alerts** | `DISBURSEMENT_ALERT_EMAILS=admin1@example.com,admin2@example.com` |
| **Stop feedback notifications** | Remove `feedback` fields in voucher instructions OR comment out `SendFeedbacks` in pipeline |
| **Test notifications without sending** | `NOTIFICATION_FAKE_MODE=true` |
| **Change queue priority for notification** | Edit `config/notifications.php` → `queue.queues` |
| **Stop database logging for all notifications** | `NOTIFICATION_DATABASE_LOGGING=false` |

---

**End of Document**
