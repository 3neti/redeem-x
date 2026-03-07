# OG Meta Scaling Plan

**Status:** 📋 Planned
**Priority:** Low (act when voucher count approaches 10K+)
**Effort:** 1 sprint

## Problem

OG card PNGs are stored on the local `public` disk. At scale this becomes a storage concern, and Cloudflare Browser Rendering has cost implications for bulk generation.

## Current Behaviour

- Each voucher gets **1 cached PNG** at a time (`og/{resolver}/{code}-{status}.png`)
- `cleanStaleImages()` deletes the old status variant when a new one is generated
- `ClearOgMetaCache` pipeline stage deletes PNGs on redemption
- 2 landing cards (`landing-disburse`, `landing-pay`) cached indefinitely
- Active vouchers: 10 min TTL; redeemed/expired: 7-day TTL
- Renderer: `screenshot` mode → Cloudflare Browser Rendering REST API

## Storage Projections

Typical OG card PNG: **15-40 KB** (text on solid background, 1200×630px).

| Voucher Count | Files on Disk | Estimated Storage |
|---------------|---------------|-------------------|
| 1,000         | ~1,000        | 15-40 MB          |
| 10,000        | ~10,000       | 150-400 MB        |
| 100,000       | ~100,000      | 1.5-4 GB          |
| 1,000,000     | ~1,000,000    | 15-40 GB          |

Each voucher has at most 1 file at a time (stale cleanup). Landing cards add ~60 KB total.

## Cloudflare Browser Rendering Cost

**Pricing** (as of Aug 2025): $0.09/browser hour. Workers Paid plan includes 10 free hours/month (~7,200 renders at 5s each). Rate limit: 10 req/s (600/min).

| Scenario                    | Browser Time | Cost (after 10hr free) |
|-----------------------------|--------------|------------------------|
| Normal trickle (100/day)    | ~8 min/day   | **$0**                 |
| 7,200 renders/month         | 10 hours     | **$0** (free tier)     |
| 100K bulk regen             | ~139 hours   | **~$11.61**            |
| 1M bulk regen               | ~1,389 hours | **~$124.10**           |

Images are cached, so 1M renders only occur on full cache clear or template change. Normal operation stays within the free tier.

## Proposed Changes

### Phase 1: S3 Cache Disk (when >10K vouchers)

Move `og-meta.cache_disk` from `public` to an S3-backed Flysystem disk.

1. Add S3 disk config in `config/filesystems.php` (`og-cache` disk)
2. Update `config/og-meta.php`: `'cache_disk' => env('OG_META_CACHE_DISK', 'public')`
3. Update `OgImageRenderer` to use `Storage::disk(config('og-meta.cache_disk'))` (already does)
4. Update `OgMetaService::ensureImageUrl()` to return S3 URLs instead of `asset()` paths
5. Set `OG_META_CACHE_DISK=s3-og` in production `.env`
6. Add CloudFront or Cloudflare CDN in front of S3 bucket for fast delivery

**Estimated S3 cost at 1M vouchers:**
- Storage: ~30 GB × $0.023/GB = ~$0.69/month
- GET requests (serving images): 1M × $0.0004/1K = ~$0.40/month
- Total: **< $2/month** (negligible)

### Phase 2: GD Renderer Fallback (cost optimisation)

Switch to `og-meta.renderer = gd` to eliminate Cloudflare costs entirely.

1. Already implemented — just change `OG_META_RENDERER=gd` in `.env`
2. Trade-off: slightly less visual fidelity (no Tailwind/HTML rendering)
3. Consider: use `screenshot` for first render, `gd` for cache-miss re-renders

### Phase 3: Lazy Generation (when >100K vouchers)

Only render OG images on first social-media crawler request, not on voucher creation.

1. Add `og-meta.lazy_generation` config flag
2. `ensureImageUrl()` returns a dynamic endpoint URL instead of a static PNG URL
3. Dynamic endpoint: checks cache → returns cached PNG or renders on-the-fly
4. Prevents bulk generation on deploy/template change
5. Rate-limit the render endpoint to prevent abuse

### Phase 4: Garbage Collection (when >100K vouchers)

Periodic cleanup of PNGs for expired/deleted vouchers.

1. Create `og:gc` Artisan command
2. Query expired vouchers older than 30 days
3. Delete their cached PNGs from disk
4. Schedule via `schedule:run` (weekly)

## Decision Points

| Trigger                | Action          |
|------------------------|-----------------|
| >10K vouchers          | Phase 1 (S3)    |
| Cloudflare bill > $10  | Phase 2 (GD)    |
| >100K vouchers         | Phase 3 (Lazy)  |
| >100K expired vouchers | Phase 4 (GC)    |

## Rollback

- `OG_META_CACHE_DISK=public` reverts to local storage
- `OG_META_RENDERER=gd` eliminates Cloudflare dependency
- Both are env-var toggles, zero code changes needed
