# Balance Monitoring - Phase 1 Test Results

**Date:** November 14, 2025  
**Phase:** 1 - Test & Validate Current Implementation  
**Status:** ⚠️ **BLOCKED** - API Endpoint Returns 404

---

## Summary

The existing balance check implementation is **partially working** but the NetBank API endpoint returns **HTTP 404**, indicating either:
1. The endpoint URL is incorrect
2. The API endpoint doesn't exist in the test environment
3. The account number format is wrong
4. Additional authentication/parameters are needed

---

## Current Configuration

**Environment Variables:**
```bash
NETBANK_BALANCE_ENDPOINT=https://virtual.netbank.ph/api/v1/accounts
OMNIPAY_TEST_ACCOUNT=113-001-00001-9
```

**Endpoint Being Called:**
```
GET https://virtual.netbank.ph/api/v1/accounts/113-001-00001-9
Authorization: Bearer {token}
```

**Response:**
```
HTTP 404 Not Found
Body Length: 4134 bytes
Data: null (HTML 404 page)
```

---

## Test Results

### Test 1: Original Endpoint (with /details)
```bash
php artisan omnipay:balance --account=113-001-00001-9
```

**Endpoint:** `https://virtual.netbank.ph/api/v1/accounts/113-001-00001-9/details`  
**Result:** ❌ HTTP 404

### Test 2: Corrected Endpoint (without /details)
**Endpoint:** `https://virtual.netbank.ph/api/v1/accounts/113-001-00001-9`  
**Result:** ❌ HTTP 404

---

## Code Analysis

### ✅ What's Working
1. **OAuth2 Authentication** - Token retrieval is successful
2. **Request Formation** - HTTP request is properly formed
3. **Error Handling** - Catches and logs errors appropriately
4. **Command Interface** - CLI tool works correctly

### ❌ What's Not Working
1. **API Endpoint** - Returns 404
2. **Response Parsing** - Cannot parse null response

---

## Possible Causes

### 1. Wrong Base URL
The base URL might be incorrect. Possible alternatives:
- `https://api.netbank.ph/v1/accounts` (without `virtual` subdomain)
- `https://virtual.netbank.ph/v1/accounts` (without `/api`)
- `https://virtual.netbank.ph/accounts` (no version)

### 2. Different Endpoint Path
NetBank documentation might use a different path:
- `/api/accounts/{account_number}/balance`
- `/api/accounts/{account_number}/details`
- `/api/accounts/{account_number}/info`
- `/api/v1/account-details/{account_number}`

### 3. Account Number Format
The account number might need different formatting:
- Current: `113-001-00001-9` (with dashes)
- Try: `113001000019` (without dashes)
- Try: URL encoded: `113%2D001%2D00001%2D9`

### 4. Missing Query Parameters
The API might require additional parameters:
```
GET /accounts/{account_number}?include=balance
GET /accounts/{account_number}?type=details
```

### 5. Different HTTP Method
The API might expect POST instead of GET:
```
POST /accounts/balance
Body: {"account_number": "113-001-00001-9"}
```

---

## Recommendations

### Option A: Verify NetBank Documentation (RECOMMENDED)
**Action:** Contact NetBank or check official API documentation  
**Time:** 1-2 hours  
**Risk:** Low

**Questions to ask:**
1. What is the correct endpoint for retrieving account balance?
2. What authentication is required?
3. What is the expected request format?
4. What is the response structure?

### Option B: API Exploration
**Action:** Try different endpoint variations  
**Time:** 30 minutes  
**Risk:** Medium (might hit rate limits)

**Test these endpoints:**
```bash
# Try without version
curl -H "Authorization: Bearer {token}" \
  https://virtual.netbank.ph/accounts/113-001-00001-9

# Try api subdomain
curl -H "Authorization: Bearer {token}" \
  https://api.netbank.ph/v1/accounts/113-001-00001-9

# Try balance-specific endpoint
curl -H "Authorization: Bearer {token}" \
  https://virtual.netbank.ph/api/v1/accounts/113-001-00001-9/balance

# Try without dashes
curl -H "Authorization: Bearer {token}" \
  https://virtual.netbank.ph/api/v1/accounts/113001000019
```

### Option C: Use Mock/Stub for Now (PRAGMATIC)
**Action:** Create mock balance response for development  
**Time:** 15 minutes  
**Risk:** Low

**Implementation:**
```php
// In CheckBalanceRequest
if (config('app.env') === 'local' && config('omnipay.mock_balance', false)) {
    return new CheckBalanceResponse($this, [
        'status' => 'success',
        'data' => [
            'account_number' => $this->getAccountNumber(),
            'balance' => 1250000, // PHP 12,500.00
            'available_balance' => 1200000,
            'currency' => 'PHP',
            'as_of' => now()->toIso8601String(),
        ],
    ]);
}
```

**Benefits:**
- Can proceed with Phase 2 & 3 development
- Fix endpoint later when correct URL is known
- Test full balance monitoring system

---

## NetBank API Documentation Reference

Based on the link you provided:
```
https://virtual.netbank.ph/docs#operation/Account-As-A-Service_RetrieveBankAccountDetails
```

The operation is named: **"Retrieve Bank Account Details"**

**Likely possibilities:**
1. `GET /api/v1/accounts/{account_number}/details` ✗ (tested - 404)
2. `GET /api/v1/accounts/{account_number}` ✗ (tested - 404)
3. `GET /api/v1/bank-accounts/{account_number}/details` (not tested)
4. `GET /api/v1/account-details/{account_number}` (not tested)

---

## Next Steps

### Immediate Actions (Choose One)

**1. Get Correct Endpoint** (if you have access to docs)
- Check NetBank Swagger/OpenAPI docs
- Look for example curl commands
- Contact NetBank support

**2. Skip to Phase 2 with Mock Data**
- Add mock response for development
- Implement PaymentGatewayInterface integration
- Fix endpoint when correct URL is known
- Document that endpoint needs verification

**3. API Exploration**
- Systematically test endpoint variations
- Document results
- Find working endpoint

---

## Files Modified (Debugging)

### Enhanced Logging
1. `packages/payment-gateway/src/Omnipay/Netbank/Message/CheckBalanceRequest.php`
   - Added try/catch error handling
   - Added request/response logging
   - Returns error response on exception

2. `packages/payment-gateway/src/Omnipay/Netbank/Message/CheckBalanceResponse.php`
   - Added raw response logging
   - Better error messages

### Endpoint Fix Attempted
1. `CheckBalanceRequest.php` line 135
   - Changed from: `'/' . $accountNumber . '/details'`
   - Changed to: `'/' . $accountNumber`
   - Result: Still 404

---

## Conclusion

**Phase 1 Status:** ⚠️ **Partially Complete**

✅ **Completed:**
- Verified OAuth2 authentication works
- Confirmed HTTP client works
- Added comprehensive logging
- Identified the issue (404 endpoint)

❌ **Blocked:**
- Cannot verify actual API response structure
- Cannot test with real balance data
- Cannot proceed with full testing

**Recommendation:** Proceed with **Option C (Mock Data)** to unblock development, while pursuing **Option A (Verify Documentation)** in parallel.

---

**What would you like to do next?**
1. Get correct NetBank API endpoint URL
2. Proceed with mock data and continue to Phase 2
3. Explore different endpoint variations
4. Something else?
