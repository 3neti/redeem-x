# Notification Queue Configuration Issue

**Date**: 2026-02-03  
**Issue**: Voucher generation and redemption notifications not being received despite being logged

## Problem Summary

### Symptoms
1. ✅ VouchersGeneratedSummary notification logged to database
2. ✅ SendFeedbacksNotification logged to application logs
3. ❌ Email/SMS not received by users
4. ⚠️ 33 pending jobs in queue

### Root Cause

**Queue worker mismatch**: The queue worker was running as:
```bash
php artisan queue:work  # Only processes "default" queue
```

But notifications are configured to use priority queues:
- `high` queue: DisbursementFailedNotification, LowBalanceAlert
- `normal` queue: PaymentConfirmationNotification, SendFeedbacksNotification (voucher_redeemed)
- `low` queue: VouchersGeneratedSummary, BalanceNotification, HelpNotification

### Evidence

**Jobs table breakdown**:
```
Queue: high   | Jobs: 1   (critical alerts)
Queue: normal | Jobs: 2   (user-facing notifications)
Queue: low    | Jobs: 16  (informational notifications)
Queue: sync   | Jobs: 14  (sync jobs, not notifications)
```

**Recent notifications in database** (user_id=2):
- `2026-02-03 08:14:49` - HelpNotification
- `2026-02-03 08:14:42` - BalanceNotification
- `2026-02-03 08:13:45` - VouchersGeneratedSummary (voucher 8PWM generation)
- `2026-02-03 08:11:38` - VouchersGeneratedSummary

All logged to database but **queued jobs not processed** because worker wasn't listening to the right queues.

---

## Solution

### Fix 1: Run Queue Worker with Correct Queues (Recommended)

Stop the current worker and restart with priority queues:

```bash
# Stop current worker
pkill -f "queue:work"

# Start with priority queues (high processed first, then normal, then low)
php artisan queue:work --queue=high,normal,low --tries=3 --timeout=60
```

### Fix 2: Update composer dev Command

If using `composer dev`, update the queue worker command:

```json
// composer.json
{
  "scripts": {
    "dev": [
      "Composer\\Config::disableProcessTimeout",
      "@php -r \"echo 'Starting development services...' . PHP_EOL;\"",
      "concurrently --kill-others --names=\"SERVE,QUEUE,PAIL,VITE\" -c \"bgBlue.bold,bgMagenta.bold,bgCyan.bold,bgGreen.bold\" \"php artisan serve\" \"php artisan queue:work --queue=high,normal,low --tries=3\" \"php artisan pail --timeout=0\" \"npm run dev\""
    ]
  }
}
```

### Fix 3: Process Pending Jobs

After restarting the worker with correct queues, pending jobs will be processed automatically. To verify:

```bash
# Watch queue processing
watch -n 1 'php -r "require \"vendor/autoload.php\"; \$app = require_once \"bootstrap/app.php\"; \$app->make(\"Illuminate\\Contracts\\Console\\Kernel\")->bootstrap(); \$jobs = DB::table(\"jobs\")->count(); echo \"Pending jobs: \$jobs\n\";"'

# Or check logs
tail -f storage/logs/laravel.log | grep "Notification\|SendFeedbacks\|VouchersGenerated"
```

---

## Verification

### Test Voucher Generation Notification

```bash
# Generate a voucher
php artisan tinker
>>> $user = User::find(2);
>>> $voucher = $user->generateVouchers(1, 100);  // 1 voucher, ₱100

# Check queue
>>> DB::table('jobs')->where('queue', 'low')->count();
// Should increase by 1

# Restart worker (if not already using --queue=high,normal,low)
# Wait a few seconds

# Check if job was processed
>>> DB::table('jobs')->where('queue', 'low')->count();
// Should decrease by 1

# Check notifications table
>>> DB::table('notifications')->where('type', 'App\\Notifications\\VouchersGeneratedSummary')->count();
// Should have new entry
```

### Test Redemption Feedback Notification

