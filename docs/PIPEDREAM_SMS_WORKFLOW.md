# Pipedream SMS-to-Voucher Workflow

**Version**: 2.0.0 (Refactored - Modular Architecture)  
**Status**: ✅ Production Ready  
**Last Updated**: 2026-01-24  
**Author**: Lester Hurtado

## Overview

This Pipedream workflow enables secure SMS-based authentication and voucher generation through the Omni Channel shortcode `22560537`. Version 2.0 introduces a unified workflow with command routing, modular architecture, and Pipedream Data Store integration for secure token management.

### Flow Diagram

```
User → SMS (22560537) → Omni Channel (13.250.187.118) → Pipedream
                                                             ↓
                                                    Command Router
                                                     /          \
                                        AUTHENTICATE            GENERATE
                                           ↓                        ↓
                                    Data Store             Retrieve Token
                                  (Store Token)                    ↓
                                                          redeem-x API
                                                         POST /vouchers
                                                                ↓
                                                          SMS Reply
```

## SMS Commands

### 1. AUTHENTICATE Command

**Purpose**: Store API token for your mobile number (one-time setup or rotation)

**Format**: `AUTHENTICATE {token}`  
**Case Insensitive**: Yes

**Examples**:
```
AUTHENTICATE 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de
authenticate 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de
Authenticate 3|abc123def456...
```

**Success Response**:
```
✅ API token saved successfully for 639173011987. You can now use GENERATE commands.
```

**Error Responses**:
```
❌ Invalid token format. Please provide a valid API token.
⚠️ Failed to save token. Please try again.
```

**When to Use**:
- First-time setup
- Token rotation (security best practice: every 90-365 days)
- After token expiration
- If you get "No API token found" error

**How to Get Token**:
1. Login to redeem-x dashboard: https://redeem-x.laravel.cloud
2. Go to Settings → API Tokens
3. Generate new token with `voucher:create` ability
4. Copy token and send via SMS: `AUTHENTICATE {token}`

---

### 2. GENERATE Command

**Purpose**: Generate voucher using your stored token

**Format**: `GENERATE {amount}`  
**Case Insensitive**: Yes

**Examples**:
```
Generate 100    → Generates ₱100 voucher
generate 50     → Generates ₱50 voucher
GENERATE 1000   → Generates ₱1,000 voucher
```

**Success Response**:
```
✅ Voucher ABC-1234 generated (₱100.00). Redeem at: https://redeem-x.laravel.cloud/disburse
```

**Error Responses**:
```
❌ No API token found for your number. Please send AUTHENTICATE command first.
❌ Invalid amount. Please use format: Generate {amount} (e.g., Generate 100)
❌ Insufficient wallet balance. Please top up your account.
⚠️ Too many requests. Please wait a moment and try again.
⚠️ System error. Please contact support.
```

---

## Technical Architecture

### File: `docs/pipedream-generate-voucher.js`

**Version**: 2.0.0 (Refactored)  
**Lines of Code**: ~363  
**Language**: JavaScript (Node.js runtime)

### Code Structure

The workflow is organized into four clean sections:

```javascript
// ============================================================================
// CONFIGURATION (Lines 37-78)
// ============================================================================
const CONFIG = { REDEEMX_API_URL, DEFAULT_COUNT, MIN_TOKEN_LENGTH };
const COMMAND_PATTERNS = { AUTHENTICATE, GENERATE };
const MESSAGES = { AUTHENTICATE: {...}, GENERATE: {...} };

// ============================================================================
// COMMAND HANDLERS (Lines 80-202)
// ============================================================================
async function handleAuthenticate(sender, smsText, store, $) { }
async function handleGenerate(sender, smsText, store, $) { }

// ============================================================================
// API INTEGRATION (Lines 204-309)
// ============================================================================
async function callVoucherAPI(sender, amount, token) { }
function formatAPIError(error) { }

// ============================================================================
// MAIN WORKFLOW (Lines 311-363)
// ============================================================================
export default defineComponent({ async run({ steps, $ }) { } });
```

### Key Functions

#### 1. Configuration Constants

**Purpose**: Centralize all configuration, patterns, and messages

