# Form-Flow UI/UX Update SOP

**Audience:** AI agents (Warp Oz, Claude Code, Junie)
**Last updated:** 2026-03-12

## Why This SOP Exists

The form-flow package (`3neti/form-flow`) publishes Vue stubs to the host app. The host app's `post-update-cmd` runs `vendor:publish --tag=form-flow-views --force` on **every** `composer update`, overwriting `resources/js/pages/form-flow/core/` with the package's stubs. If the package stubs are outdated, host app customizations get silently wiped out.

## Architecture

```
Host App (redeem-x)                     Package (3neti/form-flow)
─────────────────────                   ─────────────────────────
resources/js/pages/form-flow/core/      ~/PhpstormProjects/packages/form-flow-manager/
  GenericForm.vue  ◄──── publish ────   stubs/resources/js/pages/form-flow/core/
                                          GenericForm.vue

config/form-flow-drivers/               (host-app only, NOT in package)
  voucher-redemption.yaml

composer.json: "3neti/form-flow": "^1.7.2"  ← semver, Packagist
```

**Key points:**
- GenericForm.vue exists in BOTH the host app and the package stubs
- YAML drivers (`config/form-flow-drivers/`) are host-app only — no package sync needed
- Form-handler plugins (kyc, location, selfie, signature, otp) follow the same workflow if their Vue stubs need updating — they live at `~/PhpstormProjects/packages/form-handler-{name}/`

## Update Workflow

### Step 1: Edit in host app

Make changes to the Vue component and/or YAML driver:

```bash
# Vue component (requires package sync)
resources/js/pages/form-flow/core/GenericForm.vue

# YAML driver (host-app only, no sync needed)
config/form-flow-drivers/voucher-redemption.yaml
```

### Step 2: Build & verify

```bash
npm run build
# Must exit 0 with no errors
```

### Step 3: Sync to package source

**Only needed if you edited GenericForm.vue** (skip for YAML-only changes):

```bash
cp resources/js/pages/form-flow/core/GenericForm.vue \
   ~/PhpstormProjects/packages/form-flow-manager/stubs/resources/js/pages/form-flow/core/GenericForm.vue

# Verify files are identical
diff resources/js/pages/form-flow/core/GenericForm.vue \
     ~/PhpstormProjects/packages/form-flow-manager/stubs/resources/js/pages/form-flow/core/GenericForm.vue
# Must produce no output
```

### Step 4: Commit + tag + push the package

```bash
# Check current version
git -C ~/PhpstormProjects/packages/form-flow-manager tag --sort=-v:refname | head -1
# e.g., v1.7.2

# Commit
git -C ~/PhpstormProjects/packages/form-flow-manager add -A
git -C ~/PhpstormProjects/packages/form-flow-manager commit -m "Description of changes

Co-Authored-By: Oz <oz-agent@warp.dev>"

# Tag (patch bump)
git -C ~/PhpstormProjects/packages/form-flow-manager tag v1.7.3

# Push
git -C ~/PhpstormProjects/packages/form-flow-manager push origin main --tags
```

### Step 5: Update host app composer.json

```bash
# Update version constraint
# "3neti/form-flow": "^1.7.2" → "^1.7.3"
```

### Step 6: Composer update

```bash
composer update 3neti/form-flow
```

⚠️ **This triggers `post-update-cmd` which also runs `vendor:publish --tag=pwa-components --force`.**
See [PWA_UI_STUB_SYNC_SOP.md](PWA_UI_STUB_SYNC_SOP.md) — if pwa-ui stubs are out of date, PWA files will be overwritten.

### Step 7: Verify no regressions

```bash
git diff --stat
```

**Expected:** Only your intentional changes (composer.json, composer.lock, GenericForm.vue, YAML driver if edited, sw.js timestamp).

**If unexpected PWA files appear in the diff:**
```bash
git checkout HEAD -- resources/js/pages/pwa/ resources/js/layouts/PwaLayout.vue \
    resources/js/components/pwa/ resources/js/composables/pwa/
```

### Step 8: Commit + push host app

Ask user for confirmation before git operations.

```bash
git add -A
git commit -m "Description of changes

Co-Authored-By: Oz <oz-agent@warp.dev>"
git push origin main
```

## Form-Handler Plugin Updates

The same workflow applies to form-handler plugins, substituting:

| Plugin | Package Source | Stubs Directory |
|--------|---------------|-----------------|
| kyc | `~/PhpstormProjects/packages/form-handler-kyc/` | `stubs/resources/js/pages/form-flow/kyc/` |
| location | `~/PhpstormProjects/packages/form-handler-location/` | `stubs/resources/js/pages/form-flow/location/` |
| selfie | `~/PhpstormProjects/packages/form-handler-selfie/` | `stubs/resources/js/pages/form-flow/selfie/` |
| signature | `~/PhpstormProjects/packages/form-handler-signature/` | `stubs/resources/js/pages/form-flow/signature/` |
| otp | `~/PhpstormProjects/packages/form-handler-otp/` | `stubs/resources/js/pages/form-flow/otp/` |

These plugins are NOT in `post-update-cmd` — their stubs are only published via install commands or manual `vendor:publish`. However, you should still keep stubs in sync to avoid drift.

## Common Mistakes

1. **Forgetting to sync GenericForm.vue to package** → Next `composer update` overwrites your changes
2. **Forgetting to tag the package** → `composer update` won't pull the new version
3. **Not checking git diff after `composer update`** → PWA files may have been silently overwritten
4. **Editing YAML driver and trying to sync it** → YAML drivers are host-app only, no sync needed