```bash
# Create voucher with feedback instructions
php artisan tinker
>>> $user = User::find(2);
>>> $instructions = new VoucherInstructionsData([
...     'cash' => ['amount' => 50.0],
...     'feedback' => [
...         'email' => 'lester@hurtado.ph',
...         'mobile' => '09173011987',
...     ],
... ]);
>>> $vouchers = $user->generateVouchers(1, $instructions);
>>> $code = $vouchers->first()->code;

# Redeem the voucher (via UI at http://redeem-x.test/redeem)
# Enter code, complete flow

# Check logs
tail -f storage/logs/laravel.log | grep SendFeedbacks
// Should see: [SendFeedbacks] Feedback notification sent

# Check queue (normal queue)
>>> DB::table('jobs')->where('queue', 'normal')->count();

# Verify email/SMS sent (check EngageSpark dashboard or Mailtrap)
```

### Test SMS Commands

```bash
# Test BALANCE command
php artisan test:sms-router "BALANCE" --mobile=09173011987
# ✅ Response: "Your balance: ₱X,XXX.XX"

# Test HELP command
php artisan test:sms-router "HELP" --mobile=09173011987
# ✅ Response: List of available commands

# Check queue (low queue)
>>> DB::table('jobs')->where('queue', 'low')->count();
```

---

## Why This Happened

### Queue Configuration (config/notifications.php)

```php
'queue' => [
    'queues' => [
        'high' => [
            'disbursement_failed',
            'low_balance_alert',
        ],
        'normal' => [
            'payment_confirmation',
            'voucher_redeemed',  // SendFeedbacksNotification
        ],
        'low' => [
            'vouchers_generated',  // VouchersGeneratedSummary
            'balance',
            'help',
        ],
    ],
],
```

### BaseNotification Implementation

All notifications extend `BaseNotification` which uses `viaQueues()` to route to the correct queue:

```php
// app/Notifications/BaseNotification.php
public function viaQueues(): array
{
    return [
        'engage_spark' => $this->getQueueName(),
        'mail' => $this->getQueueName(),
        'database' => $this->getQueueName(),
    ];
}

protected function getQueueName(): string
{
    $type = $this->getNotificationType();
    $queues = config('notifications.queue.queues', []);
    
    foreach ($queues as $queueName => $types) {
        if (in_array($type, $types)) {
            return $queueName;
        }
    }
    
    return config('notifications.queue.default_queue', 'default');
}
```

### Why Default Queue Worker Doesn't Work

Laravel's `queue:work` (without `--queue` option) only processes jobs on the **default** queue (usually named "default" or "sync"). Jobs on `high`, `normal`, `low` queues are never picked up.

---

## Best Practices

### Development Environment

Always run queue worker with priority queues:

```bash
php artisan queue:work --queue=high,normal,low --tries=3
```

Or use `composer dev` which should include this configuration.

### Production Environment

Use Supervisor to manage queue workers:

```ini
[program:redeem-x-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=high,normal,low --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

### Monitoring

Set up queue monitoring:

```bash
# Check pending jobs by queue
php artisan queue:monitor high,normal,low --max=100

# Or use Laravel Horizon (for Redis queue)
# Or custom monitoring dashboard
```

---

## Related Documentation

- **Notification System**: `docs/guides/features/NOTIFICATION_SYSTEM.md`
- **Notification Triggers**: `docs/guides/features/NOTIFICATION_TRIGGERS_AND_RECIPIENTS.md`
- **Queue Configuration**: `config/notifications.php`

---

## Quick Fix Checklist

- [ ] Stop current queue worker: `pkill -f "queue:work"`
- [ ] Restart with priority queues: `php artisan queue:work --queue=high,normal,low --tries=3`
- [ ] Verify pending jobs are being processed: `watch -n 1 'php -r "..."'`
- [ ] Test voucher generation notification
- [ ] Test redemption feedback notification
- [ ] Test SMS commands (BALANCE, HELP)
- [ ] Update `composer dev` script to use correct queue command
- [ ] Deploy queue worker configuration to production

---

**End of Document**
