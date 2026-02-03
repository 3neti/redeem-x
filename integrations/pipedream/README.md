# Pipedream SMS Integration

Pipedream acts as an SMS gateway proxy between Omni Channel (SMS provider) and the redeem-x Laravel application.

## Architecture Overview

```
SMS User → Omni Channel (22560537) → Pipedream Workflow → Laravel /sms endpoints → Response
                                           ↓
                                    Data Store (tokens)
```

### Flow

1. **SMS Reception:** User sends SMS to shortcode 22560537
2. **Pipedream Trigger:** Omni Channel forwards SMS to Pipedream HTTP webhook
3. **Authentication:**
   - `AUTHENTICATE <code>` → Pipedream validates and stores token in Data Store
   - Other commands → Pipedream checks token, routes to Laravel
4. **Laravel Processing:** Application processes command (GENERATE, REDEEM, BALANCE, etc.)
5. **Response:** Laravel returns response → Pipedream → Omni Channel → SMS User

## Workflows

### generate-voucher.js (v2.1.0)
**Type:** Full SMS command handling workflow

**Features:**
- Handles all SMS commands: AUTHENTICATE, GENERATE, REDEEM, BALANCE
- Token management in Pipedream Data Store
- Direct voucher generation (bypasses Laravel for GENERATE command)
- Comprehensive error handling and logging
- 570 lines of code

**When to use:** Complete SMS gateway with built-in voucher logic

**Status:** Production-ready but being phased out in favor of v3.0

### token-based-routing.js (v3.0.0) ⭐
**Type:** Simplified authentication proxy

**Features:**
- **Pipedream:** Only handles AUTHENTICATE command (stores tokens)
- **Laravel:** All business logic (GENERATE, REDEEM, BALANCE)
- Routes authenticated requests to `/sms` (protected)
- Routes unauthenticated requests to `/sms/public` (limited)
- ~200 lines (significantly simpler than v2.1)

**When to use:** Recommended for new deployments (cleaner separation of concerns)

**Status:** Current production version

## Deployment

### Prerequisites
1. Pipedream account (free tier sufficient)
2. Omni Channel account with shortcode access
3. Laravel application with `/sms` and `/sms/public` endpoints configured

### Step 1: Create Pipedream Workflow

1. Log in to Pipedream: https://pipedream.com
2. Create new workflow: **New** → **HTTP / Webhook Requests**
3. Copy workflow code from `token-based-routing.js` (v3.0) or `generate-voucher.js` (v2.1)
4. Paste into Pipedream code editor

### Step 2: Configure Data Store

1. In Pipedream workflow editor, add **Data Stores** step
2. Create or select existing data store: **redeem-x**
3. Update workflow code to reference the data store key

### Step 3: Set Environment Variables

Configure these variables in Pipedream workflow settings:

```javascript
// v3.0 (token-based-routing.js)
LARAVEL_API_URL = "https://redeem-x.test/api/v1"
PIPEDREAM_DATA_STORE_KEY = "redeem-x"

// v2.1 (generate-voucher.js) - additional variables
VOUCHER_API_ENDPOINT = "https://redeem-x.test/api/v1/vouchers"
SMS_SHORTCODE = "22560537"
```

See workflow file comments for complete list.

### Step 4: Connect Omni Channel

1. Copy Pipedream webhook URL (shown at top of workflow)
2. In Omni Channel dashboard:
   - Go to **Settings** → **Webhooks**
   - Add new webhook pointing to Pipedream URL
   - Select event: **Incoming SMS**
3. Test by sending SMS to shortcode

### Step 5: Test End-to-End

```bash
# Send SMS to shortcode 22560537
# From mobile: AUTHENTICATE <auth-code>
# Expected: "Authentication successful. You can now use other commands."

# Test authenticated command
# From same mobile: BALANCE
# Expected: Current balance amount
```

## Configuration

### Data Store Schema

```json
{
  "user_tokens": {
    "09171234567": {
      "token": "abc123xyz",
      "authenticated_at": "2026-02-03T12:00:00Z",
      "expires_at": "2026-02-10T12:00:00Z"
    }
  }
}
```

**Token Expiry:** 7 days (configurable in workflow code)

### Laravel Endpoints

**v3.0 requires these routes:**

```php
// routes/api.php
Route::post('/sms', [SmsController::class, 'handle'])
    ->middleware('auth:sanctum'); // Authenticated requests

Route::post('/sms/public', [SmsController::class, 'handlePublic']);
    // Public commands (AUTHENTICATE only)
```

## Version History

See `CHANGELOG.md` for detailed version history.

**Summary:**
- **v3.0.0** (Jan 31, 2026) - Simplified proxy architecture
- **v2.1.0** (Jan 28, 2026) - Full SMS command handling
- **v2.0.0** - Token-based authentication added
- **v1.0.0** - Initial Pipedream integration

## Troubleshooting

### Common Issues

**Issue:** SMS not reaching Pipedream
- **Solution:** Verify Omni Channel webhook URL matches Pipedream endpoint
- Check Omni Channel webhook logs for delivery failures

**Issue:** Authentication fails
- **Solution:** Check Data Store key matches in both workflow and config
- Verify token hasn't expired (7-day TTL)

**Issue:** Laravel endpoint returns 401 Unauthorized
- **Solution:** Ensure Pipedream passes token in Authorization header
- Check Laravel Sanctum configuration

**Issue:** Commands timeout
- **Solution:** Increase Pipedream workflow timeout (default: 30s)
- Optimize Laravel command processing time

### Debugging

Enable debug logging in Pipedream:

```javascript
// Add to workflow code
console.log("Received SMS:", steps.trigger.event);
console.log("Token lookup:", token);
console.log("Laravel response:", response.data);
```

View logs: **Workflow** → **Event History** → Select event → **Logs**

## Related Documentation

- **SMS Commands:** `docs/guides/automation/CONSOLE_COMMANDS.md`
- **API Endpoints:** `docs/api/`
- **Troubleshooting:** `docs/troubleshooting/`
- **Architecture:** `docs/architecture/SMS_GATEWAY_ARCHITECTURE.md`

## Migration Guide

### v2.1 → v3.0

1. Deploy v3.0 workflow in new Pipedream workflow (don't replace v2.1 yet)
2. Update Laravel routes to add `/sms/public` endpoint
3. Test v3.0 with separate shortcode or test numbers
4. Switch Omni Channel webhook to v3.0 endpoint
5. Monitor for 24 hours, rollback if issues
6. Archive v2.1 workflow (keep for reference)

**Benefits of v3.0:**
- 65% less code (easier to maintain)
- All business logic in Laravel (easier to test)
- Clear separation of concerns (auth vs logic)
- Better error handling (Laravel validation)