```javascript
const CONFIG = {
  REDEEMX_API_URL: process.env.REDEEMX_API_URL || "https://redeem-x.laravel.cloud/api/v1",
  DEFAULT_COUNT: 1,
  MIN_TOKEN_LENGTH: 10,
};

const COMMAND_PATTERNS = {
  AUTHENTICATE: /^authenticate\s+(.+)/i,
  GENERATE: /^generate\s+(\d+)/i,
};

const MESSAGES = {
  AUTHENTICATE: {
    SUCCESS: (mobile) => `✅ API token saved...`,
    INVALID_TOKEN: "❌ Invalid token format...",
    STORE_ERROR: "⚠️ Failed to save token...",
  },
  GENERATE: { /* ... */ },
};
```

**Benefits**:
- ✅ Single place to update all messages
- ✅ Easy to add new commands (just add pattern)
- ✅ Environment-aware configuration

---

#### 2. handleAuthenticate()

**Signature**: `async function handleAuthenticate(sender, smsText, store, $)`

**Purpose**: Store API token in Pipedream Data Store

**Flow**:
1. Match SMS against AUTHENTICATE pattern
2. Return `null` if no match (enables chaining)
3. Extract and trim token from regex capture group
4. Validate token length (min 10 chars)
5. Store in Data Store: `{ token, created_at, mobile }`
6. Return result object with status and message

**Data Store Structure**:
```javascript
{
  "639173011987": {
    "token": "3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de",
    "created_at": "2026-01-24T11:33:07.535Z",
    "mobile": "639173011987"
  }
}
```

**Error Handling**:
- Invalid token format → User-friendly error
- Data Store failure → Retry message

---

#### 3. handleGenerate()

**Signature**: `async function handleGenerate(sender, smsText, store, $)`

**Purpose**: Retrieve token and generate voucher

**Flow**:
1. Match SMS against GENERATE pattern
2. Return `null` if no match
3. Extract and parse amount as integer
4. Validate amount (must be positive)
5. Retrieve token from Data Store by sender mobile
6. Check token exists (error if not found)
7. Call `callVoucherAPI(sender, amount, token)`
8. Return voucher result

**Token Retrieval**:
```javascript
tokenData = await store.get(sender);
// Returns: { token: "3|...", created_at: "...", mobile: "..." }
```

**Error Handling**:
- No token found → "Please AUTHENTICATE first"
- Invalid amount → Format example message
- Data Store failure → Retry or re-authenticate
- API errors → Delegated to `formatAPIError()`

---

#### 4. callVoucherAPI()

**Signature**: `async function callVoucherAPI(sender, amount, token)`

**Purpose**: Make authenticated API call to redeem-x

**Flow**:
1. Generate idempotency key: `sms-{mobile}-{timestamp}`
2. Prepare payload with amount, count, feedback_mobile
3. Set headers: Authorization, Content-Type, Idempotency-Key
4. POST to `/api/v1/vouchers`
5. Parse nested response structure: `response.data.data.vouchers[0]`
6. Return formatted success message with voucher details

**Request Example**:
```javascript
{
  url: "https://redeem-x.laravel.cloud/api/v1/vouchers",
  method: "POST",
  headers: {
    "Authorization": "Bearer 3|TByBd...",
    "Content-Type": "application/json",
    "Accept": "application/json",
    "Idempotency-Key": "sms-639173011987-1706083200"
  },
  body: {
    "amount": 100,
    "count": 1,
    "feedback_mobile": "+639173011987"
  }
}
```

**Response Parsing**:
```javascript
// Nested structure: response.data.data.vouchers
const responseData = response.data.data || response.data;
const voucher = responseData.vouchers[0];
```

---

#### 5. formatAPIError()

**Signature**: `function formatAPIError(error)`

**Purpose**: Map HTTP status codes to user-friendly messages

**Status Code Mapping**:
```javascript
400 → "⚠️ System error (missing idempotency key). Contact support."
401 → "⚠️ System error (authentication failed). Contact support."
403 → "❌ Insufficient wallet balance. Please top up."
422 → "❌ Invalid request. Check amount format."
429 → "⚠️ Too many requests. Wait and try again."
5xx → "⚠️ System error ({status}). Contact support."
```

**Error Structure**:
```javascript
{
  status: "error",
  message: "User-friendly message",
  error: {
    status: 403,
    data: { /* API response */ },
    error: "Axios error message"
  }
}
```

---

#### 6. Main Workflow (Command Router)

**Purpose**: Route SMS to appropriate handler

**Flow**:
```javascript
async run({ steps, $ }) {
  const sender = steps.trigger.event.body.sender;
  const smsText = steps.trigger.event.body.sms;
  
  // Try AUTHENTICATE
  result = await handleAuthenticate(sender, smsText, store, $);
  if (result) return exportAndReturn(result);
  
  // Try GENERATE
  result = await handleGenerate(sender, smsText, store, $);
  if (result) return exportAndReturn(result);
  
  // Unknown command - ignore
  return { status: "ignored", message: null };
}
```

