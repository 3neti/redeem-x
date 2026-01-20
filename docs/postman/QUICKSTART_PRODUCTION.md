# Quick Start: Testing Production API with Postman

## 🎯 Goal
Test your Redeem-X API on production (`redeem-x.laravel.cloud`) using Postman.

## ⚡ 3-Minute Setup

### 1. Generate API Token (90 seconds)
```
1. Open browser → https://redeem-x.laravel.cloud
2. Log in with your account
3. Click Settings (left sidebar) → API Tokens
4. Click "Create New Token"
5. Name: "Postman Testing"
6. Abilities: "*" (full access)
7. Click "Generate"
8. COPY THE TOKEN (shown only once!)
```

### 2. Import Environment (30 seconds)
```
1. Open Postman Desktop
2. Environments tab (left sidebar)
3. Click "Import"
4. Select: docs/postman/redeem-x-production.postman_environment.json
5. Done!
```

### 3. Configure Token (30 seconds)
```
1. Select environment dropdown (top right)
2. Choose: "Redeem-X Production (Laravel Cloud)"
3. Click eye icon (⦿)
4. Edit environment
5. Paste token in `access_token` → Current Value
6. Save
```

### 4. Test (30 seconds)
```
1. Open collection: redeem-x-utility-bill-payment
2. SKIP "00 - Setup" folder (not needed!)
3. Go to: "01 - Generate Payable Voucher"
4. Run: "Generate Payable Voucher with Invoice Data"
5. ✅ Success = 201 Created
```

---

## ❌ Common Mistakes

### Mistake #1: Using Chrome Interceptor
**Problem**: Interceptor only works for LOCAL development (`redeem-x.test`)
**Solution**: Use API tokens for production (see above)

### Mistake #2: Running "Get CSRF Token" Request
**Problem**: That's for session-based auth (local only)
**Solution**: Skip "00 - Setup" folder entirely when using production

### Mistake #3: Wrong Environment Selected
**Problem**: Still using "Redeem-X Local Development" environment
**Solution**: Switch to "Redeem-X Production (Laravel Cloud)" in dropdown

### Mistake #4: Token Not Pasted
**Problem**: `access_token` variable is empty
**Solution**: Edit environment and paste token in "Current Value" field

---

## 🆘 Troubleshooting One-Liners

| Error | Fix |
|-------|-----|
| `401 Unauthenticated` | Token not set or expired. Regenerate token and paste in environment. |
| `404 Not Found` | Wrong `base_url`. Should be `https://redeem-x.laravel.cloud` |
| `419 CSRF Token Mismatch` | Using session auth instead of token. Check Bearer token is set. |
| `500 Server Error` | Production issue. Check Laravel logs on server. |

---

## 🔍 Quick Verification

**Is your token working?**
```bash
curl https://redeem-x.laravel.cloud/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

Expected response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Your Name",
    "email": "your@email.com"
  }
}
```

---

## 📚 Full Documentation

See `PRODUCTION_AUTHENTICATION.md` for:
- Detailed explanations
- Security best practices
- Advanced troubleshooting
- Token scopes and permissions
