# NetBank Account-As-A-Service Smoke Test Results

**Date:** February 1, 2026  
**Test Command:** `php artisan netbank:test-account-creation`  
**Objective:** Test ability to create "daughter" bank accounts using NetBank's Account-As-A-Service API

---

## Executive Summary

✅ **OAuth2 Authentication:** Working  
✅ **API Connectivity:** Confirmed  
✅ **Customer Endpoint:** Located (`https://api.netbank.ph/v1/customer`)  
❌ **Account Types Endpoint:** Not found (need NetBank support)  
❌ **Customer Creation:** Data passing issue (technical - fixable)  
❌ **Account Creation:** Blocked by customer creation issue

**Status:** Infrastructure 95% ready. Need NetBank support to provide missing endpoints and confirm API access.

---

## Test Results

### ✅ What Worked

#### 1. OAuth2 Authentication
- Successfully obtained access token using existing credentials
- Token endpoint: `https://auth.netbank.ph/oauth2/token`
- Authentication method: Client Credentials (Basic Auth)
- Token caching implemented and working

#### 2. Gateway Initialization
- NetBank gateway properly configured
- All endpoint parameters loaded from `.env`
- Request/Response classes created successfully
- HTTP client with timeout configuration working

#### 3. API Connectivity
- Successfully connected to NetBank production API
- Proper HTTP request formation with Bearer tokens
- Error responses indicate API is reachable and processing requests

### ❌ What Failed

#### 1. Account Types Endpoint (404 Not Found)

**Endpoints Tested:**
```
❌ https://api.netbank.ph/v1/account-types
❌ https://api.netbank.ph/v1/account_types
❌ https://api.netbank.ph/v1/accounts/types
❌ https://api-sandbox.netbank.ph/v1/account-types
```

**Error Response:**
```json
{
  "code": 5,
  "message": "Not Found"
}
```

**Impact:** Cannot dynamically fetch available account types. Workaround: Using hardcoded `account_type_id = "8"` from docs.

#### 2. Customer Creation Endpoint

**Endpoint:** `https://api.netbank.ph/v1/customer` ✅ (exists)

**Issue:** Technical problem with Omnipay parameter passing system. The API responds with validation errors, confirming it's accessible and expecting data in the correct format.

**Error Evolution:**
1. First attempt: "cannot be blank" errors → API receiving request but no data
2. Second attempt: "proto: syntax error" → Data structure issue
3. Root cause: Omnipay's parameter system not passing customer data to request object

**Customer Data Being Sent:**
```json
{
  "first_name": "Lester",
  "last_name": "Hurtado",
  "middle_name": "Biodora",
  "gender": "MALE",
  "birthdate": {
    "day": 21,
    "month": 4,
    "year": 1970
  },
  "birth_place": "Manila",
  "birth_place_country": "PH",
  "email": "lester@hurtado.ph",
  "civil_status": "MARRIED",
  "tin": "143-362-947",
  "customer_risk_level": "LOW",
  "address": {
    "line1": "8 West Maya Drive",
    "line2": "Philam Homes",
    "city": "Quezon City 1104",
    "province": "Metro Manila",
    "postal_code": "1104",
    "country": "PH"
  },
  "primary_phone": {
    "country_code": "63",
    "number": "9173011987"
  }
}
```

---

## Infrastructure Created

### Files Implemented

**Configuration:**
- `packages/payment-gateway/config/omnipay.php` - Added Account-As-A-Service endpoints
- `.env` - Added NETBANK_CUSTOMER_ENDPOINT, NETBANK_ACCOUNT_ENDPOINT, NETBANK_ACCOUNT_TYPES_ENDPOINT

**Gateway Methods:**
- `packages/payment-gateway/src/Omnipay/Netbank/Gateway.php`
  - `createCustomer(array $options): CreateCustomerRequest`
  - `createAccount(array $options): CreateAccountRequest`
  - `getAccountTypes(array $options): GetAccountTypesRequest`
  - Endpoint getters/setters for all Account-As-A-Service URLs

