# Case Sensitivity Standardization for Vue Pages

## Problem

**macOS uses a case-insensitive filesystem by default, while Linux (production) uses a case-sensitive filesystem.**

This causes a critical production issue:
- Locally (macOS): `Vouchers/Index.vue` and `vouchers/Index.vue` are treated as the same file
- Production (Linux): They are treated as different files
- Controllers reference lowercase paths (e.g., `vouchers/Index`)
- Git tracks capitalized paths (e.g., `Vouchers/Index.vue`)
- **Result**: Production can't find the files → "Page not found" errors

## Standard Convention

**ALL directory names under `resources/js/pages/` MUST be lowercase.**

### ✅ Correct Examples
```
resources/js/pages/vouchers/Index.vue
resources/js/pages/settings/campaigns/Index.vue
resources/js/pages/vendor-aliases/Index.vue
resources/js/pages/form-flow/location/LocationCapture.vue
```

### ❌ Incorrect Examples
```
resources/js/pages/Vouchers/Index.vue
resources/js/pages/Settings/Campaigns/Index.vue
resources/js/pages/VendorAliases/Index.vue
```

### Exception: Vue Component Files
Component files themselves should use PascalCase:
```
resources/js/pages/vouchers/Index.vue  ✅ (directory lowercase, file PascalCase)
resources/js/pages/vouchers/Show.vue   ✅
```

## How to Fix

### For Existing Repositories (Case-Insensitive Filesystem)

Git cannot rename from `Vouchers` to `vouchers` directly on case-insensitive filesystems. Use a two-step rename:

```bash
# Step 1: Rename to temporary name
git mv resources/js/pages/Vouchers resources/js/pages/vouchers-temp

# Step 2: Rename to final lowercase name
git mv resources/js/pages/vouchers-temp resources/js/pages/vouchers

# Commit and push
git commit -m "Fix: Rename Vouchers to vouchers for case-sensitive filesystems"
git push origin main
```

### Affected Directories (2026-01-08)

The following directories needed to be renamed:
- `Vouchers/` → `vouchers/`
- `Balances/` → `balances/`
- `Contacts/` → `contacts/`
- `Transactions/` → `transactions/`
- `Pay/` → `pay/`
- `Wallet/` → `wallet/`
- `Redeem/` → `redeem/` (if capitalized)
- `Campaigns/` → `campaigns/` ✅ (already fixed)

## Prevention

### For New Pages

When creating new pages, always use lowercase directory names:

```bash
# ✅ Correct
mkdir resources/js/pages/my-feature
touch resources/js/pages/my-feature/Index.vue

# ❌ Wrong
mkdir resources/js/pages/MyFeature
```

### Pre-commit Hook (Optional)

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash
# Check for capitalized directories in resources/js/pages/
capitalized=$(git diff --cached --name-only | grep -E 'resources/js/pages/[A-Z]' | grep -v '\.vue$')
if [ -n "$capitalized" ]; then
    echo "ERROR: Capitalized directory names found in resources/js/pages/"
    echo "$capitalized"
    echo "Please use lowercase directory names. See docs/CASE_SENSITIVITY_FIX.md"
    exit 1
fi
```

## Related Issues

- Production Error: `Page not found: ./pages/vouchers/Index.vue`
- Production Error: `Page not found: ./pages/settings/campaigns/Index.vue`
- Root Cause: Git tracks `Vouchers/` but controllers reference `vouchers/`
- Environment: macOS (case-insensitive) vs Linux production (case-sensitive)

## References

- macOS filesystem: HFS+ and APFS are case-insensitive by default
- Linux filesystem: ext4, xfs are case-sensitive
- Git behavior: Respects the OS filesystem (case-insensitive on macOS, case-sensitive on Linux)
- Laravel/Inertia: Uses exact path matching from controller
