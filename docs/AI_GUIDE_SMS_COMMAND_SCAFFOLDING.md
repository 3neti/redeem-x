# AI Guide: Scaffolding New SMS Commands

This guide provides step-by-step instructions for AI agents to scaffold new SMS commands in the redeem-x system, following the proven pattern established by the BALANCE command implementation.

## Architecture Overview

The SMS command system uses a two-tier architecture:

1. **Pipedream Workflow** (`docs/pipedream-generate-voucher.js`)
   - Receives SMS from EngageSpark
   - Routes to appropriate handler
   - Forwards internal commands to Laravel

2. **Laravel SMS Handler** (`packages/omnichannel/src/Handlers/`)
   - Processes command logic
   - Sends multi-channel notifications (SMS, email, webhook, database)
   - Returns JSON response

## Command Types

### Type A: API-Based Commands (Handled by Pipedream)
Examples: AUTHENTICATE, GENERATE, REDEEM
- Store/retrieve data from Pipedream Data Store
- Call redeem-x REST API endpoints
- Return response directly

### Type B: Internal Commands (Forwarded to Laravel)
Examples: BALANCE, REGISTER
- Forward to Laravel `/sms` endpoint
- Processed by omnichannel package handlers
- Use Laravel Notifications for delivery

## Implementation Checklist

### Phase 1: Planning (Before Implementation)

- [ ] **Define Command Scope**
  - Command name and aliases
  - Required parameters
  - Optional flags
  - Response format
  - Permission requirements

- [ ] **Choose Command Type**
  - Type A: Needs API/external data → Handle in Pipedream
  - Type B: Needs Laravel models/logic → Forward to Laravel

- [ ] **Create Implementation Plan**
  - Use `create_plan` tool
  - Document: problem, current state, proposed changes
  - Wait for user approval before coding

### Phase 2: Backend Implementation (Laravel)

#### Step 1: Create SMS Handler

Location: `packages/omnichannel/src/Handlers/SMS{CommandName}.php`

```php
<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Models\User;
use App\Notifications\{CommandName}Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;

class SMS{CommandName} implements SMSHandlerInterface
{
    /**
     * Handle {COMMAND} SMS command.
     */
    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        Log::info('[SMS{CommandName}] Processing {COMMAND} command', [
            'from' => $from,
            'to' => $to,
            'values' => $values,
        ]);

        // 1. Find user by mobile
        $user = $this->findUserByMobile($from);
        
        if (!$user) {
            return response()->json([
                'message' => 'No account found. Send REGISTER to create one.',
            ]);
        }

        // 2. Check permissions (if needed)
        if (!$user->can('required-permission')) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ]);
        }

        // 3. Process command logic
        try {
            $result = $this->processCommand($user, $values);
            
            // 4. Send notification
            $user->notify(new {CommandName}Notification(
                data: $result
            ));
            
            return response()->json(['message' => $result['message']]);
        } catch (\Throwable $e) {
            Log::error('[SMS{CommandName}] Failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to process command. Please try again.',
            ]);
        }
    }

    protected function findUserByMobile(string $mobile): ?User
    {
        return User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where(function ($sub) use ($mobile) {
                    $sub->where('value', $mobile)
                        ->orWhere('value', 'LIKE', "%{$mobile}%")
                        ->orWhere('value', 'LIKE', '%' . ltrim($mobile, '0') . '%');
                });
        })->first();
    }
    
    protected function processCommand(User $user, array $values): array
    {
        // Implement command-specific logic
        return [
            'message' => 'Command result',
            // Additional data for notification
        ];
    }
}
```

#### Step 2: Register Route

Location: `packages/omnichannel/routes/sms.php`

```php
use LBHurtado\OmniChannel\Handlers\SMS{CommandName};

// Register BEFORE the catch-all REDEEM handler
$router->register('{COMMAND} {param?}', SMS{CommandName}::class);
```

**CRITICAL: Handler Order**
```php
// ✅ CORRECT ORDER (specific to broad)
$router->register('AUTHENTICATE {token}', SMSAuthenticate::class);
$router->register('GENERATE {amount}', SMSGenerate::class);
$router->register('BALANCE {flag?}', SMSBalance::class);  // ← Specific
$router->register('{message}', CatchAllHandler::class);    // ← Broad (last!)

// ❌ WRONG ORDER (will cause false matches)
$router->register('{message}', CatchAllHandler::class);    // Too early!
$router->register('BALANCE {flag?}', SMSBalance::class);   // Never reached
```

#### Step 3: Create Notification Class

