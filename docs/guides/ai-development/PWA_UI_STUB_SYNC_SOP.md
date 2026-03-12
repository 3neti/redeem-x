# PWA-UI Stub Sync SOP

**Audience:** AI agents (Warp Oz, Claude Code, Junie)
**Last updated:** 2026-03-12

## Why This SOP Exists

The `3neti/pwa-ui` package is a monorepo `@dev` package symlinked from `monorepo-packages/pwa-ui/`. The host app's `post-update-cmd` runs `vendor:publish --tag=pwa-components --force` on **every** `composer update` — even when updating completely unrelated packages. This copies package stubs over host app files, silently reverting any customizations not synced back to the package.

**Real incident (2026-03-12):** Running `composer update 3neti/form-flow` triggered `pwa-components` publish, overwriting Portal.vue and wiping out the commit version indicator feature.

## The Rule

> **After editing ANY PWA-published file in the host app, you MUST sync it back to `monorepo-packages/pwa-ui/resources/js/` in the same commit.**

If you don't, the next `composer update` (for any package) will silently revert your changes.

## Architecture

```
monorepo-packages/pwa-ui/resources/js/     Host App (redeem-x)
──────────────────────────────────────     ─────────────────────
layouts/PwaLayout.vue         ──force──►   resources/js/layouts/PwaLayout.vue
components/PwaBottomNav.vue   ──force──►   resources/js/components/pwa/PwaBottomNav.vue
pages/pwa/Portal.vue          ──force──►   resources/js/pages/pwa/Portal.vue
pages/pwa/Settings.vue        ──force──►   resources/js/pages/pwa/Settings.vue
pages/pwa/Vouchers/Generate.vue ─force─►   resources/js/pages/pwa/Vouchers/Generate.vue
composables/                  ──force──►   resources/js/composables/pwa/
```

**Note the `components` path difference:** Package `components/X.vue` → Host `components/pwa/X.vue`

The package is symlinked: `vendor/3neti/pwa-ui` → `../../monorepo-packages/pwa-ui/`

## When Overwrites Happen

Any of these commands trigger `post-update-cmd` → `vendor:publish --tag=pwa-components --force`:

- `composer update` (any package, even unrelated)
- `composer update 3neti/form-flow` (unrelated, but still triggers post-update-cmd)
- `composer install` (on fresh clone or lockfile change)
- Manual: `php artisan vendor:publish --tag=pwa-components --force`

## File Mapping (Host → Package)

When syncing, use these exact paths:

```bash
# Layouts
cp resources/js/layouts/PwaLayout.vue \
   monorepo-packages/pwa-ui/resources/js/layouts/PwaLayout.vue

# Components (note: host has /pwa/ subdirectory, package does not)
cp resources/js/components/pwa/PwaBottomNav.vue \
   monorepo-packages/pwa-ui/resources/js/components/PwaBottomNav.vue

# Pages
cp resources/js/pages/pwa/Portal.vue \
   monorepo-packages/pwa-ui/resources/js/pages/pwa/Portal.vue

cp resources/js/pages/pwa/Settings.vue \
   monorepo-packages/pwa-ui/resources/js/pages/pwa/Settings.vue

cp resources/js/pages/pwa/Vouchers/Generate.vue \
   monorepo-packages/pwa-ui/resources/js/pages/pwa/Vouchers/Generate.vue

# Composables (if any)
# cp resources/js/composables/pwa/X.ts \
#    monorepo-packages/pwa-ui/resources/js/composables/X.ts
```

## Quick Sync (All Files)

Copy-paste this block to sync all known PWA files at once:

```bash
cp resources/js/layouts/PwaLayout.vue monorepo-packages/pwa-ui/resources/js/layouts/PwaLayout.vue
cp resources/js/components/pwa/PwaBottomNav.vue monorepo-packages/pwa-ui/resources/js/components/PwaBottomNav.vue
cp resources/js/pages/pwa/Portal.vue monorepo-packages/pwa-ui/resources/js/pages/pwa/Portal.vue
cp resources/js/pages/pwa/Settings.vue monorepo-packages/pwa-ui/resources/js/pages/pwa/Settings.vue
cp resources/js/pages/pwa/Vouchers/Generate.vue monorepo-packages/pwa-ui/resources/js/pages/pwa/Vouchers/Generate.vue
```

## Recovery: If Files Were Already Overwritten

```bash
# 1. Restore host app files from git
git checkout HEAD -- resources/js/pages/pwa/ resources/js/layouts/PwaLayout.vue \
    resources/js/components/pwa/PwaBottomNav.vue resources/js/composables/pwa/

# 2. Sync restored files back to package (so it doesn't happen again)
# Run the Quick Sync commands above

# 3. Verify git diff only shows intentional changes
git diff --stat
```

## Why Not Remove `--force`?

The `--force` flag in `post-update-cmd` is needed because:
- On fresh project setup, stubs must be published to create the initial files
- When the package adds NEW files, they need to overwrite the target directory
- Without `--force`, `vendor:publish` skips files that already exist

The tradeoff: we accept the overwrite risk and mitigate it by keeping stubs in sync.

## Checklist for AI Agents

Before committing after ANY PWA file edit:

- [ ] Did I edit files under `resources/js/pages/pwa/`, `resources/js/layouts/`, `resources/js/components/pwa/`, or `resources/js/composables/pwa/`?
- [ ] If yes: Did I sync each edited file back to `monorepo-packages/pwa-ui/resources/js/`?
- [ ] Does `git diff --stat` show changes in BOTH the host file AND the corresponding monorepo file?
