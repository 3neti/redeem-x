# SMS Command Routing System - Refactor TODO

**Status:** Planning  
**Priority:** Medium  
**Plan ID:** 61f04dc3-a719-46fe-b799-4dbb2cee375a

## Overview
Refactor Pipedream SMS workflow (`docs/pipedream-generate-voucher.js`) to use Laravel-inspired routing system. Currently requires 4-5 file edits to add new commands. Goal: reduce to 2 lines (route registration + handler).

## Backend Integration Points

### Existing API Endpoints (Used by Pipedream)

**Voucher Generation API:**
- Route: `POST /api/v1/vouchers`
- Action: `App\Actions\Api\Vouchers\GenerateVouchers`
- File: `app/Actions/Api/Vouchers/GenerateVouchers.php`
- Used by: GENERATE command in Pipedream
- Route file: `routes/api/vouchers.php`

**SMS Redemption API:**
- Route: `POST /api/v1/redeem/sms`
- Action: `App\Actions\Api\Redemption\RedeemViaSms`
- File: `app/Actions/Api/Redemption/RedeemViaSms.php`
- Used by: REDEEM command in Pipedream
- Route file: `routes/api/redemption.php`

**Redemption Validation API:**
- Route: `POST /api/v1/redeem/validate`
- Action: `App\Actions\Api\Redemption\ValidateRedemptionCode`
- Used by: REDEEM command (validation check)

### Events

**VoucherRedeemedViaMessaging**
- File: `app/Events/VoucherRedeemedViaMessaging.php`
- Fired by: `RedeemViaSms` action
- Properties:
  - `voucher`: Voucher model
  - `contact`: Contact model
  - `channel`: 'sms', 'viber', 'messenger', 'whatsapp'
  - `bankAccount`: Resolved bank account
  - `messageMetadata`: Original message data
- **No listeners yet** - available for future hooks

### Supporting Infrastructure

**Contact Management:**
- Model: `LBHurtado\Contact\Models\Contact`
- Used by: SMS redemption to store/retrieve contact info
- Bank account resolution via `BankAccount::fromBankAccount()`

**Bank Aliases Config:**
- File: `config/bank-aliases.php`
- Maps friendly codes (GCASH, BDO) to SWIFT codes
- Used by: `RedeemViaSms` for bank resolution

**Data Store:**
- Platform: Pipedream Data Store
- Store name: "redeem-x"
- Used by: AUTHENTICATE command to store API tokens
- Key: Mobile number, Value: `{ token, created_at, mobile }`

**Test Command:**
- File: `app/Console/Commands/TestSmsRedemptionCommand.php`
- Command: `php artisan test:sms-redeem {code} {mobile} {--bank=}`
- Tests SMS redemption locally

## Potential Small Feature Updates

### 1. BALANCE Command (New)
**Backend Needed:**
- Endpoint: `GET /api/v1/wallet/balance`
- Action: Check user wallet balance via token
- Response: `{ balance: 500.00, currency: "PHP" }`

**Pipedream Change:**
```javascript
router.route('BALANCE', 'balance', handleBalance);
async function handleBalance(params, { sender, store }) {
  const token = await store.get(sender);
  const response = await axios.get(`${API}/wallet/balance`, {
    headers: { Authorization: `Bearer ${token.token}` }
  });
  return { status: 'success', message: `💰 Balance: ₱${response.data.balance}` };
}
```

### 2. HELP Command (New)
**Backend:** None needed (client-side only)
**Pipedream Change:**
```javascript
router.route('HELP', 'help {command?}', handleHelp);
async function handleHelp(params) {
  if (params.command) {
    // Show specific command help
  } else {
    // List all commands from router.routes
  }
}
```

### 3. STATUS Command (New)
**Backend:** Already exists!
- Endpoint: `GET /api/v1/redeem/status/{code}`
- Action: `App\Actions\Api\Redemption\GetRedemptionStatus`
- Route: `routes/api/redemption.php` (line 59)

**Pipedream Change:**
```javascript
router.route('STATUS', 'status {code:voucher}', handleStatus);
async function handleStatus(params, { sender }) {
  const response = await axios.get(`${API}/redeem/status/${params.code}`);
  return { status: 'success', message: response.data.message };
}
```

### 4. Enhanced GENERATE (Options)
**Backend:** Already supports via GenerateVouchers action
- Support: `count`, `prefix`, `ttl_hours`, `feedback_mobile`

**Pipedream Change:**
```javascript
// Current: GENERATE 100
// New: GENERATE 100 COUNT:5 PREFIX:ABC

router.route('GENERATE', 'generate {amount:int} {options*}', handleGenerate);
// Parse options: COUNT:5 PREFIX:ABC → { count: 5, prefix: 'ABC' }
```