Location: `app/Notifications/{CommandName}Notification.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LBHurtado\EngageSpark\EngageSparkMessage;

class {CommandName}Notification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected array $data
    ) {}

    public function via(object $notifiable): array
    {
        // For AnonymousNotifiable, use configured channels only
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return config('voucher-notifications.{command}.channels', ['engage_spark']);
        }
        
        // For User models, include database for audit trail
        $channels = config('voucher-notifications.{command}.channels', ['engage_spark']);
        return array_unique(array_merge($channels, ['database']));
    }

    public function toEngageSpark(object $notifiable): EngageSparkMessage
    {
        return (new EngageSparkMessage())->content($this->data['message']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subject')
            ->line($this->data['message']);
    }

    public function toWebhook(object $notifiable): array
    {
        return [
            'type' => '{command}',
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => '{command}',
            'data' => $this->data,
        ];
    }
}
```

#### Step 4: Add Configuration

Location: `config/voucher-notifications.php`

```php
'{command}' => [
    'channels' => explode(',', env('{COMMAND}_NOTIFICATION_CHANNELS', 'engage_spark')),
],
```

#### Step 5: Create Permissions (if needed)

Location: `database/seeders/RolePermissionSeeder.php`

```php
// Create permission
Permission::firstOrCreate(['name' => 'required-permission']);

// Assign to roles
$superAdmin->syncPermissions([
    // ... existing permissions
    'required-permission',
]);
```

Run: `php artisan db:seed --class=RolePermissionSeeder`

#### Step 6: Create Test Command

Location: `app/Console/Commands/Test{CommandName}.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class Test{CommandName} extends Command
{
    protected $signature = 'test:{command}
                            {mobile? : Mobile number to test with}
                            {--param= : Optional parameter}';

    protected $description = 'Test {COMMAND} SMS command';

    public function handle(): int
    {
        $mobile = $this->argument('mobile') ?? $this->getDefaultMobile();
        $param = $this->option('param');

        $smsText = '{COMMAND}';
        if ($param) {
            $smsText .= " {$param}";
        }

        $this->info("Testing: {$smsText}");
        $this->info("From: {$mobile}");
        $this->newLine();

        $response = Http::post(config('app.url') . '/sms', [
            'from' => $mobile,
            'to' => '2929',
            'message' => $smsText,
        ]);

        if ($response->successful()) {
            $this->info('✓ SMS processed successfully');
            $this->newLine();
            $this->line($response->json()['message'] ?? 'No message');
            return self::SUCCESS;
        }

        $this->error('✗ SMS processing failed');
        return self::FAILURE;
    }

    protected function getDefaultMobile(): string
    {
        $user = User::first();
        return $user?->mobile ?? '09173011987';
    }
}
```

### Phase 3: Pipedream Integration

#### Step 1: Add Command Pattern

Location: `docs/pipedream-generate-voucher.js`

```javascript
const COMMAND_PATTERNS = {
  // ... existing patterns
  {COMMAND}: /^{command}(\s+(.+))?$/i,
};
```

#### Step 2: Add Messages

```javascript
const MESSAGES = {
  // ... existing messages
  {COMMAND}: {
    GENERIC_ERROR: "⚠️ Failed to process command. Please try again.",
  },
};
```

#### Step 3: Add Handler Function

```javascript
/**
 * Handles {COMMAND} command - forwards to internal Laravel /sms endpoint
 */
async function handle{CommandName}(sender, smsText, store, $) {
  const match = smsText.match(COMMAND_PATTERNS.{COMMAND});
  
  if (!match) {
    return null;
  }
  
  console.log("[{COMMAND}] Command detected", { sender, smsText });
  
  try {
    const response = await axios.post(
      CONFIG.REDEEMX_SMS_URL,
      {
        from: sender,
        to: "2929",
        message: smsText,
      },
      { headers: { "Content-Type": "application/json" } }
    );
    
    return {
      status: "success",
      message: response.data.message || "Command processed",
      forwarded: true,
    };
  } catch (error) {
    console.error("[{COMMAND}] Failed", error);
    return {
      status: "error",
      message: MESSAGES.{COMMAND}.GENERIC_ERROR,
      error: error.message,
    };
  }
}
```

#### Step 4: Register in Workflow

**CRITICAL: Add BEFORE REDEEM handler**

```javascript
async run({ steps, $ }) {
  // ... existing handlers
  
  // Try {COMMAND} handler (before REDEEM!)
  result = await handle{CommandName}(sender, smsText, this.redeemxStore, $);
  if (result) {
    $.export("status", result.status);
    $.export("message", result.message);
    if (result.forwarded) $.export("forwarded", result.forwarded);
    if (result.error) $.export("error", result.error);
    return result;
  }
  
  // Try REDEEM handler (must be last!)
  // ...
}
```

#### Step 5: Update Documentation

