# Postman Variables: Collection vs Environment

## Understanding Variable Scopes

Postman has **three variable scopes** that follow a hierarchy:

```
Environment Variables (highest priority if set)
  ↓
Collection Variables (set by test scripts)
  ↓
Global Variables (lowest priority)
```

## How Your Collection Works

### Variables Used

| Variable | Scope | Set By | Used For |
|----------|-------|--------|----------|
| `base_url` | **Environment** | Manual (in environment file) | API endpoint base |
| `access_token` | **Environment** | Manual (paste token) | Authentication |
| `voucher_code` | **Collection** | Test script after voucher generation | QR/status requests |
| `contact_id` | **Environment** | (Reserved for future use) | Contact API |
| `token_id` | **Environment** | (Reserved for future use) | Token management |

### Key Insight

Your collection uses **`pm.collectionVariables.set('voucher_code', code)`** in test scripts:
- ✅ **Works across ALL environments** (local and production)
- ✅ **Automatically populated** after "Generate Payable Voucher" request succeeds
- ✅ **Persists for entire collection run**

## Why Your Tests Work Locally But Not Production

### Scenario Analysis

**If tests fail ONLY in production:**

| Symptom | Cause | Fix |
|---------|-------|-----|
| First request (Generate Voucher) succeeds, subsequent requests fail | `base_url` or `access_token` mismatch | Verify environment selected in dropdown |
| All requests fail with 401 | `access_token` not set or expired | Regenerate token, paste in production environment |
| QR/status requests fail with 404 | `voucher_code` not being set | Check test script ran successfully (201 response) |

### Debug Checklist

1. **Verify environment selected**
   ```
   Top right dropdown → Should say "Redeem-X Production (Laravel Cloud)"
   ```

2. **Verify `base_url` is correct**
   ```
   Click eye icon (⦿) → Check base_url = https://redeem-x.laravel.cloud
   ```

3. **Verify `access_token` is set**
   ```
   Click eye icon (⦿) → Check access_token has value (should be secret type)
   ```

4. **Verify collection variables are being set**
   ```
   Run "Generate Payable Voucher" → Check Console tab
   Should see: "✓ Payable voucher created" and "Code: BILL-XXX-XXX"
   ```

5. **Check collection variables after generation**
   ```
   Click eye icon (⦿) → Switch to "Collection Variables" tab
   Should see: voucher_code = "BILL-XXX-XXX"
   ```

## Collection vs Environment Variables

### When Test Scripts Run

```javascript
// In "Generate Payable Voucher with Invoice Data" request
pm.test("Voucher created successfully", function () {
    pm.response.to.have.status(201);
    var jsonData = pm.response.json();
    
    // Extract voucher code from response
    const code = jsonData.data.vouchers[0].code;
    
    // ⭐ THIS SETS COLLECTION VARIABLE (works across all environments)
    pm.collectionVariables.set('voucher_code', code);
    
    // ❌ If it used this, it would ONLY work in current environment
    // pm.environment.set('voucher_code', code);
});
```

### In Subsequent Requests

```
Request URL: {{base_url}}/api/v1/vouchers/{{voucher_code}}/qr
                    ↑                              ↑
            Environment Variable          Collection Variable
            (set manually)                (set by test script)
```

## Your Environments Compared

### Local Environment (`redeem-x.postman_environment.json`)

```json
{
  "name": "Redeem-X Local Development",
  "values": [
    {"key": "base_url", "value": "http://redeem-x.test"},
    {"key": "access_token", "value": ""},  // ← Auto-filled by Chrome Interceptor
    {"key": "voucher_code", "value": ""},  // ← Reserved but unused (collection var used)
    {"key": "contact_id", "value": ""},
    {"key": "token_id", "value": ""}
  ]
}
```

### Production Environment (`redeem-x-production.postman_environment.json`)

```json
{
  "name": "Redeem-X Production (Laravel Cloud)",
  "values": [
    {"key": "base_url", "value": "https://redeem-x.laravel.cloud"},
    {"key": "access_token", "value": ""},  // ← MUST paste API token here
    {"key": "voucher_code", "value": ""},  // ← Reserved but unused (collection var used)
    {"key": "contact_id", "value": ""},
    {"key": "token_id", "value": ""}
  ]
}
```

## Common Mistakes

### ❌ Mistake #1: Thinking `voucher_code` Environment Variable is Used
**Reality**: The collection sets `voucher_code` as a **collection variable**, not environment variable.
**Impact**: None - this is correct behavior and works across environments.

### ❌ Mistake #2: Not Switching Environments
**Problem**: Run production tests while "Redeem-X Local Development" is selected.
**Fix**: Switch dropdown to "Redeem-X Production (Laravel Cloud)"

### ❌ Mistake #3: Forgetting to Set `access_token`
**Problem**: Production environment has empty `access_token` after import.
**Fix**: Edit environment → Paste API token in `access_token` Current Value

### ❌ Mistake #4: Running Tests Before Token Is Set
**Problem**: Run collection with empty `access_token` → All requests fail with 401.
**Fix**: Set token FIRST, then run collection.

## Testing Strategy

### Running Full Collection

To run the **entire collection** and have all tests pass:

**Local (Chrome Interceptor):**
```
1. Enable Chrome Interceptor
2. Select "Redeem-X Local Development" environment
3. Run "00 - Setup (Run First)" → Get CSRF Token
4. Run entire collection → All tests pass ✅
```

**Production (API Token):**
```
1. Generate API token in production UI
2. Select "Redeem-X Production (Laravel Cloud)" environment
3. Edit environment → Paste token in access_token
4. SKIP "00 - Setup (Run First)" folder
5. Run "01 - Generate Payable Voucher" → onwards
6. All tests pass ✅
```

### Using Collection Runner

**To run multiple requests automatically:**

1. Click "Collections" tab (left sidebar)
2. Find "Redeem-X API - Utility Bill Payment"
3. Click "..." → "Run collection"
4. **IMPORTANT**: Deselect "00 - Setup" folder if using production
5. Click "Run Redeem-X API"
6. Watch tests execute in sequence

## Variable Resolution Order

When Postman sees `{{voucher_code}}`:

1. Check **environment variable** `voucher_code` → Empty
2. Check **collection variable** `voucher_code` → Found! (set by test script)
3. Use collection variable value

When Postman sees `{{base_url}}`:

1. Check **environment variable** `base_url` → Found! (http://redeem-x.test or https://redeem-x.laravel.cloud)
2. Use environment variable value

## Syncing Environments with Workspace

If you're using a **shared Postman workspace**:

1. Changes to environment files in Git **do NOT auto-sync** to Postman workspace
2. You must **re-import** updated environment files manually
3. Or edit environments directly in Postman UI (syncs to cloud workspace)

**Recommendation**:
- **Git**: Source of truth for environment structure
- **Postman Cloud**: Source of truth for actual token values
- After updating Git environment files, re-import to Postman

## Summary

✅ **Your collection is correctly designed** - uses collection variables for dynamic data

✅ **Both environments have identical structure** - base_url, access_token, voucher_code, contact_id, token_id

✅ **The only difference needed** - base_url value and how access_token is populated:
- Local: `http://redeem-x.test` + Chrome Interceptor for token
- Production: `https://redeem-x.laravel.cloud` + Manual API token

✅ **No additional variables needed** - collection variables handle dynamic data automatically
