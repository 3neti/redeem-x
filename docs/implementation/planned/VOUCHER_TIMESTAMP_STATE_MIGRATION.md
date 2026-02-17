# Voucher Timestamp-Based State Migration Plan

## Status
📋 **Planned** - Ready for sprint planning  
🎯 **Priority:** Medium (Architecture improvement, not urgent)  
⏱️ **Estimated:** 2-3 sprints  

## Related Work
- ✅ **Phase 1 Complete:** PWA redeemed voucher status fix (deployed 2026-02-17)
- 📝 **Phase 2:** This plan - Timestamp-based state system migration

## Problem Statement
Current voucher state system uses a hybrid of enum (`state` column) and timestamps (`redeemed_at`, `expires_at`). This creates:
- Inconsistency: State enum not updated on redemption
- No audit trail: Can't track when state changes occurred
- Ambiguity: Multiple sources of truth (enum vs timestamps)

## Proposed Solution: Event-Driven Timestamp State System

### Architecture Philosophy
**States are derived from events (timestamps), not stored directly.**

### Timestamp Schema
```sql
redeemed_at   -- Terminal: voucher was redeemed (immutable)
cancelled_at  -- Terminal: admin cancelled (immutable)
closed_at     -- Terminal: business rule closed (immutable)
locked_at     -- Temporal: admin locked
unlocked_at   -- Temporal: admin unlocked (supersedes locked_at)
expires_at    -- Time-based: expiry date (existing)
starts_at     -- Time-based: scheduled activation (existing)
```

### State Precedence (Computed)
```
1. redeemed_at exists              → 'redeemed'   (terminal)
2. cancelled_at exists             → 'cancelled'  (terminal)
3. closed_at exists                → 'closed'     (terminal)
4. locked_at > unlocked_at         → 'locked'     (reversible)
5. expires_at < now()              → 'expired'    (time-based)
6. starts_at > now()               → 'scheduled'  (future activation)
7. default                         → 'active'
```

### Benefits
✅ **Audit trail:** Every state change has a timestamp  
✅ **Immutable history:** Can't accidentally erase state changes  
✅ **Deterministic:** Clear precedence rules eliminate ambiguity  
✅ **Event-driven:** Fits CQRS/Event Sourcing patterns  
✅ **Simple:** No state machine complexity  
✅ **Easy to add states:** Just add timestamp column  

## Implementation Phases

### Phase 2.1: Database Migration (Sprint 1)
**Goal:** Add timestamp columns, backfill data, keep enum for backward compatibility

```bash
# Create migration
php artisan make:migration add_state_timestamps_to_vouchers
```

**Migration content:**
```php
// Up
$table->timestamp('locked_at')->nullable()->after('state');
$table->timestamp('unlocked_at')->nullable()->after('locked_at');
$table->timestamp('cancelled_at')->nullable()->after('unlocked_at');

// Backfill from existing state enum
DB::table('vouchers')
    ->where('state', 'locked')
    ->update(['locked_at' => DB::raw('updated_at')]);

DB::table('vouchers')
    ->where('state', 'cancelled')
    ->update(['cancelled_at' => DB::raw('updated_at')]);

DB::table('vouchers')
    ->where('state', 'closed')
    ->update(['closed_at' => DB::raw('updated_at')]);
```

### Phase 2.2: Model Updates (Sprint 1-2)
**Goal:** Add computed accessor, update state transition methods

**Files to modify:**
- `packages/voucher/src/Models/Voucher.php`
- `packages/voucher/src/Data/VoucherData.php`

**Add computed accessor:**
```php
// Voucher.php
public function getStateAttribute(): string
{
    // Terminal states
    if ($this->redeemed_at) return 'redeemed';
    if ($this->cancelled_at) return 'cancelled';
    if ($this->closed_at) return 'closed';
    
    // Temporal state
    if ($this->isLocked()) return 'locked';
    
    // Time-based
    if ($this->isExpired()) return 'expired';
    if ($this->isScheduled()) return 'scheduled';
    
    return 'active';
}

public function isLocked(): bool
{
    return $this->locked_at && 
           (!$this->unlocked_at || $this->locked_at > $this->unlocked_at);
}

public function isScheduled(): bool
{
    return $this->starts_at && $this->starts_at->isFuture();
}

public function isTerminalState(): bool
{
    return $this->redeemed_at || $this->cancelled_at || $this->closed_at;
}
```

