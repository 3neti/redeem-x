# Laravel Actions Consolidation Guide

This guide documents the methodology for consolidating separate Action, Listener, and Command classes into a single Laravel Action using the `lorisleiva/laravel-actions` package.

## When to Consolidate

Consider consolidation when you have:
- An Action class with core business logic
- A Listener that calls the action in response to events
- An Artisan Command that calls the action from CLI
- A Job that wraps the action for async execution

These are signs of the same logic scattered across multiple files.

## Before: Scattered Pattern

```
app/
├── Actions/
│   └── SyncFormFlowDataToEnvelope.php    # Core logic
├── Listeners/
│   └── SyncFormFlowToEnvelope.php        # Event handler → calls action
├── Console/Commands/
│   └── SyncVoucherToEnvelopeCommand.php  # CLI → calls action
```

**Problems:**
- Logic duplication (error handling, logging repeated in each file)
- Multiple files to maintain for one feature
- Inconsistent patterns across similar features

## After: Unified Laravel Action

```
app/
└── Actions/
    └── Envelope/
        └── SyncFormFlowData.php  # All-in-one: action + job + listener + command
```

## Implementation Steps

### 1. Create the Laravel Action Class

```php
<?php

declare(strict_types=1);

namespace App\Actions\Envelope;

use App\Events\FormFlowCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncFormFlowData
{
    use AsAction;

    // Command configuration
    public string $commandSignature = 'voucher:sync-to-envelope
                            {code : The voucher code}
                            {--dry-run : Show what would be synced without making changes}
                            {--force : Overwrite existing envelope payload}';

    public string $commandDescription = 'Sync voucher persisted inputs to its settlement envelope';

    // Inject dependencies via constructor
    public function __construct(
        protected SyncFormFlowToEnvelope $syncAction,
        protected FormFlowDataMapper $mapper
    ) {}

    /**
     * Core business logic - always implement this method.
     */
    public function handle(Voucher $voucher, array $collectedData): FormFlowSyncResultData
    {
        // Your core logic here
    }

    /**
     * Job execution - called when dispatched asynchronously.
     * Wraps handle() with logging and error handling.
     */
    public function asJob(Voucher $voucher, array $collectedData): void
    {
        Log::info('[SyncFormFlowData] Job started', [
            'voucher' => $voucher->code,
        ]);

        try {
            $result = $this->handle($voucher, $collectedData);

            if ($result->hasErrors()) {
                Log::warning('[SyncFormFlowData] Completed with errors', [
                    'voucher' => $voucher->code,
                    'errors' => $result->attachmentErrors,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[SyncFormFlowData] Failed', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger queue retry
        }
    }

    /**
     * Event listener - dispatches job for async processing.
     */
    public function asListener(FormFlowCompleted $event): void
    {
        Log::info('[SyncFormFlowData] Event received, dispatching job', [
            'voucher' => $event->voucher->code,
            'flow_id' => $event->flowId,
        ]);

        // Dispatch as queued job
        static::dispatch($event->voucher, $event->collectedData);
    }

    /**
     * Artisan command - parses CLI input and calls handle().
     */
    public function asCommand(Command $command): int
    {
        $code = strtoupper($command->argument('code'));
        $dryRun = $command->option('dry-run');
        $force = $command->option('force');

        // Fetch model, validate, show info...
        
        if ($dryRun) {
            $command->warn('Dry run - no changes made.');
            return Command::SUCCESS;
        }

        $result = $this->handle($voucher, $collectedData);

        if (!$result->success) {
            $command->error($result->error);
            return Command::FAILURE;
        }

        $command->info('Sync completed!');
        return Command::SUCCESS;
    }
}
```

### 2. Register Command Auto-Discovery

In `AppServiceProvider::boot()`:

```php
use Lorisleiva\Actions\Facades\Actions;

public function boot(): void
{
    // Auto-register action commands (Laravel 11+ has no Kernel.php)
    if ($this->app->runningInConsole()) {
        Actions::registerCommands();
    }
}
```

### 3. Update Event Listener Registration

Change the listener class in your event registration:

```php
// Before
Event::listen(FormFlowCompleted::class, SyncFormFlowToEnvelope::class);

// After
Event::listen(FormFlowCompleted::class, SyncFormFlowData::class);
```

### 4. Delete Obsolete Files

Remove the old scattered files:
- `app/Actions/SyncFormFlowDataToEnvelope.php`
- `app/Listeners/SyncFormFlowToEnvelope.php`
- `app/Console/Commands/SyncVoucherToEnvelopeCommand.php`

### 5. Update Imports

Search for and update any imports referencing the old classes.

## Method Signatures

| Method | Purpose | Return Type | When Called |
|--------|---------|-------------|-------------|
| `handle()` | Core business logic | Your result type | Direct invocation, from asJob, from asCommand |
| `asJob()` | Async execution wrapper | `void` | When dispatched via `static::dispatch()` |
| `asListener()` | Event handler | `void` | When event fires |
| `asCommand()` | CLI handler | `int` (exit code) | When artisan command runs |

## Dispatch Flow

```
Event Fires
    ↓
asListener(Event $event)
    ↓
static::dispatch($voucher, $data)  ← Queues the job
    ↓
[Queue Worker picks up]
    ↓
asJob($voucher, $data)
    ↓
$this->handle($voucher, $data)  ← Core logic executes
```

## Key Patterns

### 1. Listener Dispatches Job (Not Calls Handle Directly)

```php
// ✅ Correct - async via queue
public function asListener(FormFlowCompleted $event): void
{
    static::dispatch($event->voucher, $event->collectedData);
}

// ❌ Wrong - blocks event dispatch
public function asListener(FormFlowCompleted $event): void
{
    $this->handle($event->voucher, $event->collectedData);
}
```

### 2. Job Wraps Handle with Error Handling

```php
public function asJob(Voucher $voucher, array $collectedData): void
{
    try {
        $this->handle($voucher, $collectedData);
    } catch (\Throwable $e) {
        Log::error('Failed', ['error' => $e->getMessage()]);
        throw $e; // Re-throw for queue retry
    }
}
```

### 3. Command Returns Exit Codes

```php
public function asCommand(Command $command): int
{
    // ... validation ...
    
    if ($error) {
        $command->error($message);
        return Command::FAILURE;  // Exit code 1
    }
    
    $command->info('Success!');
    return Command::SUCCESS;  // Exit code 0
}
```

## Directory Convention

Place consolidated actions in subdirectories by domain:

```
app/Actions/
├── Envelope/
│   └── SyncFormFlowData.php
├── Voucher/
│   ├── ProcessRedemption.php
│   └── ValidateVoucherCode.php
├── Contact/
│   ├── InitiateContactKYC.php
│   └── ValidateContactKYC.php
└── Api/
    └── ... (API-specific actions)
```

## Testing

After consolidation, test all execution paths:

```bash
# Test command
php artisan voucher:sync-to-envelope R66M --dry-run
php artisan voucher:sync-to-envelope R66M

# Test event dispatch (trigger the event)
php artisan tinker
>>> event(new \App\Events\FormFlowCompleted($voucher, $data, 'flow-id', now()));

# Test direct invocation
>>> \App\Actions\Envelope\SyncFormFlowData::run($voucher, $data);
```

## References

- [Laravel Actions Documentation](https://www.laravelactions.com/2.x/)
- [Execute as Commands](https://www.laravelactions.com/2.x/execute-as-commands.html)
- [Listen for Events](https://www.laravelactions.com/2.x/listen-for-events.html)
- [Dispatch Jobs](https://www.laravelactions.com/2.x/dispatch-jobs.html)
