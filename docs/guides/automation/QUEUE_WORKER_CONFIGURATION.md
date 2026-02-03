# Queue Worker Configuration Guide

**Last Updated**: 2026-02-03  
**Audience**: DevOps, System Administrators, Developers

This guide provides comprehensive configuration for Laravel queue workers optimized for the notification system's priority-based architecture.

---

## Table of Contents

1. [Overview](#overview)
2. [Queue Architecture](#queue-architecture)
3. [Worker Configurations](#worker-configurations)
4. [Parameter Reference](#parameter-reference)
5. [Deployment Guides](#deployment-guides)
6. [Monitoring & Troubleshooting](#monitoring--troubleshooting)
7. [Performance Tuning](#performance-tuning)

---

## Overview

### Why Priority Queues?

The notification system uses **4 priority queues** to ensure critical alerts are processed before informational messages:

```
high → normal → low → default
 ↓       ↓       ↓       ↓
Critical  User-  Info   General
Alerts   Facing  Only    Jobs
```

**Benefits**:
- ✅ Critical disbursement failures processed immediately
- ✅ User-facing notifications prioritized over batch jobs
- ✅ Resource-intensive jobs don't block time-sensitive alerts
- ✅ Separate failure handling per priority level

### Queue-to-Notification Mapping

| Queue | Notifications | Criticality | Avg. Processing Time |
|-------|--------------|-------------|---------------------|
| **high** | DisbursementFailedNotification, LowBalanceAlert | 🔴 Critical | 2-5s (email) |
| **normal** | PaymentConfirmationNotification, SendFeedbacksNotification | 🟡 Important | 5-15s (email+attachments) |
| **low** | VouchersGeneratedSummary, BalanceNotification, HelpNotification | 🟢 Informational | 1-3s (SMS only) |
| **default** | Non-notification jobs (batch processing, reports, etc.) | ⚪ General | Varies |

---

## Queue Architecture

### Configuration Source

Queue priorities are defined in `config/notifications.php`:

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

### How Notifications Route to Queues

All notifications extend `BaseNotification` which implements `viaQueues()`:

```php
// app/Notifications/BaseNotification.php
public function viaQueues(): array
{
    return [
        'engage_spark' => $this->getQueueName(),  // SMS channel
        'mail' => $this->getQueueName(),          // Email channel
        'database' => $this->getQueueName(),      // Database channel
    ];
}

protected function getQueueName(): string
{
    $type = $this->getNotificationType();
    $queues = config('notifications.queue.queues', []);
    
    // Find which queue this notification type belongs to
    foreach ($queues as $queueName => $types) {
        if (in_array($type, $types)) {
            return $queueName;  // Returns: 'high', 'normal', or 'low'
        }
    }
    
    return config('notifications.queue.default_queue', 'default');
}
```

**Example flow**:
1. `DisbursementFailedNotification` fires
2. `getNotificationType()` returns `'disbursement_failed'`
3. Lookup finds `'disbursement_failed'` in `'high'` queue
4. Job queued to `jobs` table with `queue = 'high'`
5. High-priority worker picks it up immediately

---

## Worker Configurations

### 🔴 High Priority Queue

**Purpose**: Process critical system alerts that require immediate attention.

**Notifications**:
- `DisbursementFailedNotification` - Alert admins when disbursements fail
- `LowBalanceAlert` - Warn when gateway balance falls below threshold

**Command**:
```bash
php artisan queue:work --queue=high \
  --tries=3 \
  --timeout=60 \
  --sleep=3 \
  --backoff=10,30,60 \
  --rest=0
```

**Parameter Breakdown**:

| Parameter | Value | Reason |
|-----------|-------|--------|
| `--queue` | `high` | Only process critical alerts |
| `--tries` | `3` | Retry failed jobs up to 3 times (network issues, transient errors) |
| `--timeout` | `60` | Kill job if email/SMS delivery takes >60 seconds |
| `--sleep` | `3` | Check queue every 3 seconds when empty (high responsiveness) |
| `--backoff` | `10,30,60` | 1st retry: 10s, 2nd: 30s, 3rd: 60s (quick initial retry for transient failures) |
| `--rest` | `0` | No pause between jobs (maximum throughput) |

**Characteristics**:
- **Volume**: Low (1-5 jobs/day in normal operation, spikes during outages)
- **Latency**: <10 seconds from queue to delivery
- **Failure impact**: Critical - affects operations team's ability to respond to issues
- **Recommended processes**: 1 (sufficient for low volume)

---

### 🟡 Normal Priority Queue

**Purpose**: Process user-facing notifications that users are actively waiting for.

**Notifications**:
- `PaymentConfirmationNotification` - SMS confirmation for settlement payments
- `SendFeedbacksNotification` - Email voucher redemption details with attachments

**Command**:
```bash
php artisan queue:work --queue=normal \
  --tries=3 \
  --timeout=90 \
  --sleep=5 \
  --backoff=15,45,120 \
  --rest=0
```

**Parameter Breakdown**:

| Parameter | Value | Reason |
|-----------|-------|--------|
| `--queue` | `normal` | Process user-facing notifications |
| `--tries` | `3` | Standard retry count for important notifications |
| `--timeout` | `90` | Allow time for email rendering + attachment processing (signature, selfie, location maps) |
| `--sleep` | `5` | Check every 5 seconds (balanced responsiveness) |
| `--backoff` | `15,45,120` | 1st retry: 15s, 2nd: 45s, 3rd: 120s (progressive backoff for email gateway issues) |
| `--rest` | `0` | No pause (maintain throughput during redemption spikes) |

**Characteristics**:
- **Volume**: Medium (10-50 jobs/hour during peak redemption times)
- **Latency**: <30 seconds from redemption to email delivery
- **Failure impact**: High - affects user experience and trust
- **Recommended processes**: 2 (handle concurrent redemptions)

**Why longer timeout (90s)?**

`SendFeedbacksNotification` includes email attachments:
1. Fetch signature image from storage (2-5s)
2. Fetch selfie image from storage (2-5s)
3. Generate location map snapshot (5-10s)
4. Render email HTML with embedded images (3-5s)
5. SMTP delivery to mail server (10-20s)
6. **Total**: 22-45 seconds typical, 90s allows safety margin

---

### 🟢 Low Priority Queue

**Purpose**: Process informational notifications that can tolerate delays.

**Notifications**:
- `VouchersGeneratedSummary` - SMS notification of generated voucher codes
- `BalanceNotification` - Response to SMS balance inquiry
- `HelpNotification` - Response to SMS help request

**Command**:
```bash
php artisan queue:work --queue=low \
  --tries=2 \
  --timeout=30 \
  --sleep=10 \
  --backoff=30,90 \
  --rest=1
```

**Parameter Breakdown**:

| Parameter | Value | Reason |
|-----------|-------|--------|
| `--queue` | `low` | Process informational notifications only |
| `--tries` | `2` | Fewer retries (less critical if ultimately fails) |
| `--timeout` | `30` | Simple SMS messages should complete in <30 seconds |
| `--sleep` | `10` | Check every 10 seconds (conserve resources, tolerate delay) |
| `--backoff` | `30,90` | 1st retry: 30s, 2nd: 90s (longer delays acceptable) |
| `--rest` | `1` | 1 second pause between jobs (reduce CPU usage) |

**Characteristics**:
- **Volume**: High (50-200 jobs/hour during active voucher generation)
- **Latency**: 10-60 seconds acceptable
- **Failure impact**: Low - informational only, user can regenerate if needed
- **Recommended processes**: 1 (single worker sufficient)

**Why rest=1?**

Low-priority notifications are high-volume but not time-sensitive. A 1-second pause between jobs:
- Reduces CPU usage by 20-30%
- Allows other processes (web requests, high/normal queues) to use resources
- Has negligible impact on user experience (voucher generation is async anyway)

---

### ⚪ Default Queue

**Purpose**: Catch-all for general application jobs not related to notifications.

**Jobs**:
- Batch data processing
- Report generation
- Cache warming
- Database maintenance
- Third-party API syncs

**Command**:
```bash
php artisan queue:work --queue=default \
  --tries=3 \
  --timeout=120 \
  --sleep=5 \
  --backoff=30,60,120 \
  --rest=0
```

**Parameter Breakdown**:

| Parameter | Value | Reason |
|-----------|-------|--------|
| `--queue` | `default` | Catch-all for non-priority jobs |
| `--tries` | `3` | Standard retry count |
| `--timeout` | `120` | Generous timeout for varied job types (reports, exports, etc.) |
| `--sleep` | `5` | Moderate check frequency |
| `--backoff` | `30,60,120` | 1st retry: 30s, 2nd: 60s, 3rd: 120s (standard progressive backoff) |
| `--rest` | `0` | No pause (maintain throughput for batch jobs) |

**Characteristics**:
- **Volume**: Varies (depends on application features)
- **Latency**: Minutes to hours acceptable
- **Failure impact**: Low to medium (depends on job type)
- **Recommended processes**: 2 (handle concurrent batch operations)

---

## Parameter Reference

### `--queue` (string)

**What it does**: Specifies which queue(s) the worker should process.

**Format**:
```bash
--queue=<queue_name>                # Single queue
--queue=<queue1>,<queue2>,<queue3>  # Multiple queues (priority order)
```

**Examples**:
```bash
--queue=high                        # Only high priority
--queue=high,normal,low             # All notification queues (high processed first)
--queue=high,normal,low,default     # All queues with priority
```

**How it works**:
- Worker processes queues **left-to-right** in priority order
- If `high` has jobs, `normal` and `low` are skipped until `high` is empty
- This ensures critical jobs never wait behind low-priority jobs

**Best practice**: Run **separate workers** per queue rather than combined queues. This ensures low-priority jobs don't starve completely during high-priority spikes.

---

### `--tries` (integer)

**What it does**: Maximum number of attempts before marking job as failed.

**Default**: `1` (no retries)

**Format**:
```bash
--tries=3  # Retry up to 3 times (4 total attempts: original + 3 retries)
```

**Recommendations**:

| Queue | Tries | Reason |
|-------|-------|--------|
| **high** | `3` | Critical alerts must be delivered; retry for transient failures |
| **normal** | `3` | User-facing; important to retry for network issues |
| **low** | `2` | Informational; fewer retries acceptable |
| **default** | `3` | Standard for general jobs |

**How retries work**:
1. Job fails (exception thrown or timeout)
2. Job re-queued with incremented attempt count
3. Worker waits for `backoff` delay before picking up retry
4. Process repeats until `--tries` exhausted
5. If still failing, job moved to `failed_jobs` table

**Example**:
```php
// In your notification:
public $tries = 3;  // Can also be set per-notification class
```

---

### `--timeout` (seconds)

**What it does**: Maximum execution time for a single job before force-killing it.

**Default**: `60` seconds

**Format**:
```bash
--timeout=90  # Kill job after 90 seconds
```

**Recommendations**:

| Queue | Timeout | Typical Job Duration | Safety Margin |
|-------|---------|---------------------|---------------|
| **high** | `60` | 2-5s (email/SMS) | 12x-30x |
| **normal** | `90` | 5-15s (email+attachments) | 6x-18x |
| **low** | `30` | 1-3s (SMS only) | 10x-30x |
| **default** | `120` | Varies widely | Generous |

**Why safety margin matters**:
- Network congestion during peak hours
- Email server slowdowns
- SMS gateway rate limiting
- Image processing delays (thumbnails, maps)

**What happens on timeout**:
1. Job process receives `SIGTERM` (graceful shutdown request)
2. 10-second grace period for cleanup
3. If still running, `SIGKILL` (force kill)
4. Job marked as failed, retry logic applies

**Setting per-job timeout**:
```php
// In your notification:
public $timeout = 90;  // Override worker timeout for this job
```

---

### `--sleep` (seconds)

**What it does**: How long to wait when queue is empty before checking again.

**Default**: `3` seconds

**Format**:
```bash
--sleep=10  # Wait 10 seconds before next check
```

**Recommendations**:

| Queue | Sleep | Check Frequency | Reason |
|-------|-------|----------------|--------|
| **high** | `3` | Every 3 seconds | Fast detection of critical alerts |
| **normal** | `5` | Every 5 seconds | Balanced responsiveness for user-facing jobs |
| **low** | `10` | Every 10 seconds | Conserve resources, delay acceptable |
| **default** | `5` | Every 5 seconds | Standard for general jobs |

**Resource impact**:

| Sleep | DB Queries/Hour | CPU Usage | Latency |
|-------|----------------|-----------|---------|
| `1` | 3,600 | High | <2s |
| `3` | 1,200 | Medium | <6s |
| `5` | 720 | Low-Medium | <10s |
| `10` | 360 | Low | <20s |

**Best practice**: Use shorter sleep for time-sensitive queues, longer sleep for batch/informational queues.

**Trade-off**:
- ⬇️ Lower sleep = higher responsiveness, higher resource usage
- ⬆️ Higher sleep = lower resource usage, higher latency

---

### `--backoff` (seconds)

**What it does**: Delay before retrying failed jobs. Progressive backoff reduces load during outages.

**Default**: `0` (immediate retry)

**Format**:
```bash
--backoff=10              # 10 seconds for all retries
--backoff=10,30,60        # 10s (1st), 30s (2nd), 60s (3rd)
--backoff=5,15,30,60,120  # Progressive delays (if --tries=5)
```

**Recommendations**:

| Queue | Backoff | Strategy | Reason |
|-------|---------|----------|--------|
| **high** | `10,30,60` | Quick initial, progressive | Transient failures often resolve quickly; critical jobs retry fast |
| **normal** | `15,45,120` | Moderate progressive | Email gateway issues may need time; avoid thundering herd |
| **low** | `30,90` | Longer delays | Not time-sensitive; reduce retry pressure |
| **default** | `30,60,120` | Standard progressive | General-purpose backoff |

**Backoff Strategies Explained**:

#### 1. **Quick Initial Retry** (high priority)
```bash
--backoff=10,30,60
```
- 1st retry: 10 seconds (catch transient network hiccups)
- 2nd retry: 30 seconds (email server restart)
- 3rd retry: 60 seconds (longer outage recovery)

**Use case**: SMS gateway had 2-second timeout, retry catches successful delivery.

#### 2. **Progressive Backoff** (normal priority)
```bash
--backoff=15,45,120
```
- 1st retry: 15 seconds
- 2nd retry: 45 seconds (3x multiplier)
- 3rd retry: 120 seconds (2.7x multiplier)

**Use case**: Email server throttling; longer delays reduce retry storm.

#### 3. **Long Delays** (low priority)
```bash
--backoff=30,90
```
- 1st retry: 30 seconds
- 2nd retry: 90 seconds

**Use case**: Informational SMS; no rush, reduce gateway load.

**Example scenario - Email server outage**:

Without backoff (`--backoff=0`):
```
12:00:00 - Job fails (email server down)
12:00:01 - Retry 1 fails (server still down)
12:00:02 - Retry 2 fails (server still down)
12:00:03 - Retry 3 fails → moved to failed_jobs
Result: All retries wasted in 3 seconds
```

With progressive backoff (`--backoff=10,30,60`):
```
12:00:00 - Job fails (email server down)
12:00:10 - Retry 1 fails (server still down)
12:00:40 - Retry 2 succeeds (server recovered at 12:00:35)
Result: Job delivered after 40 seconds
```

---

### `--rest` (seconds)

**What it does**: Pause duration **between** processing jobs (even when queue has jobs).

**Default**: `0` (no pause)

**Format**:
```bash
--rest=1  # 1 second pause after completing each job
```

**Recommendations**:

| Queue | Rest | Reason |
|-------|------|--------|
| **high** | `0` | No pause; process critical alerts immediately |
| **normal** | `0` | No pause; maintain user-facing responsiveness |
| **low** | `1` | 1-second pause to reduce CPU usage for high-volume informational jobs |
| **default** | `0` | No pause; maintain throughput |

**When to use `--rest`**:

✅ **Use rest=1 for**:
- High-volume, low-priority queues (e.g., `low` queue with 200 jobs/hour)
- Resource-constrained environments (shared hosting, small VPS)
- Batch processing that doesn't need real-time execution

❌ **Don't use rest for**:
- Critical or time-sensitive queues (`high`, `normal`)
- Low-volume queues (rest adds unnecessary delay)
- Production environments with dedicated queue workers

**Resource impact example** (100 jobs/hour):

| Rest | Jobs/Hour | CPU Usage | Total Processing Time |
|------|-----------|-----------|---------------------|
| `0` | 100 | 100% | 60 minutes |
| `1` | 60 | 70% | 160 minutes |
| `2` | 36 | 50% | 260 minutes |

**Use case**: Low-priority queue processes `VouchersGeneratedSummary` (200 jobs during busy hour). With `--rest=1`, CPU usage drops 30% while jobs still complete within 3-4 minutes (acceptable for informational notifications).

---

### `--force`

**What it does**: Run queue worker even when application is in maintenance mode (`php artisan down`).

**Default**: `false` (worker stops in maintenance mode)

**Format**:
```bash
--force  # No value needed; it's a boolean flag
```

**When to use**:

✅ **Use --force for**:
- Critical queue workers in production (disbursement processing during deployment)
- Emergency job processing during maintenance windows
- Background jobs that must continue during frontend maintenance

❌ **Don't use --force for**:
- Development environments (maintenance mode should stop everything)
- Non-critical queues that can wait
- When database migrations are running (jobs may fail on schema changes)

**Best practice**:

Only apply `--force` to high-priority queue in production:

```bash
# Production Supervisor config
[program:redeem-x-worker-high]
command=php artisan queue:work --queue=high --force --tries=3 --timeout=60
```

```bash
# Normal and low queues (stop during maintenance)
[program:redeem-x-worker-normal]
command=php artisan queue:work --queue=normal --tries=3 --timeout=90
# Note: No --force flag
```

---

## Deployment Guides

### Local Development (macOS/Linux)

**Option 1: Single Terminal (Combined Queues)**

```bash
# Simple approach for development
php artisan queue:work --queue=high,normal,low,default --tries=3 --timeout=90
```

**Option 2: composer dev (Recommended)**

Update `composer.json`:

```json
{
  "scripts": {
    "dev": [
      "Composer\\Config::disableProcessTimeout",
      "concurrently --kill-others --names=\"SERVE,QUEUE,PAIL,VITE\" -c \"bgBlue.bold,bgMagenta.bold,bgCyan.bold,bgGreen.bold\" \"php artisan serve\" \"php artisan queue:work --queue=high,normal,low,default --tries=3 --timeout=90\" \"php artisan pail --timeout=0\" \"npm run dev\""
    ]
  }
}
```

Then run:
```bash
composer dev
```

---

### Laravel Cloud

Create **4 separate queue workers** in the Laravel Cloud dashboard:

#### Worker 1: High Priority
```yaml
Name: redeem-x-high
Command: queue:work
Arguments: --queue=high --tries=3 --timeout=60 --sleep=3 --backoff=10,30,60 --rest=0
Processes: 1
Memory: 256MB
```

#### Worker 2: Normal Priority
```yaml
Name: redeem-x-normal
Command: queue:work
Arguments: --queue=normal --tries=3 --timeout=90 --sleep=5 --backoff=15,45,120 --rest=0
Processes: 2
Memory: 512MB
```

#### Worker 3: Low Priority
```yaml
Name: redeem-x-low
Command: queue:work
Arguments: --queue=low --tries=2 --timeout=30 --sleep=10 --backoff=30,90 --rest=1
Processes: 1
Memory: 256MB
```

#### Worker 4: Default
```yaml
Name: redeem-x-default
Command: queue:work
Arguments: --queue=default --tries=3 --timeout=120 --sleep=5 --backoff=30,60,120 --rest=0
Processes: 2
Memory: 512MB
```

**Total resources**: 6 processes, ~1.75GB memory

---

### Traditional VPS (with Supervisor)

Install Supervisor:
```bash
sudo apt-get install supervisor
```

Create config file: `/etc/supervisor/conf.d/redeem-x-queue.conf`

```ini
; High Priority Queue
[program:redeem-x-queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/redeem-x/artisan queue:work --queue=high --tries=3 --timeout=60 --sleep=3 --backoff=10,30,60 --rest=0 --force
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/redeem-x/storage/logs/queue-high.log
stopwaitsecs=3600

; Normal Priority Queue
[program:redeem-x-queue-normal]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/redeem-x/artisan queue:work --queue=normal --tries=3 --timeout=90 --sleep=5 --backoff=15,45,120 --rest=0
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/redeem-x/storage/logs/queue-normal.log
stopwaitsecs=3600

; Low Priority Queue
[program:redeem-x-queue-low]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/redeem-x/artisan queue:work --queue=low --tries=2 --timeout=30 --sleep=10 --backoff=30,90 --rest=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/redeem-x/storage/logs/queue-low.log
stopwaitsecs=3600

; Default Queue
[program:redeem-x-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/redeem-x/artisan queue:work --queue=default --tries=3 --timeout=120 --sleep=5 --backoff=30,60,120 --rest=0
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/redeem-x/storage/logs/queue-default.log
stopwaitsecs=3600

; Group all queue workers
[group:redeem-x-queues]
programs=redeem-x-queue-high,redeem-x-queue-normal,redeem-x-queue-low,redeem-x-queue-default
priority=999
```

Activate config:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start redeem-x-queues:*
```

Check status:
```bash
sudo supervisorctl status redeem-x-queues:*
```

---

### Docker / Kubernetes

**Dockerfile** (add to existing):

```dockerfile
# Queue worker service
FROM php:8.2-cli

WORKDIR /var/www/html

COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Start queue worker
CMD ["php", "artisan", "queue:work", "--queue=high,normal,low,default", "--tries=3", "--timeout=90", "--sleep=5"]
```

**docker-compose.yml**:

```yaml
services:
  # ... existing services (web, db, etc.)

  queue-high:
    build: .
    command: php artisan queue:work --queue=high --tries=3 --timeout=60 --sleep=3 --backoff=10,30,60 --rest=0
    depends_on:
      - db
    env_file: .env
    restart: unless-stopped

  queue-normal:
    build: .
    command: php artisan queue:work --queue=normal --tries=3 --timeout=90 --sleep=5 --backoff=15,45,120 --rest=0
    depends_on:
      - db
    env_file: .env
    restart: unless-stopped
    deploy:
      replicas: 2

  queue-low:
    build: .
    command: php artisan queue:work --queue=low --tries=2 --timeout=30 --sleep=10 --backoff=30,90 --rest=1
    depends_on:
      - db
    env_file: .env
    restart: unless-stopped

  queue-default:
    build: .
    command: php artisan queue:work --queue=default --tries=3 --timeout=120 --sleep=5 --backoff=30,60,120 --rest=0
    depends_on:
      - db
    env_file: .env
    restart: unless-stopped
    deploy:
      replicas: 2
```

**Kubernetes** (CronJob for queue worker):

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redeem-x-queue-high
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redeem-x-queue-high
  template:
    metadata:
      labels:
        app: redeem-x-queue-high
    spec:
      containers:
      - name: queue-worker
        image: redeem-x:latest
        command:
          - php
          - artisan
          - queue:work
          - --queue=high
          - --tries=3
          - --timeout=60
          - --sleep=3
          - --backoff=10,30,60
          - --rest=0
          - --force
        resources:
          requests:
            memory: "256Mi"
            cpu: "100m"
          limits:
            memory: "512Mi"
            cpu: "500m"
```

---

## Monitoring & Troubleshooting

### Check Queue Status

**View pending jobs by queue**:
```bash
php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
\$jobs = DB::table('jobs')
    ->select('queue', DB::raw('COUNT(*) as count'))
    ->groupBy('queue')
    ->get();
foreach(\$jobs as \$j) {
    echo \"Queue: {\$j->queue} | Jobs: {\$j->count}\\n\";
}
"
```

**Laravel Artisan command** (Laravel 10+):
```bash
php artisan queue:monitor high,normal,low,default --max=100
```

This sends alerts if queue depth exceeds 100 jobs.

---

### Monitor Failed Jobs

**List failed jobs**:
```bash
php artisan queue:failed
```

**Retry specific failed job**:
```bash
php artisan queue:retry <job-id>
```

**Retry all failed jobs**:
```bash
php artisan queue:retry all
```

**Flush failed jobs** (delete all):
```bash
php artisan queue:flush
```

---

### Real-Time Queue Monitoring

**Watch queue counts live** (refreshes every second):
```bash
watch -n 1 'php -r "require \"vendor/autoload.php\"; \$app = require_once \"bootstrap/app.php\"; \$app->make(\"Illuminate\\Contracts\\Console\\Kernel\")->bootstrap(); echo \"=== Queue Status ===\\n\"; \$jobs = DB::table(\"jobs\")->select(\"queue\", DB::raw(\"COUNT(*) as count\"))->groupBy(\"queue\")->get(); foreach(\$jobs as \$j) echo \"Queue: {\$j->queue} | Jobs: {\$j->count}\\n\"; echo \"\\nFailed jobs: \" . DB::table(\"failed_jobs\")->count() . \"\\n\";"'
```

**Monitor logs** (filter for notifications):
```bash
tail -f storage/logs/laravel.log | grep "Notification\|SendFeedbacks\|VouchersGenerated\|DisbursementFailed"
```

---

### Common Issues

#### Issue: Jobs Not Processing

**Symptoms**: Jobs stuck in `jobs` table, notifications not sent.

**Diagnosis**:
```bash
# Check if worker is running
ps aux | grep "queue:work"

# Check which queues worker is processing
ps aux | grep "queue:work" | grep -v grep
```

**Solution**:
```bash
# Restart worker with correct queues
pkill -TERM -f "queue:work"
sleep 5
php artisan queue:work --queue=high,normal,low,default --tries=3 --timeout=90
```

---

#### Issue: High CPU Usage

**Symptoms**: Queue worker consuming 100% CPU.

**Diagnosis**:
```bash
# Check sleep settings
ps aux | grep "queue:work" | grep -v grep
# Look for --sleep value
```

**Solution**:
```bash
# Increase sleep interval for low-priority queues
php artisan queue:work --queue=low --sleep=10 --rest=1  # Add rest pause
```

---

#### Issue: Jobs Timing Out

**Symptoms**: Jobs repeatedly failing with timeout errors.

**Diagnosis**:
```bash
# Check failed jobs
php artisan queue:failed | grep "Timeout"

# Check average job duration in logs
grep "Processing:" storage/logs/laravel.log | tail -20
```

**Solution**:
```bash
# Increase timeout for specific queue
php artisan queue:work --queue=normal --timeout=120  # Increase from 90 to 120
```

---

#### Issue: Memory Leaks

**Symptoms**: Worker memory usage grows over time until crash.

**Diagnosis**:
```bash
# Monitor memory usage
watch -n 5 'ps aux | grep "queue:work" | grep -v grep | awk "{print \$6/1024 \" MB\"}"'
```

**Solution**:
```bash
# Restart worker after N jobs or N seconds
php artisan queue:work --max-jobs=1000  # Restart after 1000 jobs
php artisan queue:work --max-time=3600  # Restart after 1 hour
```

Add to Supervisor config:
```ini
[program:redeem-x-queue-normal]
command=php artisan queue:work --queue=normal --max-time=3600
```

---

## Performance Tuning

### Scaling Guidelines

**When to scale up** (add more processes):

| Metric | Threshold | Action |
|--------|-----------|--------|
| Queue depth | >50 jobs for >5 minutes | Add 1 process |
| Job latency | >2 minutes average | Add 1-2 processes |
| Failed jobs | >5% failure rate | Investigate root cause first, then scale |

**When to scale down** (reduce processes):

| Metric | Threshold | Action |
|--------|-----------|--------|
| CPU usage | <20% average | Reduce processes by 1 |
| Queue depth | Consistently 0 | Reduce processes or increase sleep |

---

### Process Count Recommendations

**By Application Scale**:

| Scale | Users/Day | Vouchers/Day | high | normal | low | default | Total |
|-------|-----------|--------------|------|--------|-----|---------|-------|
| **Small** | <100 | <500 | 1 | 1 | 1 | 1 | 4 |
| **Medium** | 100-1000 | 500-5000 | 1 | 2 | 1 | 2 | 6 |
| **Large** | 1000-10000 | 5000-50000 | 2 | 4 | 2 | 4 | 12 |
| **Enterprise** | >10000 | >50000 | 4 | 8 | 4 | 8 | 24 |

---

### Resource Requirements

**Per Process** (approximate):

| Queue | Memory | CPU | Disk I/O |
|-------|--------|-----|----------|
| **high** | 256MB | 5-10% | Low |
| **normal** | 512MB | 10-20% | Medium (attachments) |
| **low** | 256MB | 5-10% | Low |
| **default** | 512MB | 10-30% | Varies |

**Total for medium deployment** (6 processes):
- Memory: ~2.5GB
- CPU: 4 cores recommended
- Disk: 20GB+ (includes logs, storage)

---

### Optimization Tips

**1. Use Redis for queue driver** (faster than database):

```env
QUEUE_CONNECTION=redis
```

```bash
# Install Redis
sudo apt-get install redis-server

# Configure Laravel
# config/queue.php already has redis config
```

**Performance improvement**: 3-5x faster job processing.

---

**2. Enable opcode caching** (PHP 8+):

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

**Performance improvement**: 30-50% faster job execution.

---

**3. Optimize notification queries**:

```php
// BAD: N+1 query problem
foreach ($vouchers as $voucher) {
    $voucher->owner->notify(new VouchersGeneratedSummary($voucher));
}

// GOOD: Eager load relationships
$vouchers = Voucher::with('owner')->get();
foreach ($vouchers as $voucher) {
    $voucher->owner->notify(new VouchersGeneratedSummary($voucher));
}
```

---

**4. Use Laravel Horizon** (for Redis queue):

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Benefits:
- Web dashboard for queue monitoring
- Auto-scaling workers based on load
- Job metrics and analytics
- Retry failed jobs from UI

---

## Quick Reference Tables

### Parameter Comparison

| Parameter | High | Normal | Low | Default | Purpose |
|-----------|------|--------|-----|---------|---------|
| `--queue` | high | normal | low | default | Queue name |
| `--tries` | 3 | 3 | 2 | 3 | Max attempts |
| `--timeout` | 60 | 90 | 30 | 120 | Job timeout (seconds) |
| `--sleep` | 3 | 5 | 10 | 5 | Empty queue sleep (seconds) |
| `--backoff` | 10,30,60 | 15,45,120 | 30,90 | 30,60,120 | Retry delays (seconds) |
| `--rest` | 0 | 0 | 1 | 0 | Pause between jobs (seconds) |
| `--force` | ✅ | ❌ | ❌ | ❌ | Run in maintenance mode |

---

### Notification-to-Queue Mapping

| Notification | Queue | Avg. Time | Channels | Attachments |
|--------------|-------|-----------|----------|-------------|
| DisbursementFailedNotification | high | 2-5s | Email | None |
| LowBalanceAlert | high | 2-5s | Email | None |
| PaymentConfirmationNotification | normal | 1-3s | SMS | None |
| SendFeedbacksNotification | normal | 5-15s | Email, SMS, Webhook | Signature, Selfie, Location |
| VouchersGeneratedSummary | low | 1-3s | SMS | None |
| BalanceNotification | low | 1-3s | SMS | None |
| HelpNotification | low | 1-3s | SMS | None |

---

### Deployment Checklist

#### Development
- [ ] Run `composer dev` or `queue:work --queue=high,normal,low,default`
- [ ] Verify queue worker appears in `ps aux | grep queue:work`
- [ ] Test notification delivery with `php artisan test:notification`
- [ ] Monitor logs: `tail -f storage/logs/laravel.log`

#### Staging
- [ ] Configure separate workers per queue (Supervisor or Laravel Cloud)
- [ ] Set appropriate timeouts based on notification types
- [ ] Enable `--force` for high-priority queue only
- [ ] Test failover (kill worker, verify auto-restart)
- [ ] Load test with realistic job volumes

#### Production
- [ ] Deploy with Supervisor or Kubernetes
- [ ] Configure monitoring (Laravel Horizon or custom)
- [ ] Set up alerts for queue depth >100 jobs
- [ ] Enable opcache and Redis queue driver
- [ ] Document rollback procedure
- [ ] Train ops team on queue restart commands

---

## Related Documentation

- **Notification System Architecture**: `docs/guides/features/NOTIFICATION_SYSTEM.md`
- **Notification Triggers & Recipients**: `docs/guides/features/NOTIFICATION_TRIGGERS_AND_RECIPIENTS.md`
- **Queue Configuration Troubleshooting**: `docs/troubleshooting/NOTIFICATION_QUEUE_CONFIGURATION.md`
- **Notification Configuration**: `config/notifications.php`

---

## Support

**Queue not processing?** See troubleshooting section above or check:
- `docs/troubleshooting/NOTIFICATION_QUEUE_CONFIGURATION.md`

**Need to add new queue priority?** See:
- `config/notifications.php` - Add to `queue.queues` array
- `app/Notifications/BaseNotification.php` - Update `getQueueName()` logic

**Production deployment help?** See:
- Deployment guides section above
- Laravel documentation: https://laravel.com/docs/queues

---

**End of Document**