**Benefits**:
- ✅ Single entry point for all commands
- ✅ Easy to add new commands (just add handler + routing)
- ✅ Consistent export logic
- ✅ No duplicate code

**Exports** (for next Pipedream step):
```javascript
$.export("status", "success|error|ignored");
$.export("message", "SMS reply text");
$.export("voucher", { code, amount, ... }); // Only on success
$.export("error", { status, data, ... });   // Only on error
```

---

## Data Store Configuration

### Pipedream Data Store: "redeem-x"

**Type**: Key-Value Store  
**Scope**: Workflow-level  
**Persistence**: Permanent (survives workflow updates)

### Storage Format

**Key**: Mobile number (e.g., `"639173011987"`)  
**Value**: Object with token and metadata

```json
{
  "token": "3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de",
  "created_at": "2026-01-24T11:33:07.535Z",
  "mobile": "639173011987"
}
```

### Operations

**Store Token**:
```javascript
await this.redeemxStore.set(sender, {
  token: token,
  created_at: new Date().toISOString(),
  mobile: sender,
});
```

**Retrieve Token**:
```javascript
const tokenData = await this.redeemxStore.get(sender);
// Returns: { token, created_at, mobile } or undefined
```

**Update Token** (rotation):
```javascript
// Just overwrite with new AUTHENTICATE command
await this.redeemxStore.set(sender, { token: newToken, ... });
```

### Security Features

- ✅ **Per-mobile isolation**: Each user has separate token
- ✅ **Token not in code**: Stored separately from workflow
- ✅ **Survives updates**: Workflow code changes don't affect tokens
- ✅ **Easy rotation**: Just send new AUTHENTICATE SMS
- ✅ **Audit trail**: `created_at` timestamp for tracking

---

## Testing Results

### Test Date: 2026-01-24

#### ✅ Test 1: AUTHENTICATE Command

**Input SMS**: `AUTHENTICATE 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de`  
**Sender**: `639173011987`

**Result**: Success

**Data Store Entry**:
```json
{
  "639173011987": {
    "token": "3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de",
    "created_at": "2026-01-24T11:33:07.535Z",
    "mobile": "639173011987"
  }
}
```

**SMS Reply**:
```
✅ API token saved successfully for 639173011987. You can now use GENERATE commands.
```

---

#### ✅ Test 2: GENERATE Command

**Input SMS**: `Generate 100`  
**Sender**: `639173011987`

**Result**: Success

**API Request**:
```json
{
  "method": "POST",
  "url": "https://redeem-x.laravel.cloud/api/v1/vouchers",
  "headers": {
    "Authorization": "Bearer 3|TByBd...",
    "Idempotency-Key": "sms-639173011987-1706083200"
  },
  "body": {
    "amount": 100,
    "count": 1,
    "feedback_mobile": "+639173011987"
  }
}
```

**API Response** (201 Created):
```json
{
  "data": {
    "count": 1,
    "vouchers": [
      {
        "code": "ABC-1234",
        "amount": 100,
        "currency": "PHP",
        "status": "unused",
        "redemption_url": "https://redeem-x.laravel.cloud/disburse"
      }
    ]
  },
  "meta": {
    "timestamp": 1706083200,
    "version": "1.0"
  }
}
```

**SMS Reply**:
```
✅ Voucher ABC-1234 generated (₱100.00). Redeem at: https://redeem-x.laravel.cloud/disburse
```

---

## Deployment to Pipedream

### 1. Create Workflow

