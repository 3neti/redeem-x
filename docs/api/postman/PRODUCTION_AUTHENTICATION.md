# Postman Authentication Guide: Local vs Production

## Quick Summary

| Environment | Authentication Method | Setup |
|-------------|----------------------|-------|
| **Local** (`redeem-x.test`) | Session cookies via Chrome Interceptor | Install extension, enable, sync cookies |
| **Production** (`redeem-x.laravel.cloud`) | Sanctum API Token | Generate token via API, paste in environment |

## Why Chrome Interceptor Doesn't Work for Production

**Chrome Interceptor** syncs cookies from your **browser's current domain** to Postman. This works locally because:
- You log into `http://redeem-x.test` in Chrome
- Interceptor syncs `redeem-x-session` cookie to Postman
- Postman sends cookie with requests to `http://redeem-x.test`

**This CANNOT work for production** because:
- You'd need to log into `https://redeem-x.laravel.cloud` in Chrome
- Session cookies are `httpOnly` and domain-specific
- Postman can't access cookies from different domains
- Even if you're logged in, the session is separate

---

## ✅ Solution: Use Sanctum API Tokens

Laravel Sanctum supports **two authentication methods**:
1. **Session-based** (for SPAs) - Used locally with Interceptor
2. **Token-based** (for APIs) - **Use this for production**

---

## 🚀 Step-by-Step Setup for Production

### Step 1: Import Production Environment

1. Open Postman
2. Go to **Environments** tab (left sidebar)
3. Click **Import** button
4. Select file: `docs/postman/redeem-x-production.postman_environment.json`
5. Imported environment: **"Redeem-X Production (Laravel Cloud)"**

### Step 2: Generate API Token

**Option A: Via Production UI** (Recommended)
1. Log into https://redeem-x.laravel.cloud in your browser
2. Navigate to **Settings > API Tokens**
3. Click **"Create New Token"**
4. Name: `Postman - Utility Bill Testing`
5. Abilities: Select all or `*` (full access)
6. Click **Generate**
7. **Copy the token immediately** (shown only once)

**Option B: Via Artisan Command** (SSH required)
```bash
# SSH into production server
php artisan tinker

# Generate token for your user
$user = \App\Models\User::where('email', 'your@email.com')->first();
$token = $user->createToken('Postman Production')->plainTextToken;
echo $token;
```

**Option C: Via API Request** (if you have existing auth)
```bash
# If you already have a valid session in production
curl -X POST https://redeem-x.laravel.cloud/api/v1/auth/tokens \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Postman Production",
    "abilities": ["*"]
  }'
```

### Step 3: Configure Postman Environment

1. In Postman, select environment: **"Redeem-X Production (Laravel Cloud)"** (dropdown in top right)
2. Click **eye icon** (⦿) next to environment dropdown
3. Edit environment
4. Find `access_token` variable
5. Paste your generated token in **Current Value**
6. Click **Save**

### Step 4: Test Authentication

1. Open collection: `redeem-x-utility-bill-payment.postman_collection.json`
2. **Skip "00 - Setup (Run First)" folder** - Not needed with tokens!
3. Go directly to **"01 - Generate Payable Voucher"**
4. Run: **"Generate Payable Voucher with Invoice Data"**
5. ✅ Should return `201 Created` with voucher data

---

## 🧹 Cleanup Recommendations

Your current collection has setup specific to **local development**. Here's what to clean up:

### Remove Unnecessary Setup Folder

The **"00 - Setup (Run First)"** folder is only needed for **Chrome Interceptor** (local dev). For production with tokens, it's:
- **Unnecessary** - Token auth doesn't need CSRF
- **Misleading** - Makes users think they need Interceptor
- **Will fail** - Production won't respond to `/sanctum/csrf-cookie` for API tokens

**Recommendation**: Create two versions of your collection:
1. `redeem-x-utility-bill-payment-LOCAL.postman_collection.json` - Keep setup folder
2. `redeem-x-utility-bill-payment-PRODUCTION.postman_collection.json` - Remove setup folder

### Update Collection Description

Current description mentions Chrome Interceptor extensively. For production, update to:

```markdown
## Authentication Setup

### Local Development (`redeem-x.test`)
Uses Chrome Interceptor for session-based auth.
See "00 - Setup (Run First)" folder for instructions.

### Production (`redeem-x.laravel.cloud`)
Uses Sanctum API Tokens.
1. Generate token via Settings > API Tokens in production
2. Paste token in `access_token` environment variable
3. Skip "00 - Setup" folder - start with "01 - Generate Payable Voucher"
```

---

## 🔒 Security Best Practices

### Token Storage
- **Never commit tokens to Git** - Use environment variables
- **Rotate tokens regularly** - Regenerate monthly
- **Use descriptive names** - "Postman - John Doe - Jan 2026"
- **Revoke unused tokens** - Clean up in Settings > API Tokens

### Token Scopes
For production, consider limiting abilities:
```json
{
  "abilities": [
    "vouchers:create",
    "vouchers:read",
    "vouchers:update"
  ]
}
```

### Environment Variables
Mark `access_token` as **secret type** in Postman to prevent accidental exposure in screenshots/shares.

---

## 🐛 Troubleshooting

### Error: `401 Unauthenticated`
**Causes:**
1. Token not set in environment variable
2. Token expired or revoked
3. Wrong environment selected in Postman
4. Token not copied correctly (leading/trailing spaces)

**Fix:**
```bash
# Verify token is set
echo {{access_token}}  # Should output token, not empty

# Check environment
# Ensure "Redeem-X Production" is selected in dropdown

# Test token directly
curl https://redeem-x.laravel.cloud/api/v1/vouchers \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Error: `419 CSRF Token Mismatch`
This error means you're still using **session-based auth** instead of **token-based auth**.

**Fix:**
- Ensure `Authorization: Bearer {{access_token}}` header is present
- Check collection-level auth settings (should be Bearer token)
- Your collection already has this configured correctly at line 9-17

### Token Not Working After Generation
**Causes:**
1. Token generated for wrong user
2. User account disabled/deleted
3. Database connection issue

**Fix:**
```bash
# Verify token in database
php artisan tinker
\Laravel\Sanctum\PersonalAccessToken::where('token', hash('sha256', 'your_token'))->first();
```

---

## 📝 Testing Checklist

- [ ] Import `redeem-x-production.postman_environment.json`
- [ ] Generate API token via production UI
- [ ] Paste token in `access_token` environment variable
- [ ] Select "Redeem-X Production" environment
- [ ] Run "Generate Payable Voucher with Invoice Data"
- [ ] Verify `201 Created` response
- [ ] Run "Generate QR Code for Printing"
- [ ] Run "Check Payment Status"
- [ ] All tests pass ✅

---

## 📚 Related Documentation

- Laravel Sanctum: https://laravel.com/docs/11.x/sanctum#api-token-authentication
- Postman Environments: https://learning.postman.com/docs/sending-requests/variables/
- Postman Bearer Token Auth: https://learning.postman.com/docs/sending-requests/authorization/
