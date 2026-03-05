# OG Meta

Auto-generate Open Graph images for link previews (WhatsApp, iMessage, Facebook, Viber, etc.). Renders status-aware card images with optional splash HTML content.

Two rendering modes:
- **screenshot** — Cloudflare Browser Rendering via `spatie/laravel-screenshot`. Full HTML/CSS support including Tailwind, images, and custom fonts.
- **gd** — PHP GD library. No external service needed but limited to plain text and base64 image overlays.

Controlled by `og-meta.renderer` config key.

## Architecture

```
Request → InjectOgMeta middleware → OgMetaService → Resolver → OgMetaData
                                         ↓
                                   OgImageRenderer
                                    ├── screenshot → Blade template → Cloudflare API → PNG
                                    └── gd → PHP GD functions → PNG
                                         ↓
                                   Storage (public disk) → cache hit on next request
```

**Middleware flow**: The `og-meta` middleware calls a resolver for the current route, gets `OgMetaData`, and shares `$og` view data (title, description, image URL) for the `<meta>` tags in `<head>`.

**Image flow**: When a crawler fetches the `og:image` URL (`GET /og/{resolverKey}/{identifier}`), `OgImageController` calls the same resolver, renders the card via `OgImageRenderer`, caches the PNG to disk, and serves it with `Cache-Control` headers.

### Key Files

| File | Purpose |
|------|---------|
| `src/Data/OgMetaData.php` | DTO — all card fields and OG tag values |
| `src/Contracts/OgMetaResolver.php` | Interface — resolvers implement this |
| `src/Resolvers/ModelOgResolver.php` | Abstract base — handles model lookup boilerplate |
| `src/Services/OgImageRenderer.php` | Renders images (screenshot or GD mode) |
| `src/Services/OgMetaService.php` | Orchestrates resolvers and rendering |
| `src/Http/Controllers/OgImageController.php` | Serves images at `GET /og/{resolverKey}/{identifier}` |
| `src/Http/Middleware/InjectOgMeta.php` | Shares `$og` view data for meta tags |
| `resources/views/card.blade.php` | HTML template for screenshot mode |
| `resources/views/tags.blade.php` | `<meta>` tag partial for `<head>` |

## Card Blade Template — Design Reference

**Location**: `resources/views/card.blade.php`

This is the HTML template rendered to a PNG via Cloudflare Browser Rendering in screenshot mode. Designers and AI agents should understand this layout when customizing the card appearance.

### Canvas

- **Dimensions**: 1200×630px (OG standard — required by Facebook, WhatsApp, iMessage)
- **Format**: Full HTML document with `<html>`, `<head>`, `<body>`
- **Styling**: Tailwind CSS via CDN, Inter font (400/600/700/900) via Google Fonts

### Layout Structure

Centered, full-width layout — the voucher code is the dominant element:

```
┌──────────────────────────────────────────────────────┐
│  48px padding — status-colored background             │
│  ┌────────────────────────────────────────────────┐  │
│  │                                                │  │
│  │  REDEEM-X (small, gray, top-left)              │  │
│  │                                                │  │
│  │            ║   R2PQ   ║                        │  │
│  │               ₱50.00                           │  │
│  │       [REDEEMABLE]  [CASH]                     │  │
│  │                                                │  │
│  │                                                │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

- **Code**: 7xl font-black, centered, flanked by `║` parallel line decorators (gray-200, font-thin)
- **Amount**: 4xl font-semibold, gray-700, centered below code
- **Badges**: Pill-shaped (`rounded-full`), side by side. Type badge (gray-100 bg, gray-500 text) + Payee badge (gray-700 bg, white text)
- **App name**: Absolute positioned top-left, sm uppercase tracking-wider, gray-300

### Template Variables

| Variable | Type | Description |
|----------|------|-------------|
| `$bgColor` | string | CSS `rgb()` for outer background (from status config) |
| `$badgeColor` | string | CSS `rgb()` for status badge (from status config) |
| `$appName` | string | App name shown top-left |
| `$headline` | string | Large centered text (e.g. voucher code) |
| `$subtitle` | ?string | Secondary centered text (e.g. amount) |
| `$status` | string | Drives background color (not rendered as badge) |
|| `$payeeBadge` | ?string | Payee badge label (e.g. vendor alias, mobile, "CASH") |
| `$typeBadge` | ?string | Type badge label (e.g. "redeemable", "payable") |

### Status Colors

Colors are defined as RGB arrays in `config/og-meta.php` and converted to CSS `rgb()` strings by `OgImageRenderer::statusCssColor()`:

| Status | Background | Badge |
|--------|-----------|-------|
| `active` | `rgb(220, 252, 231)` (green-100) | `rgb(22, 163, 74)` (green-600) |
| `redeemed` | `rgb(229, 231, 235)` (gray-200) | `rgb(107, 114, 128)` (gray-500) |
| `expired` | `rgb(254, 226, 226)` (red-100) | `rgb(220, 38, 38)` (red-600) |
| `pending` | `rgb(254, 243, 199)` (yellow-100) | `rgb(202, 138, 4)` (yellow-600) |

Unknown statuses fall back to neutral gray.

### Customization Rules

1. **Keep 1200×630**: Social platforms expect this aspect ratio. Deviating causes cropping.
2. **Delete cached PNGs** after template changes — images are cached on disk.
3. **Fonts**: Change the `@import` URL in `<style>` for different Google Fonts.
4. **Test locally**: Generate via `curl -o test.png http://app.test/og/{key}/{id}` and view the PNG.

## OgMetaData DTO

