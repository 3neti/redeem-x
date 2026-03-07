
## OG Meta Scaling Plan
**File:** `OG_META_SCALING_PLAN.md`  
**Status:** 📋 Planned  
**Priority:** Low (act when voucher count > 10K)  
**Effort:** 1 sprint  

Storage and Cloudflare Browser Rendering cost analysis for OG card PNGs at scale. Phased migration: S3 cache disk → GD fallback → lazy generation → garbage collection.

## Voucher Timestamp State Migration
**File:** `VOUCHER_TIMESTAMP_STATE_MIGRATION.md`  
**Status:** 📋 Planned  
**Priority:** Medium  
**Effort:** 2-3 sprints  
**Phase 1:** ✅ Complete (PWA status fix deployed 2026-02-17)  
**Phase 2:** Event-driven timestamp-based state system  

Migrate voucher states from enum to timestamp-based system for immutable audit trail and deterministic state computation.