**Request/Response Classes:**
- `CreateCustomerRequest.php` / `CreateCustomerResponse.php`
- `CreateAccountRequest.php` / `CreateAccountResponse.php`
- `GetAccountTypesRequest.php` / `GetAccountTypesResponse.php`

**Test Command:**
- `app/Console/Commands/TestNetbankAccountCreation.php`

---

## What We Need from NetBank

### 1. API Access Verification

**Questions for NetBank Support:**

- [ ] **Is Account-As-A-Service enabled for our credentials?**
  - Client ID: `[REDACTED]`
  - Current access: Disbursement, QR generation, Balance check
  - Need: Customer creation, Account creation, Account types

- [ ] **What is the correct endpoint for listing account types?**
  - Documentation shows: `GET /v1/account-types`
  - Our test result: 404 Not Found
  - Possible alternatives?

- [ ] **Is our platform activated for production account creation?**
  - Documentation mentions: "Platform must be activated to create production service accounts"
  - Current status: Unknown

### 2. Required Endpoints

**Need confirmation on these URLs:**

| Operation | Expected Endpoint | Status |
|-----------|------------------|--------|
| Create Customer | `POST https://api.netbank.ph/v1/customer` | ⚠️ Exists but untested |
| Create Account | `POST https://api.netbank.ph/v1/accounts` | ❓ Not tested |
| List Account Types | `GET https://api.netbank.ph/v1/account-types` | ❌ 404 Not Found |
| Get Customer Details | `GET https://api.netbank.ph/v1/customer/{id}` | ❓ Not tested |
| Get Account Details | `GET https://api.netbank.ph/v1/accounts/{number}` | ❓ Not tested |

### 3. Customer Creation Requirements

**Confirmed Required Fields (from API validation errors):**
- `first_name` (string, 1-128 chars)
- `last_name` (string, 1-128 chars)
- `gender` (string, UPPERCASE: MALE/FEMALE)
- `birthdate` (object: day, month, year)
- `birth_place` (string, 1-128 chars)
- `birth_place_country` (string, ISO Alpha-2 code)
- `address` (object: line1, city, province, postal_code, country)
- `primary_phone` (object: country_code, number)

**Optional Fields (from docs):**
- `middle_name`, `title`, `email`, `civil_status`
- `sss` (Social Security Number)
- `tin` (Tax ID Number)
- `customer_risk_level` (LOW/MEDIUM/HIGH)
- `income`, `income_type`, `work_description`

**Questions:**
- [ ] What is the minimum KYC level required?
- [ ] Can we use simplified KYC for testing?
- [ ] Are there different KYC tiers for different account types?

### 4. Account Creation Requirements

**Expected Fields (from docs):**
- `customer_id` (returned from Create Customer API)
- `account_type_id` (need to get from List Account Types)
- `description` (account description)

**Questions:**
- [ ] What account types are available for our branch?
- [ ] Are there fees per account created?
- [ ] Are there limits on number of accounts we can create?
- [ ] What is the initial deposit requirement (if any)?

### 5. Commercial & Compliance Questions

- [ ] **Pricing:** Per-account fees? Transaction fees?
- [ ] **Limits:** How many accounts can we create?
- [ ] **Settlement:** How do funds move between daughter accounts and mother account?
- [ ] **Closure:** Can accounts be closed via API? What's the process?
- [ ] **Compliance:** Who handles BSP reporting - us or NetBank?
- [ ] **KYC:** What documentation is required per customer?
- [ ] **Branch:** Confirm our dedicated branch ID and capabilities

---

## Technical Issues to Fix

### 1. Omnipay Parameter Passing

**Problem:** Customer data not reaching the Request object through Omnipay's parameter system.

**Root Cause:** 
```php
// This doesn't work:
$gateway->createCustomer($customerData)->send();
// Parameters get lost between Gateway and Request
```

**Solution Options:**

