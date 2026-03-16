# OG Meta Route Guard — Preventing Redirect Regressions

**Last updated:** 2026-03-16
**Related incident:** Divisible vouchers smart routing broke OG meta tags

## The Rule

> **Any controller action on a route with `og-meta` middleware MUST render an Inertia page for non-Inertia requests.** If the controller redirects (302) before `Inertia::render()`, OG meta tags are lost.

## How OG Meta Rendering Works

The OG meta system has a strict execution order:

1. **Middleware** (`og-meta:disburse`) runs BEFORE the controller — resolves OG data from the voucher and calls `view()->share('og', $data->toViewData())`
2. **Controller** renders `Inertia::render('page/Name')` — this triggers Laravel to render `app.blade.php`
3. **Blade template** (`app.blade.php`) includes `@include('og-meta::tags')` in `<head>`, which reads the shared `$og` data
4. **Full HTML response** with `<meta property="og:..." />` tags is sent to the client

If step 2 returns a **redirect** instead of a rendered page, step 3 never executes — the 302 response has no HTML body, so no OG tags.

## The X-Inertia Guard Pattern

Routes with `og-meta` middleware serve two audiences:

- **OG crawlers and initial page loads** (no `X-Inertia` header) — need full HTML with OG tags
- **SPA navigations** (have `X-Inertia` header) — can receive redirects, JSON, etc.

The guard pattern separates these:

```php
public function start(): Response|RedirectResponse
{
    $code = request()->query('code');

    // Only validate/redirect for Inertia (SPA) requests.
    // Non-Inertia requests (crawlers, initial loads) must render the page
    // so that og-meta middleware tags are included in <head>.
    if ($code && request()->header('X-Inertia')) {
        // ... validation, smart routing, redirects
    }

    // Non-Inertia requests always reach here → full HTML with OG tags
    return Inertia::render('disburse/Start', [
        'initial_code' => old('code', $code),
    ]);
}
```

## Routes That Use This Pattern

| Route | Middleware | Controller | Guard Location |
|-------|-----------|------------|----------------|
| `GET /disburse` | `og-meta:disburse` | `DisburseController::start()` | Line ~68 |
| `GET /pay` | `og-meta:pay` | `PayController::show()` | Check if applicable |

Check `routes/disburse.php` and `routes/pay.php` for the middleware declarations.

## What Happened (2026-03-16 Incident)

The divisible vouchers feature added smart routing to `DisburseController::start()`:
- Redeemed divisible vouchers → redirect to `/withdraw`
- Redeemed non-divisible vouchers → redirect with error
- Valid unredeemed vouchers → redirect to form flow

The change removed the `X-Inertia` guard:

```php
// BEFORE (correct)
if ($code && request()->header('X-Inertia')) {

// AFTER (broken)
if ($code) {
```

This caused ALL requests with `?code=` to redirect before rendering HTML — including OG crawlers. Result: WhatsApp/Facebook/iMessage link previews showed no voucher-specific OG data.

## Checklist for Future Route Changes

When modifying a controller action on a route with `og-meta` middleware:

- [ ] Does the route have `->middleware('og-meta:...')`? Check the route file.
- [ ] Does the controller action have any early `return redirect(...)` paths?
- [ ] Are those redirect paths guarded by `request()->header('X-Inertia')`?
- [ ] Does a non-Inertia `GET` request (e.g., `curl`) still return full HTML with `<meta property="og:...">` tags?

**Quick verification:**
```bash
curl -s 'http://redeem-x.test/disburse?code=XXXX' | grep 'og:'
```

## Testing Smart Routing with Inertia Headers

When testing controller logic that requires `X-Inertia` header:

```php
beforeEach(function () {
    $this->withoutVite();
    // Disable Inertia middleware to avoid 409 version mismatch.
    // The controller only checks the raw X-Inertia header, not the middleware.
    $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
});

test('smart routing works for Inertia requests', function () {
    // ... setup ...
    $response = $this->get('/disburse?code='.$code, ['X-Inertia' => 'true']);
    $response->assertRedirect('/expected-path');
});
```

**Why `withoutMiddleware`?** Sending `X-Inertia: true` without a matching `X-Inertia-Version` header triggers Inertia's 409 version mismatch response. Disabling the middleware avoids this while still letting the controller read the raw header.

## Key Files

- `app/Http/Controllers/Disburse/DisburseController.php` — Primary route with guard
- `routes/disburse.php` — `og-meta:disburse` middleware declaration
- `monorepo-packages/og-meta/src/Http/Middleware/InjectOgMeta.php` — Middleware that resolves OG data
- `resources/views/app.blade.php` — `@include('og-meta::tags')` in `<head>`
- `app/OgResolvers/VoucherOgResolver.php` — Resolver that maps voucher → OG data
- `config/og-meta.php` — Resolver registration
- `docs/guides/ai-development/OG_META_DEVELOPMENT_GUIDE.md` — Full OG meta dev guide
