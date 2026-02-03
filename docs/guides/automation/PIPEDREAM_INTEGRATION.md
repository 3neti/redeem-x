# Pipedream Integration

For complete Pipedream integration documentation, see:

**[integrations/pipedream/README.md](../../../integrations/pipedream/README.md)**

## Quick Links

- **Architecture Overview:** [integrations/pipedream/README.md#architecture-overview](../../../integrations/pipedream/README.md#architecture-overview)
- **Deployment Guide:** [integrations/pipedream/README.md#deployment](../../../integrations/pipedream/README.md#deployment)
- **Troubleshooting:** [integrations/pipedream/README.md#troubleshooting](../../../integrations/pipedream/README.md#troubleshooting)
- **Version History:** [integrations/pipedream/CHANGELOG.md](../../../integrations/pipedream/CHANGELOG.md)

## Workflows

### Current Production Version: v3.0.0 (token-based-routing.js)

**Simplified authentication proxy architecture:**
- Pipedream handles AUTHENTICATE command only
- All business logic in Laravel
- Routes to `/sms` (authenticated) or `/sms/public` (unauthenticated)
- ~200 lines (65% smaller than v2.1)

**File:** `integrations/pipedream/token-based-routing.js`

### Legacy Version: v2.1.0 (generate-voucher.js)

**Full SMS command handling workflow:**
- Handles AUTHENTICATE, GENERATE, REDEEM, BALANCE in Pipedream
- 570 lines of code
- Being phased out in favor of v3.0

**File:** `integrations/pipedream/generate-voucher.js`

## SMS Flow

```
SMS User → Omni Channel (22560537) → Pipedream → Laravel → Response
```

1. User sends SMS to shortcode
2. Pipedream receives webhook from Omni Channel
3. AUTHENTICATE command → Pipedream stores token
4. Other commands → Pipedream forwards to Laravel with token
5. Laravel processes and responds
6. Response sent back via SMS

## Configuration

**Environment Variables:**
```bash
LARAVEL_API_URL=https://redeem-x.test/api/v1
PIPEDREAM_DATA_STORE_KEY=redeem-x
```

**Laravel Routes (v3.0):**
```php
Route::post('/sms', [SmsController::class, 'handle'])->middleware('auth:sanctum');
Route::post('/sms/public', [SmsController::class, 'handlePublic']);
```

## Testing Commands

See `CONSOLE_COMMANDS.md` for SMS testing commands:
- `php artisan test:sms` - Test SMS sending
- `php artisan test:sms-balance` - Test BALANCE command
- `php artisan test:sms-redeem` - Test voucher redemption
- `php artisan test:sms-router` - Test command routing locally

## Migration from v2.1 to v3.0

See complete migration guide in `integrations/pipedream/README.md#migration-guide`.

**Benefits:**
- 65% less code
- All business logic in Laravel (easier to test)
- Clear separation of concerns
- Better error handling

## Related Documentation

- **Console Commands:** `CONSOLE_COMMANDS.md`
- **Shell Scripts:** `SHELL_SCRIPTS.md`
- **SMS Architecture:** `docs/architecture/SMS_GATEWAY_ARCHITECTURE.md`
- **API Endpoints:** `docs/api/`