`LBHurtado\OgMeta\Data\OgMetaData` — all fields that resolvers populate:

| Field | Type | Used For |
|-------|------|----------|
| `title` | `string` | `og:title` meta tag |
| `description` | `string` | `og:description` meta tag |
| `status` | `string` | Badge color, background color, cache filename |
| `headline` | `string` | Large text on card |
| `subtitle` | `?string` | Secondary text on card |
| `tagline` | `?string` | Bottom text on card |
| `url` | `?string` | `og:url` meta tag |
| `imageUrl` | `?string` | `og:image` — auto-set by `OgMetaService` if null |
| `cacheKey` | `?string` | Image cache filename segment (e.g. voucher code) |
| `httpMaxAge` | `?int` | `Cache-Control` max-age in seconds (null = infinite) |
| `message` | `?string` | Text below the status badge (GD mode) |
| `overlayImage` | `?string` | Base64-encoded image for GD mode (right side) |
| `splashHtml` | `?string` | Raw HTML for screenshot mode (reserved for future use) |
| `typeBadge` | `?string` | Secondary badge label (e.g. "redeemable", "payable") |
|| `payeeBadge` | `?string` | Payee badge label (e.g. vendor alias, mobile, "CASH") |

**Renderer-specific fields**:
- `overlayImage` is only used in GD mode — the renderer composites it onto the canvas
- `typeBadge` renders as a gray pill badge in screenshot mode
- `payeeBadge` renders as a dark pill badge next to the type badge in screenshot mode
- A resolver can populate fields for both rendering modes

## Creating a Resolver

### 1. Create the class

Extend `ModelOgResolver` for model-backed resolvers:

```php
<?php

namespace App\OgResolvers;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\OgMeta\Data\OgMetaData;
use LBHurtado\OgMeta\Resolvers\ModelOgResolver;

class InvoiceOgResolver extends ModelOgResolver
{
    protected string $model = \App\Models\Invoice::class;
    protected string $findBy = 'number';      // Column to look up by
    protected string $queryParam = 'invoice';  // ?invoice=INV-001
    protected bool $uppercase = false;

    protected function mapToOgData(Model $model): OgMetaData
    {
        return new OgMetaData(
            title: "Invoice {$model->number}",
            description: "Amount due: {$model->formatted_total}",
            status: $model->is_paid ? 'redeemed' : 'active',
            headline: $model->number,
            subtitle: $model->formatted_total,
            tagline: $model->is_paid ? 'Paid' : 'Payment pending',
            cacheKey: $model->number,
            httpMaxAge: $model->is_paid ? 604800 : 300,
        );
    }
}
```

For non-model resolvers, implement `OgMetaResolver` directly:

```php
use LBHurtado\OgMeta\Contracts\OgMetaResolver;

class StaticOgResolver implements OgMetaResolver
{
    public function resolve(Request $request): ?OgMetaData { ... }
    public function resolveForImage(string $identifier): ?OgMetaData { ... }
}
```

### 2. Register the resolver

In `config/og-meta.php`:

```php
'resolvers' => [
    'invoice' => \App\OgResolvers\InvoiceOgResolver::class,
],
```

The key (`'invoice'`) becomes the URL segment: `GET /og/invoice/{identifier}`.

### 3. Add middleware to routes

```php
Route::get('/invoices', [InvoiceController::class, 'show'])
    ->middleware('og-meta:invoice');
```

### 4. Include meta tags

In your page's `<head>`:

```blade
@include('og-meta::tags')
```

This renders `og:title`, `og:description`, `og:image`, `og:url`, and Twitter Card tags when `$og` is available.

## Configuration

All keys in `config/og-meta.php`:

```php
return [
    // Rendering mode: 'screenshot' or 'gd'
    'renderer' => 'gd',

    // Canvas dimensions (OG standard)
    'dimensions' => ['width' => 1200, 'height' => 630],

    // Custom font paths (null = bundled Inter)
    'fonts' => ['bold' => null, 'regular' => null],

    // App name on card (null = config('app.name'))
    'app_name' => null,

    // Cache storage
    'cache_disk' => 'public',
    'cache_prefix' => 'og',

    // Resolver registry: key => class
    'resolvers' => [],

    // Status colors: [bg => [r,g,b], badge => [r,g,b]]
    'statuses' => [
        'active'   => ['bg' => [220, 252, 231], 'badge' => [22, 163, 74]],
        'redeemed' => ['bg' => [229, 231, 235], 'badge' => [107, 114, 128]],
        'expired'  => ['bg' => [254, 226, 226], 'badge' => [220, 38, 38]],
        'pending'  => ['bg' => [254, 243, 199], 'badge' => [202, 138, 4]],
    ],

    // Fallback for unknown statuses
    'fallback_status' => ['bg' => [243, 244, 246], 'badge' => [107, 114, 128]],
];
```

Publish the config to your host app:

```bash
php artisan vendor:publish --tag=og-meta-config
```

## Caching

- **Location**: `{cache_disk}://{cache_prefix}/{resolverKey}/{cacheKey}-{status}.png`
  - Example: `storage/app/public/og/disburse/R2PQ-active.png`
- **Freshness**: `httpMaxAge` on `OgMetaData` controls TTL. `null` = cached forever.
- **Auto-cleanup**: When status changes (e.g. active → redeemed), the renderer deletes the old status PNG before generating the new one.
- **Force regeneration**: Delete the cached PNG file. Next request regenerates it.
- **HTTP caching**: `OgImageController` sets `Cache-Control: public, max-age={httpMaxAge}` on responses.