```javascript
/**
 * Commands:
 * ...
 * X. {COMMAND} {params} - Description
 *    Example: "{COMMAND} example"
 *    Response: "Result message"
 *    Requires: 'permission-name' (if applicable)
 */
```

### Phase 4: Testing

```bash
# 1. Test locally
php artisan test:{command}
php artisan test:{command} --param=value

# 2. Check logs
tail -f storage/logs/laravel.log | grep "{COMMAND}"

# 3. Verify notification sent
php artisan tinker --execute="
echo DB::table('notifications')
  ->where('type', 'App\\\\Notifications\\\\{CommandName}Notification')
  ->count() . ' notifications sent';
"

# 4. Test via Pipedream (after deployment)
# Send SMS: "{COMMAND} params"
```

### Phase 5: Deployment

```bash
# 1. Commit changes
git add -A
git commit -m "Add {COMMAND} SMS command with multi-channel notifications

- Create SMS{CommandName} handler in omnichannel package
- Create {CommandName}Notification with SMS/email/webhook support
- Register route in packages/omnichannel/routes/sms.php
- Add Pipedream handler with proper command order
- Add test command: php artisan test:{command}
- Add permissions (if applicable)

Co-Authored-By: Warp <agent@warp.dev>"

# 2. Push to main
git push origin main

# 3. Run seeder on production (if permissions added)
ssh production
php artisan db:seed --class=RolePermissionSeeder

# 4. Update Pipedream workflow
# Copy docs/pipedream-generate-voucher.js to Pipedream
```

## Common Pitfalls

### ❌ Handler Order Issues
**Problem:** REDEEM catches command before specific handler
**Solution:** Always add new handlers BEFORE REDEEM in both:
- `packages/omnichannel/routes/sms.php`
- `docs/pipedream-generate-voucher.js` workflow

### ❌ Permission Mismatches
**Problem:** Handler checks `'permission-name'` but seeder creates `'permission name'`
**Solution:** Use exact same string (including hyphens/spaces) in both:
- Handler: `$user->can('permission-name')`
- Seeder: `Permission::firstOrCreate(['name' => 'permission-name'])`

### ❌ Channel Schema Confusion
**Problem:** Using `type`/`address` instead of `name`/`value` for channels
**Solution:** User channels use:
- `name = 'mobile'`
- `value = '09173011987'`

### ❌ Anonymous vs User Notifications
**Problem:** Using `Notification::route()` instead of `$user->notify()`
**Solution:** Always use `$user->notify()` to:
- Get mobile via `routeNotificationForEngageSpark()`
- Store in database for audit trail
- Support multiple channels

## Example: Complete BALANCE Implementation

See the following files for reference:
- Handler: `packages/omnichannel/src/Handlers/SMSBalance.php`
- Notification: `app/Notifications/BalanceNotification.php`
- Route: `packages/omnichannel/routes/sms.php` (line 14)
- Pipedream: `docs/pipedream-generate-voucher.js` (handleBalance function)
- Test: `app/Console/Commands/TestSmsBalance.php`
- Config: `config/voucher-notifications.php` (balance section)
- Permissions: `database/seeders/RolePermissionSeeder.php` (view-balances)

## Summary: Success Checklist

- [ ] Created SMS handler in omnichannel package
- [ ] Created notification class with multi-channel support
- [ ] Registered route BEFORE catch-all handlers
- [ ] Added Pipedream handler function
- [ ] Registered in workflow BEFORE REDEEM
- [ ] Added configuration for notification channels
- [ ] Created/updated permissions (if needed)
- [ ] Created test command
- [ ] Tested locally with queue worker running
- [ ] Verified SMS notification received
- [ ] Verified database audit trail
- [ ] Updated documentation in Pipedream file
- [ ] Committed with descriptive message
- [ ] Deployed to production
- [ ] Ran seeders (if applicable)
- [ ] Updated Pipedream workflow
- [ ] Tested end-to-end via SMS

## Quick Reference: File Locations

```
packages/omnichannel/
├── routes/sms.php                          # Register route
└── src/Handlers/SMS{CommandName}.php       # Handler logic

app/
├── Notifications/{CommandName}Notification.php  # Multi-channel notification
└── Console/Commands/Test{CommandName}.php       # Test command

config/
└── voucher-notifications.php               # Channel configuration

database/seeders/
└── RolePermissionSeeder.php               # Permissions (if needed)

docs/
└── pipedream-generate-voucher.js          # Pipedream integration
```

## Need Help?

1. Review the BALANCE command implementation (grep for `SMSBalance`)
2. Check this guide's checklist
3. Verify handler order in both Laravel and Pipedream
4. Test locally before deploying to Pipedream
5. Check logs: `tail -f storage/logs/laravel.log`
