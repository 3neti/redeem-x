# OG Meta Development Guide (Host App)

This guide covers the redeem-x-specific setup for the `og-meta` package.

**Full package reference**: [`monorepo-packages/og-meta/README.md`](../../../monorepo-packages/og-meta/README.md) — architecture, card template design, DTO fields, resolver creation, configuration, and caching.

## Environment Setup

Add these to `.env`:

```bash
# Rendering mode: 'screenshot' (Cloudflare) or 'gd' (PHP GD fallback)
OG_META_RENDERER=screenshot

# Required for screenshot mode
CLOUDFLARE_API_TOKEN=your_token_here
CLOUDFLARE_ACCOUNT_ID=your_account_id
LARAVEL_SCREENSHOT_DRIVER=cloudflare
```

**When to use each mode**:
- `screenshot` — Production and staging. Renders full HTML/CSS/images via Cloudflare Browser Rendering. Requires Cloudflare credentials.
- `gd` — Fallback for environments without Cloudflare access. Plain text and base64 image overlays only.

## Host App Config

`config/og-meta.php`:

```php
return [
    'renderer' => env('OG_META_RENDERER', 'screenshot'),

    'resolvers' => [
        'disburse' => \App\OgResolvers\VoucherOgResolver::class,
    ],
];
```

The `'disburse'` key means:
- Image URL: `GET /og/disburse/{CODE}`
- Middleware parameter: `og-meta:disburse`

## VoucherOgResolver — Working Example

**File**: `app/OgResolvers/VoucherOgResolver.php`

This resolver maps voucher data to OG card fields:

### Status Resolution

```
isRedeemed()              → 'redeemed'
isExpired()               → 'expired'
starts_at in future       → 'pending'
otherwise                 → 'active'
```

### Field Mapping

| OgMetaData field | Source |
|-----------------|--------|
| `title` | `rider->message` or default per status ("Click to redeem", "This voucher has been redeemed", etc.) |
| `description` | `"{Type} voucher — {amount}"` (e.g. "Redeemable voucher — ₱50.00") |
| `headline` | Voucher code (e.g. `R2PQ`) |
| `subtitle` | Formatted amount (e.g. `₱50.00`) |
|| `typeBadge` | Voucher type value ("redeemable", "payable", "settlement") |
|| `payeeBadge` | Payee: `cash.validation.payable` → `cash.validation.mobile` → `"CASH"` |
| `httpMaxAge` | 600s for active/pending, 604800s (7 days) for redeemed/expired |
| `cacheKey` | Voucher code |

Fields not used: `tagline`, `message`, `splashHtml`, `overlayImage` (set to null).

### Card Layout

The card is a centered, code-dominant design:
- `║ CODE ║` — voucher code as the largest element, flanked by parallel line decorators
- Amount centered below
- Two pill badges: type (gray) and payee (dark gray)

## Testing OG Images Locally

### Generate and view an image

```bash
# Clear cached image for a specific voucher
rm -f storage/app/public/og/disburse/R2PQ-*.png

# Generate via curl
curl -o /tmp/og_test.png http://redeem-x.test/og/disburse/R2PQ

# Open the image
open /tmp/og_test.png
```

### Verify cache hit

```bash
# Second request should be fast (no Cloudflare call)
curl -s -o /dev/null -w "Time: %{time_total}s\n" http://redeem-x.test/og/disburse/R2PQ
```

First request: ~6-8s (Cloudflare cold start). Cached: ~1-2s.

### Clear all cached OG images

```bash
rm -rf storage/app/public/og/
```

### Important

`.test` domains are unreachable by social media crawlers (WhatsApp, Facebook, iMessage). To test real link previews, use the production URL: `https://redeem-x.laravel.cloud/og/disburse/CODE`.

## Adding a New Resolver

1. Create `app/OgResolvers/YourResolver.php` extending `ModelOgResolver`
2. Register in `config/og-meta.php`:
   ```php
   'resolvers' => [
       'disburse' => \App\OgResolvers\VoucherOgResolver::class,
       'pay'      => \App\OgResolvers\PaymentOgResolver::class, // new
   ],
   ```
3. Add middleware to the route:
   ```php
   Route::get('/pay', [PayController::class, 'show'])
       ->middleware('og-meta:pay');
   ```
4. Include `@include('og-meta::tags')` in the page's `<head>`
5. Test: `curl -o /tmp/test.png http://redeem-x.test/og/pay/IDENTIFIER`

See the package README for the full resolver creation guide with code examples.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| First request takes ~8s | Normal — Cloudflare cold start. Subsequent requests serve from cache. |
| Image not updating after code change | Delete cached PNG: `rm storage/app/public/og/disburse/CODE-*.png` |
| Image not updating after status change | Should auto-clean. If not, delete manually and check `cleanStaleImages()`. |
| Cloudflare API error | Verify `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ACCOUNT_ID` in `.env`. Token needs `Account > Browser Rendering > Edit` permission. |
| Need to work without Cloudflare | Set `OG_META_RENDERER=gd` in `.env`. No external service needed. |
| Social preview not showing | Crawlers can't reach `.test` domains. Deploy to production or use a tunnel. |
| Splash HTML not rendering | Ensure `rider->splash` contains valid HTML. Check it uses absolute image URLs. |
