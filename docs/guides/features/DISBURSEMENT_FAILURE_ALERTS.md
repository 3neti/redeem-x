# Disbursement Failure Alerting & Audit Trail System

## Implementation Summary
**Status**: Phase 1 & 2 Complete ✅ | Phase 3 Planned

This system provides immediate admin notifications and persistent audit trails for disbursement failures, enabling:
- Real-time alerting to support/ops teams via email
- Comprehensive audit trail for bank reconciliation
- Historical analysis of failure patterns
- Accountability and transparency in payment operations

**Key Architecture Decision**: Audit trail infrastructure (DisbursementAttempt model, migration) lives in the `payment-gateway` package (reusable), while notification logic (email templates, listeners) lives in the host app (application-specific).

## Problem Statement
When disbursements fail (timeout, gateway errors, insufficient funds, etc.), there is no immediate notification to admins/support staff and no centralized audit trail for reconciliation with banks/EMIs. This creates:
- Delayed response to customer issues
- No visibility into failure patterns
- Difficult reconciliation with bank reports
- Poor customer service escalation

## Solution Overview
Three-phase implementation:
1. **Phase 1**: Immediate email alerts to admins when disbursements fail
2. **Phase 2**: Persistent audit trail database for reconciliation
3. **Phase 3**: Admin dashboard for viewing failures and metrics

## Phase 1: Immediate Alerting

### Components
- **DisbursementFailed Event**: Enhanced to include exception details and redeemer mobile
- **DisbursementFailedNotification**: Email notification sent to admins (queued)
- **NotifyAdminOfDisbursementFailure Listener**: Handles event, applies throttling, sends notification

### Alert Recipients (Priority Order)
1. **System User** (from `SYSTEM_USER_ID` env, default: `admin@disburse.cash`)
   - Resolved via `SystemUserResolverService` from wallet package
   - Primary admin for all disbursement operations
2. **Admin Role Users** (users with "admin" role)
   - Merged and deduplicated with system user
3. **Config Emails** (fallback if no user recipients)
   - From `DISBURSEMENT_ALERT_EMAILS` environment variable

### Throttling (Alert Spam Prevention)
To prevent alert spam during outages (e.g., bank downtime for 30-60 minutes):
- **Strategy**: First alert sent immediately, subsequent alerts of same error type suppressed
- **Cooldown**: 30 minutes by default (configurable via `DISBURSEMENT_ALERT_THROTTLE_MINUTES`)
- **Grouping**: By exception class (RuntimeException, GatewayException, etc.)
- **Example**: 100 timeout errors during bank outage → Only 1 alert sent, 99 suppressed
- **Metrics**: Suppressed count tracked in cache for reporting

### Configuration
```bash
# .env
DISBURSEMENT_ALERT_ENABLED=true
DISBURSEMENT_ALERT_EMAILS=support@example.com,ops@example.com
DISBURSEMENT_ALERT_THROTTLE_MINUTES=30  # Cooldown period (0 = disabled)
SYSTEM_USER_ID=admin@disburse.cash      # Primary admin user
```

### Email Template
- Subject: "🚨 Disbursement Failed: {VOUCHER_CODE}"
- Includes: Voucher code, amount, redeemer mobile, error message, timestamp
- Link to voucher details page
- Queued delivery (non-blocking)
- Styled as error notification (red theme)

## Phase 2: Audit Trail Database

### Database Schema
Table: `disbursement_attempts`
- Tracks every disbursement attempt (success or failure)
- Stores: voucher, amount, mobile, bank, gateway, reference IDs
- Error details: type, message, full trace
- Request/response payloads for debugging
- Timestamps for reconciliation

### Model
`DisbursementAttempt` model with relationships:
- `belongsTo` Voucher
- `belongsTo` User (voucher issuer)
- Scopes: `failed()`, `success()`, `recent()`

### Usage
Every disbursement attempt is logged BEFORE execution:
1. Create record with status `pending`
2. Attempt disbursement
3. Update record with `success` or `failed` status
4. Store gateway response and error details

## Phase 3: Admin Dashboard

### Features
- Failed disbursements table with filters
- Success rate metrics (24h, 7d, 30d)
- Failure breakdown by type
- Export to CSV for bank reconciliation

### Routes
- `GET /admin/disbursements/failed` - Failed disbursements list
- `GET /admin/disbursements/metrics` - Metrics dashboard

## Testing

### Test Command
```bash
php artisan test:disbursement-failure --type=timeout --email=admin@example.com
php artisan test:disbursement-failure --type=gateway_error
php artisan test:disbursement-failure --type=insufficient_funds
```

Simulates disbursement failures for testing:
- Creates test voucher and contact
- Creates disbursement attempt record
- Fires DisbursementFailed event
- Queues notification (process with `php artisan queue:work`)

### Throttling Test
```bash
# Clear cache to reset throttling
php artisan cache:clear

# First alert (should send)
php artisan test:disbursement-failure --type=timeout --email=test@example.com
php artisan queue:work --stop-when-empty

# Second alert (should be throttled)
php artisan test:disbursement-failure --type=timeout --email=test@example.com
php artisan queue:work --stop-when-empty  # No jobs processed

# Check suppression count
php artisan tinker --execute="echo Cache::get('disbursement_alert_suppressed:RuntimeException');"
```

### Automated Tests
- Unit test: Notification renders correctly
- Feature test: Event listener sends notification on failure
- Integration test: Failed disbursement creates audit record
- Throttling test: Duplicate alerts suppressed within cooldown window

## Rollout Strategy
1. Deploy Phase 1 immediately (critical for customer service)
2. Monitor alert volume for 1 week
3. Deploy Phase 2 for historical analysis
4. Phase 3 when time permits

## Files Modified/Created

### Phase 1 (Implemented)
- `packages/wallet/src/Events/DisbursementFailed.php` - Added exception and mobile properties ✅
- `app/Notifications/DisbursementFailedNotification.php` - Email notification with mobile parameter ✅
- `app/Listeners/NotifyAdminOfDisbursementFailure.php` - Event listener with throttling and system user resolution ✅
- `app/Providers/AppServiceProvider.php` - Registered listener ✅
- `config/disbursement.php` - Alert configuration with throttling settings ✅
- `app/Console/Commands/TestDisbursementFailureCommand.php` - Test command for simulating failures ✅
- `.env.example` - Added alert and throttle configuration ✅

### Phase 2 (Implemented)
- `packages/payment-gateway/database/migrations/2025_12_22_115516_create_disbursement_attempts_table.php` - Audit table ✅
- `packages/payment-gateway/src/Models/DisbursementAttempt.php` - Model with scopes ✅
- `packages/payment-gateway/src/Data/Disburse/DisburseInputData.php` - Added voucher context fields ✅
- `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php` - Log all attempts ✅
- `tests/Feature/DisbursementFailureAlertTest.php` - 6 tests, 17 assertions ✅

### Phase 3 (Not Implemented)
- `app/Http/Controllers/Admin/DisbursementFailuresController.php` - Planned
- `resources/js/pages/admin/DisbursementFailures.vue` - Planned
- Routes and navigation - Planned