1. Go to [Pipedream](https://pipedream.com)
2. Create new workflow
3. Name: "SMS to Voucher Generator v2"

### 2. Add HTTP Trigger

1. Add trigger: **HTTP / Webhook**
2. Copy webhook URL
3. Configure Omni Channel to send to this URL

### 3. Add Data Store

1. Add new step: **Data Stores** → **Create Data Store**
2. Name: `redeemxStore`
3. Store name: `redeem-x`
4. Leave as Key-Value store

### 4. Add Code Step

1. Add step: **Run Node.js Code**
2. Copy entire contents of `docs/pipedream-generate-voucher.js`
3. Paste into code editor
4. In props section, link `redeemxStore` to the Data Store created in step 3

### 5. Configure Environment Variables

```bash
REDEEMX_API_URL=https://redeem-x.laravel.cloud/api/v1
```

### 6. Test Workflow

1. Click "Test" button
2. Send test SMS via Omni Channel
3. Check execution logs
4. Verify Data Store entry (for AUTHENTICATE)
5. Verify voucher created (for GENERATE)

### 7. Deploy

1. Click "Deploy" button
2. Workflow is now live and processing real SMS

---

## Security Best Practices

### 1. Token Management

**Recommended Token Settings**:
- Abilities: `voucher:create` (minimum required)
- Expiration: 90-365 days
- Name: "SMS Service - Mobile {number}"

**Rotation Schedule**:
- Every 90 days: Send new AUTHENTICATE SMS
- Immediately: If token is compromised
- After personnel changes: Revoke old tokens

### 2. IP Whitelisting (Optional)

Configure in redeem-x settings:
```json
{
  "ip_whitelist": ["13.250.187.118"],
  "ip_whitelist_enabled": true
}
```

### 3. Rate Limiting

Built-in limits:
- redeem-x API: 60 requests/minute per token
- Pipedream: 100 executions/day (free tier)

Monitor for abuse:
- Check for repeated failures
- Alert on unusual volume

### 4. Monitoring

**Log these events**:
- ✅ AUTHENTICATE success/failure
- ✅ GENERATE success/failure
- ✅ API errors (403, 429, 500)
- ✅ Unknown commands (potential abuse)

**Alerts**:
- Multiple failed GENERATE attempts
- AUTHENTICATE from new mobile numbers
- API downtime (repeated 500 errors)

---

## Troubleshooting

### "No API token found for your number"

**Cause**: Never ran AUTHENTICATE command  
**Fix**: Send `AUTHENTICATE {token}` first

### "Insufficient wallet balance"

**Cause**: Issuer account has insufficient funds  
**Fix**: Top up wallet in redeem-x dashboard

### "System error (authentication failed)"

**Cause**: Token expired or revoked  
**Fix**: Generate new token and re-authenticate

### "Too many requests"

**Cause**: Rate limit exceeded (60/minute)  
**Fix**: Wait 1 minute and try again

### Token not saving in Data Store

**Cause**: Data Store not linked to workflow  
**Fix**: Check props configuration, ensure `redeemxStore` is linked

### Workflow not triggering

**Cause**: Omni Channel webhook URL incorrect  
**Fix**: Verify webhook URL matches Pipedream trigger URL

---

## Future Enhancements

### Planned Features

1. **BALANCE Command** - Check wallet balance via SMS
2. **HISTORY Command** - List recent vouchers
3. **HELP Command** - Show available commands
4. **Multi-user support** - Different tokens per mobile
5. **Voucher templates** - Save generation settings

### Extension Points

The modular architecture makes these easy to add:

```javascript
// Add new handler function
async function handleBalance(sender, smsText, store, $) {
  const match = smsText.match(/^balance$/i);
  if (!match) return null;
  
  // Implementation
}

// Add to routing in main workflow
result = await handleBalance(sender, smsText, store, $);
if (result) return exportAndReturn(result);
```

---

## Change Log

### Version 2.0.0 (2026-01-24)

**Major Refactor - Modular Architecture**

- ✅ Split 320-line monolith into modular functions
- ✅ Added AUTHENTICATE command for token management
- ✅ Integrated Pipedream Data Stores for token storage
- ✅ Centralized configuration (CONFIG, PATTERNS, MESSAGES)
- ✅ Command routing pattern for extensibility
- ✅ Comprehensive JSDoc documentation
- ✅ Structured logging with prefixes
- ✅ Per-mobile token isolation
- ✅ Consistent error handling across all functions

**Breaking Changes**:
- Removed hardcoded API token
- Requires AUTHENTICATE command before GENERATE

### Version 1.0.0 (2026-01-23)

**Initial Implementation**

- ✅ SMS-triggered voucher generation
- ✅ Hardcoded API token (testing only)
- ✅ GENERATE command parsing
- ✅ Idempotency support
- ✅ Error handling for common scenarios

---

## Support

For issues or questions:

1. **Check Pipedream Logs**: Click "Logs" tab in workflow
2. **Verify Data Store**: Check "redeem-x" store for token entry
3. **Test API Directly**: Use curl to test redeem-x API
4. **Review Error Messages**: All errors have user-friendly messages

**Contact**: Lester Hurtado  
**Repository**: `/Users/rli/PhpstormProjects/redeem-x`  
**Related Docs**: `docs/SECURE_SMS_API_STRATEGY.md` (security plan)
