# PWA Service Worker Cache Troubleshooting

## Symptoms
- Dev site shows **stale UI** after `npm run build` or code changes
- Hard refresh (Cmd+Shift+R) does **not** fix it
- Incognito/private window does **not** fix it
- Clearing site data in DevTools may **not** fix it
- Production (Laravel Cloud) shows correct UI but local `.test` domain is stale

## Root Cause
The PWA service worker (`public/pwa/sw.js`) intercepts all fetch requests and can serve cached responses. Unlike browser HTTP cache, SW cache persists across hard refreshes and incognito windows.

## Quick Fix (Immediate)

### Option 1: Rebuild (recommended)
```bash
npm run build
```
The Vite build plugin automatically stamps `sw.js` with a new timestamp, which forces browsers to install a new SW and purge old caches.

### Option 2: Manual SW unregister
1. Open DevTools → **Application** → **Service Workers**
2. Click **Unregister** next to `sw.js`
3. Hard refresh (Cmd+Shift+R)

### Option 3: Nuclear — clear everything
```bash
rm -rf public/build node_modules/.vite
npm run build
php artisan optimize:clear
```
Then unregister SW in DevTools and hard refresh.

## How Auto-Versioning Works

Every `npm run build` triggers the `swVersionStamp` Vite plugin (`vite.config.ts`) which:

1. Reads `public/pwa/sw.js`
2. Replaces `const SW_BUILD = '...'` with the current ISO timestamp
3. Writes back

Since the SW file content changes, the browser detects it as a new version and:
1. **Installs** the new SW (precaches fresh assets)
2. **Activates** it (deletes all old caches)
3. **Claims** all clients (starts serving immediately)

## Verifying SW Version

Open browser console and look for:
```
[SW] Installing build 2026-03-08T23:50:00.000Z
[SW] Activating build 2026-03-08T23:50:00.000Z, purging old caches
```

Or check in DevTools → Application → Service Workers — the script URL should show the current `sw.js`.

## Caching Strategies

| Path | Strategy | Reason |
|------|----------|--------|
| `/api/*` | Network first | API data must be fresh |
| Navigation | Network only + offline fallback | HTML/Inertia must be fresh |
| `/build/*` | Network first | Vite hashes handle browser caching |
| Icons, fonts, manifest | Cache first | Truly static, rarely change |

## Dev Mode
The service worker is **not registered** during Vite dev mode (`npm run dev`). The `PwaLayout.vue` checks `import.meta.env.DEV` and skips registration. This prevents the SW from intercepting Vite HMR requests.

## Common Pitfalls

### Stale `public/hot` file
If Vite dev server crashes or is killed, a `public/hot` file may be left behind. This tells Laravel to load assets from the (dead) dev server instead of `public/build/`. Fix:
```bash
rm public/hot
```

### SW registered during dev
If you previously ran `npm run build` and visited the site, the SW may still be active even when using `npm run dev`. The dev-mode guard prevents new registrations, but won't unregister an existing SW. Fix: unregister manually in DevTools.