### 5. HISTORY Command (New)
**Backend Needed:**
- Endpoint: `GET /api/v1/vouchers?user_mobile={mobile}&limit=5`
- Use existing: `App\Actions\Api\Vouchers\ListVouchers`
- Filter by feedback_mobile or redemption contact

**Pipedream Change:**
```javascript
router.route('HISTORY', 'history', handleHistory);
// Returns last 5 vouchers generated/redeemed by this mobile
```

### 6. Event Listeners (Backend)
**Opportunity:** Listen to `VoucherRedeemedViaMessaging`

**Potential Listeners:**
- Analytics tracking (log to analytics service)
- Notification aggregation (send daily summary)
- Fraud detection (pattern analysis)
- Third-party webhooks (trigger external systems)

**Example:**
```php
// app/Listeners/TrackMessagingRedemption.php
class TrackMessagingRedemption
{
    public function handle(VoucherRedeemedViaMessaging $event)
    {
        // Log to analytics
        Analytics::track('voucher_redeemed_via_messaging', [
            'channel' => $event->channel,
            'amount' => $event->voucher->amount,
            'bank' => $event->bankAccount,
        ]);
    }
}
```

## Frontend Integration Points (Future)

### Potential SMS-to-Web Bridge
**Use Case:** Send SMS with voucher link  
**Backend:** Already exists via `GenerateVouchers` (returns `redemption_url`)  
**Example:** User texts "LINK ABCD" → Receives SMS with `https://redeem-x.test/redeem?code=ABCD`

### QR Code via SMS
**Use Case:** Send voucher QR as image link  
**Backend:** Already exists!
- Endpoint: `GET /api/v1/vouchers/{code}/qr`
- Action: `App\Actions\Api\Vouchers\GenerateVoucherQr`
- Returns: Base64 PNG or URL

## Implementation TODO

### Phase 1: Create Router Infrastructure
- [ ] Create `SMSRouter` class in Pipedream script
- [ ] Implement `compilePattern()` method
- [ ] Add type constraints (int, string, voucher, bank)
- [ ] Create `extractParams()` method
- [ ] Add `dispatch()` method

### Phase 2: Register Existing Commands
- [ ] Register AUTHENTICATE route
- [ ] Register GENERATE route  
- [ ] Register REDEEM route
- [ ] Keep existing handlers (backward compatibility)

### Phase 3: Test Router (Parallel)
- [ ] Test AUTHENTICATE with router
- [ ] Test GENERATE with router
- [ ] Test REDEEM with router
- [ ] Ensure 100% backward compatibility

### Phase 4: Switch Main Workflow
- [ ] Replace sequential checks with `router.dispatch()`
- [ ] Update exports logic
- [ ] Test all commands end-to-end

### Phase 5: Add New Commands
- [ ] Add BALANCE command + handler
- [ ] Add HELP command + handler
- [ ] Add STATUS command + handler
- [ ] Test new commands

### Phase 6: Cleanup
- [ ] Remove old `COMMAND_PATTERNS`
- [ ] Remove old routing logic
- [ ] Update documentation
- [ ] Bump version to 3.0.0

## File Locations

**Frontend (Pipedream):**
- Workflow: `docs/pipedream-generate-voucher.js` (500 lines)
- Documentation: `docs/PIPEDREAM_SMS_WORKFLOW.md`

**Backend (Laravel):**
- Voucher routes: `routes/api/vouchers.php`
- Redemption routes: `routes/api/redemption.php`
- SMS redemption: `app/Actions/Api/Redemption/RedeemViaSms.php`
- Voucher generation: `app/Actions/Api/Vouchers/GenerateVouchers.php`
- Event: `app/Events/VoucherRedeemedViaMessaging.php`
- Bank config: `config/bank-aliases.php`
- Test command: `app/Console/Commands/TestSmsRedemptionCommand.php`

## Success Criteria

✅ Add new SMS command with 2 lines of code (route + handler)  
✅ Zero breaking changes to existing commands  
✅ BALANCE, HELP, STATUS commands working  
✅ Pattern syntax matches Laravel: `{param}`, `{param?}`, `{param:type}`  
✅ All tests passing  
✅ Documentation updated  

## Related Plans

- Main Plan: `plans/61f04dc3-a719-46fe-b799-4dbb2cee375a`
- See: `docs/PIPEDREAM_SMS_WORKFLOW.md` for current architecture

## Notes

- **No backend changes required** for routing refactor (pure Pipedream)
- Backend APIs are stable and well-structured
- Event system ready for listeners (no listeners yet)
- Bank aliases config makes adding new banks trivial
- All SMS features map to existing backend endpoints