**Update transition methods:**
```php
public function lock(): void
{
    if ($this->isTerminalState()) {
        throw new \Exception('Cannot lock terminal state voucher');
    }
    $this->update(['locked_at' => now()]);
}

public function unlock(): void
{
    $this->update(['unlocked_at' => now()]);
}

public function cancel(): void
{
    if ($this->isTerminalState()) {
        throw new \Exception('Voucher already in terminal state');
    }
    $this->update(['cancelled_at' => now()]);
}
```

### Phase 2.3: Controller & Filter Updates (Sprint 2)
**Goal:** Update queries to use timestamps instead of enum

**Files to modify:**
- `packages/pwa-ui/src/Http/Controllers/PwaVoucherController.php`

**Update filter queries:**
```php
// Before (enum-based)
'locked' => $query->where('state', 'locked'),

// After (timestamp-based)
'locked' => $query->where(function($q) {
    $q->whereNotNull('locked_at')
      ->where(function($q2) {
          $q2->whereNull('unlocked_at')
             ->orWhereColumn('locked_at', '>', 'unlocked_at');
      });
}),
```

### Phase 2.4: Testing (Sprint 2)
**Goal:** Comprehensive test coverage

**New test files:**
- `packages/voucher/tests/Unit/VoucherStateTest.php`
- `packages/voucher/tests/Feature/VoucherTransitionsTest.php`

**Test scenarios:**
1. State precedence rules (redeemed overrides all)
2. Lock/unlock transitions
3. Terminal state guards (can't lock/cancel redeemed vouchers)
4. Backfill accuracy (enum → timestamp migration)
5. Filter queries (locked, cancelled, etc.)

### Phase 2.5: Validation Period (Sprint 3)
**Goal:** Run both systems in parallel, monitor for discrepancies

**Strategy:**
- Computed state accessor reads from timestamps
- Database `state` enum column still exists (not used)
- Log any mismatches between computed state and enum
- Monitor for 2-4 weeks
- Fix any edge cases discovered

### Phase 2.6: Cleanup (Optional - Future)
**Goal:** Remove deprecated enum column

**Tasks:**
1. Drop `state` column from `vouchers` table
2. Remove `VoucherState` enum class
3. Update documentation
4. Remove mismatch logging

## Migration Commands

```bash
# Phase 2.1: Run migration
php artisan migrate

# Phase 2.4: Run tests
php artisan test --filter VoucherState

# Rollback (if needed)
php artisan migrate:rollback
```

## Rollback Plan

If issues are discovered:

**Option 1: Disable computed accessor**
```php
// Temporarily revert to using raw enum column
// In Voucher.php, comment out getStateAttribute()
```

**Option 2: Database rollback**
```bash
php artisan migrate:rollback
```

**Option 3: Gradual rollback**
- Keep timestamps
- Revert to enum-based queries
- Fix issues
- Re-enable timestamp system

## Success Criteria

✅ All vouchers have correct timestamps backfilled  
✅ State precedence rules work correctly  
✅ Terminal state guards prevent invalid transitions  
✅ Filters return same results (enum vs timestamps)  
✅ No production errors after 2 weeks  
✅ Computed state matches business rules 100%  

## Trade-offs

**Pros:**
- Immutable audit trail
- Deterministic state computation
- No invalid state transitions
- Easy to add new states

**Cons:**
- More database columns (6 timestamps vs 1 enum)
- Query complexity increases
- Need to document precedence rules clearly
- Migration requires careful backfilling

**Decision:** For vouchers, audit trail benefits outweigh extra columns.

## Files to Modify

### Backend
- `database/migrations/YYYY_MM_DD_add_state_timestamps_to_vouchers.php` (new)
- `packages/voucher/src/Models/Voucher.php`
- `packages/voucher/src/Data/VoucherData.php`
- `packages/pwa-ui/src/Http/Controllers/PwaVoucherController.php`

### Tests
- `packages/voucher/tests/Unit/VoucherStateTest.php` (new)
- `packages/voucher/tests/Feature/VoucherTransitionsTest.php` (new)

### Frontend
- No changes needed (already using backend computed status from Phase 1)

## Notes

- Backend `VoucherData::computeStatus()` already checks timestamps (mostly correct)
- Frontend already fixed in Phase 1 (checks `redeemed_at` first)
- `state` enum will be deprecated but kept during validation period
- No breaking changes to API (state still returned as string)
- Migration preserves all existing behavior (backward compatible)

## References

- Implementation plan: This document
- Phase 1 completion: 2026-02-17 (PWA status fix)
- Related commit: ce737036 (Merge fix/pwa-voucher-redeemed-status)
