# Redeem-X Postman Collections

Complete API collections for testing the Redeem-X voucher management system.

## Collections Overview

### 1. Management API (Complete User Journey)
**File:** `redeem-x-api.postman_collection.json`  
**Requests:** 31  
**Auth:** Session-based (Chrome Interceptor)  

Complete E2E flow for voucher issuers:
- ✅ Authentication & token management
- ✅ Wallet management & top-ups  
- ✅ Settings management
- ✅ Transaction history
- ✅ Deposits & senders

**Status:** All 31 tests passing

### 2. Voucher Generation API
**File:** `redeem-x-voucher-generation.postman_collection.json`  
**Requests:** 18  
**Auth:** Bearer token (Sanctum)  

Voucher creation and management for issuers:
- ✅ Generate vouchers (simple, custom, validation, bulk)
- ✅ List & query vouchers
- ✅ Voucher details (with auto-fetch fallback)
- ✅ Manage vouchers (QR code, metadata, cancel)

**Status:** All 18 tests passing

### 3. Redemption Flow API
**File:** `redeem-x-redemption-flow.postman_collection.json`  
**Requests:** 14  
**Auth:** None (public endpoints)  

Public redemption flow for beneficiaries:
- ✅ Validate voucher
- ✅ Start redemption session
- ✅ Submit wallet details
- ✅ Submit plugin data (location, selfie, signature)
- ✅ Finalize and confirm
- ✅ Check status
- ✅ Track timing analytics

**Status:** Ready for testing

## Quick Start

### 1. Setup Postman

**Install Chrome Interceptor** (for Management API only):
1. Install [Postman Interceptor](https://chrome.google.com/webstore/detail/postman-interceptor) Chrome extension
2. Open Postman Desktop → Click satellite icon (bottom right)
3. Enable "Interceptor" and "Sync cookies"
4. Log into http://redeem-x.test in Chrome

### 2. Import Collections

1. Import all 3 JSON files into Postman
2. Import environment: `redeem-x.postman_environment.json`
3. Select "Redeem-X" environment from dropdown

### 3. Run Collections

**Recommended order:**

1. **Management API** (creates tokens and wallet balance)
   - Requires: Chrome Interceptor + browser login
   - Run "01 - Authentication" folder first
   - Run "02 - Wallet Management" to add funds

2. **Voucher Generation** (creates vouchers)
   - Requires: `access_token` from Management API
   - Run "01 - Generate Vouchers" to create test vouchers
   - Variables auto-populate for subsequent requests

3. **Redemption Flow** (redeems vouchers)
   - Requires: `voucher_code` from Voucher Generation
   - Run "01 - Validate Voucher" first (auto-saves code)
   - Follow the 7-step redemption flow

## Authentication Methods

### Session Auth (Management API)
- Uses Laravel Sanctum SPA authentication
- Requires Chrome Interceptor to sync browser cookies
- Best for: Web UI testing

### Token Auth (Voucher Generation)
- Uses Bearer tokens from Sanctum
- Auto-set after "Create API Token" request
- Best for: Mobile apps, third-party integrations

### No Auth (Redemption Flow)
- Public endpoints for voucher redemption
- Uses signed session tokens for state management
- Best for: Public redemption interfaces

## Key Features & Lessons Learned

### Auto-Variable Population
All collections automatically extract and save variables:
- `access_token` - From token creation
- `voucher_code` - From voucher generation
- `session_token` - From redemption start
- `contact_id`, `token_id`, etc.

### URL Building (Avoiding Postman Bug)
Some requests use **pre-request scripts** to programmatically build URLs:
```javascript
const baseUrl = pm.collectionVariables.get('base_url');
const code = pm.collectionVariables.get('voucher_code');
pm.request.url = baseUrl + '/api/v1/vouchers/' + code + '/qr';
```

**Why?** Postman's `{{variable}}` interpolation fails when the URL path is stored as an array. This workaround ensures variables are properly injected.

### Policy Registration Fix
The VoucherPolicy wasn't auto-discovered because the Voucher model is from a package (`LBHurtado\Voucher`). We manually registered it in `AppServiceProvider`:
```php
Gate::policy(Voucher::class, VoucherPolicy::class);
```

### Console Logging
All collections include helpful console logs:
```javascript
console.log('Saved voucher_code:', code);
console.log('Built URL:', pm.request.url.toString());
```

View logs: `Cmd+Alt+C` (Mac) / `Ctrl+Alt+C` (Windows)

## Troubleshooting

### 403 Forbidden - "You do not have permission"
**Cause:** VoucherPolicy not registered  
**Fix:** Ensure `AppServiceProvider` has `Gate::policy(Voucher::class, VoucherPolicy::class)`

### 404 Not Found - Double slashes in URL
**Cause:** Postman variable interpolation bug  
**Fix:** Collections use pre-request scripts to build URLs programmatically

### Variables not saving
**Cause:** Test scripts not extracting variables  
**Fix:** Check Console (`Cmd+Alt+C`) for "Saved variable" logs

### Insufficient wallet balance
**Solution:** Run Management API → "02 - Wallet Management" → "Initiate Top-Up" first

### Session expired
**Solution:** Refresh http://redeem-x.test in Chrome, verify Interceptor is green

## Environment Variables

**Shared across all collections:**
- `base_url` - `http://redeem-x.test`
- `access_token` - Auto-populated
- `voucher_code` - Auto-populated
- `session_token` - Auto-populated
- `mobile` - Default: `09171234567`
- `campaign_id` - Auto-populated

## Support

**Logs:**
- Laravel: `/storage/logs/laravel.log`
- Postman Console: `Cmd+Alt+C` / `Ctrl+Alt+C`

**Debugging:**
1. Check request/response in Postman
2. View Console for variable extraction logs
3. Check Collection → Variables for current values
4. Review test results in Collection Runner