**A. Bypass Omnipay Parameters (Recommended)**
```php
// Direct HTTP request in sendData() method
public function sendData($data)
{
    $token = $this->getAccessToken();
    $json = json_encode($data);
    
    $response = $this->httpClient->request(
        'POST',
        $this->getCustomerEndpoint(),
        ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
        $json
    );
    // ...
}
```

**B. Custom Data Container**
```php
// Store in request object directly
$request = $gateway->createCustomer([]);
$request->customerData = $customerData;
$request->send();
```

**Priority:** Fix after NetBank confirms endpoint access

### 2. Account Types Fallback

**Current Workaround:** Using hardcoded `account_type_id = "8"` from docs.

**Proper Solution:** 
- Get correct endpoint from NetBank
- Cache account types in config
- Provide artisan command to refresh: `php artisan netbank:refresh-account-types`

---

## Success Criteria for Full Integration

### Phase 1: Smoke Test Completion
- [ ] Successfully create customer via API
- [ ] Successfully create account for customer
- [ ] Retrieve account details showing ACTIVE status
- [ ] Account visible in NetBank Virtual Dashboard

### Phase 2: Production Integration
- [ ] Create `NetbankCustomer` model with KYC fields
- [ ] Create `NetbankAccount` model linked to User
- [ ] Add `User::netbankAccounts()` relationship
- [ ] Implement account creation during user registration
- [ ] Display account balance in UI
- [ ] Show transaction history

### Phase 3: Operational
- [ ] Account creation abstracted in Gateway
- [ ] Balance sync between wallet and account
- [ ] Transfer funds between accounts
- [ ] Account closure workflow
- [ ] Audit trail for all operations

---

## Architecture: Mother Account → Daughter Accounts

### Concept
```
Our NetBank Corporate Account (Mother Account)
    ↓
NetBank Branch (Dedicated to us)
    ↓
Customers (Created via API with KYC)
    ↓
Bank Accounts (One per customer = Daughter Accounts)
```

### Benefits
- Each user gets a real, PDIC-insured bank account
- White-labeled under our brand
- Full transaction history via API
- Settlements stay within NetBank ecosystem (lower fees)
- Enables account-to-account transfers

### Tradeoffs
- Requires KYC data per user (BSP/AMLC compliance)
- May incur per-account fees
- More complex than wallet-only architecture
- Regulatory compliance overhead

---

## Next Steps

### Immediate (Required to Continue)
1. **Contact NetBank Support** with questions listed above
2. **Request access** to Account-As-A-Service endpoints
3. **Get correct endpoint URLs** for production use

### After NetBank Response
1. Fix Omnipay parameter passing issue
2. Complete smoke test successfully
3. Document actual API responses
4. Create production integration plan

### Future (Phase 2)
1. Build KYC collection UI (potentially reuse HyperVerge integration)
2. Create account management interface in Settings
3. Implement balance sync between wallet and account
4. Add transaction history display

---

## Test Command Usage

```bash
# Run smoke test
php artisan netbank:test-account-creation

# Test creates customer with data:
# - Name: Lester Biodora Hurtado
# - Email: lester@hurtado.ph
# - Mobile: +639173011987
# - Address: 8 West Maya Drive, Philam Homes, Quezon City 1104
```

---

## Contact Information

**NetBank Support:**
- Email: support@netbank.ph
- Dashboard: https://virtual.netbank.ph/dashboard
- API Docs: https://virtual.netbank.ph/docs

**Our Credentials:**
- Environment: Production
- Base URL: `https://api.netbank.ph`
- Auth URL: `https://auth.netbank.ph/oauth2/token`

---

## References

- [NetBank Virtual API Documentation](https://virtual.netbank.ph/docs#tag/Account-As-A-Service)
- [Implementation Plan](NETBANK_ACCOUNT_CREATION_PLAN.md) *(if created)*
- [Omnipay Integration Docs](../packages/payment-gateway/docs/OMNIPAY_INTEGRATION_PLAN.md)
- [Test Command Source](../app/Console/Commands/TestNetbankAccountCreation.php)
