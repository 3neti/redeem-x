# Merchant Package - TODO

## Current Status
✅ Core functionality implemented and tested (84% test coverage)  
✅ Production-ready with manual verification  
⚠️ 4 tests need fixture improvements

---

## Immediate Next Steps

### 1. Complete Test Fixtures
**Priority**: Medium  
**Effort**: 1-2 hours

Fix the 4 failing tests by improving test setup:

#### VendorAliasServiceTest (2 tests)
- [ ] Seed `reserved_vendor_aliases` table in test setup
- [ ] Or move tests to package test suite with proper TestCase

```php
// In beforeEach or test setup
DB::table('reserved_vendor_aliases')->insert([
    ['alias' => 'ADMIN', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
    ['alias' => 'ROOT', 'reason' => 'System', 'created_at' => now(), 'updated_at' => now()],
    ['alias' => 'GCASH', 'reason' => 'EMI', 'created_at' => now(), 'updated_at' => now()],
]);
```

#### PayableVoucherTest (2 tests)
- [ ] Create Cash entity in `createPayableVoucher()` helper
- [ ] Attach Cash entity to Voucher using proper package method

```php
// In createPayableVoucher() helper
$cash = Cash::create([
    'owner_id' => $owner->id,
    'amount' => $amount,
    'currency' => 'PHP',
]);
$cash->depositFloat($amount);

// Attach to voucher (need to research correct method from voucher package)
$voucher->attachEntity($cash); // or similar
```

---

## Future Enhancements

### 2. Admin UI for Vendor Alias Management
**Priority**: High  
**Effort**: 1 week

Create admin interface for managing vendor aliases:

- [ ] Settings > Vendor Aliases page (list view)
- [ ] Assign alias form (user selector + alias input)
- [ ] Revoke/activate alias actions
- [ ] Reserved aliases management
- [ ] Alias history/audit log

**Routes:**
```php
Route::prefix('settings/vendor-aliases')->group(function () {
    Route::get('/', [VendorAliasController::class, 'index'])->name('vendor-aliases.index');
    Route::post('/', [VendorAliasController::class, 'store'])->name('vendor-aliases.store');
    Route::patch('/{alias}', [VendorAliasController::class, 'update'])->name('vendor-aliases.update');
    Route::delete('/{alias}', [VendorAliasController::class, 'destroy'])->name('vendor-aliases.destroy');
});
```

**UI Components:**
- VendorAliasList.vue (table with search/filter)
- AssignAliasModal.vue (user selector + validation)
- RevokeAliasConfirmation.vue

---

### 3. Merchant Self-Service Portal
**Priority**: Medium  
**Effort**: 2-3 days

Allow merchants to view their own alias and request changes:

- [ ] Merchant dashboard showing current alias
- [ ] Request alias change form (admin approval required)
- [ ] Alias usage statistics (vouchers redeemed, total amount)
- [ ] Download QR code for merchant alias

**Models:**
```php
// AliasChangeRequest model
- user_id
- current_alias_id
- requested_alias
- reason
- status (pending/approved/rejected)
- reviewed_by_user_id
- reviewed_at
```

---

### 4. API Endpoints for B2B Integration
**Priority**: Medium  
**Effort**: 2 days

Expose vendor alias functionality via API for third-party integrations:

- [ ] `GET /api/v1/merchant/alias` - Get authenticated merchant's alias
- [ ] `GET /api/v1/merchant/vouchers` - List vouchers payable to merchant
- [ ] `POST /api/v1/merchant/redeem` - Redeem voucher (with payable validation)
- [ ] `GET /api/v1/merchant/transactions` - Transaction history

**Authentication:** Laravel Sanctum with merchant scope

---

### 5. Bulk Alias Assignment
**Priority**: Low  
**Effort**: 1 day

Support bulk assignment of aliases via CSV import:

- [ ] CSV template (user_email, alias, notes)
- [ ] Import command: `php artisan merchant:import-aliases {file}`
- [ ] Validation and error reporting
- [ ] Dry-run mode for preview

**Format:**
```csv
user_email,alias,notes
vendor1@example.com,VNDR1,Main retail partner
vendor2@example.com,VNDR2,Online marketplace
```

---

### 6. Analytics & Reporting
**Priority**: Low  
**Effort**: 3 days

Add analytics for vendor alias usage:

- [ ] Dashboard widget: Active aliases count
- [ ] Report: Voucher redemption by vendor alias
- [ ] Report: Alias utilization (active vs inactive)
- [ ] Export: Monthly vendor payment summary

**Metrics to track:**
- Total aliases assigned
- Active/revoked ratio
- Vouchers redeemed per alias
- Total amount disbursed per alias
- Average redemption time

---

## Technical Debt

### 7. Package Extraction to Standalone Repo
**Priority**: Low  
**Effort**: 1 day

Extract merchant package to standalone Composer package:

- [ ] Create separate repo: `lbhurtado/merchant`
- [ ] Publish to Packagist
- [ ] Update composer.json in host app to use Packagist version
- [ ] Add GitHub Actions for automated testing
- [ ] Write comprehensive README with installation guide

---

### 8. Documentation
**Priority**: High  
**Effort**: 2 days

- [ ] README.md with feature overview and examples
- [ ] API documentation (if implementing endpoint feature #4)
- [ ] Database schema diagram
- [ ] Architecture decision records (ADRs)
  - Why vendor_alias_id in CashValidationRulesData (not separate field)
  - Why two redemption paths (ProcessRedemption vs PayWithVoucher)
  - Reserved aliases strategy

---

## Nice-to-Haves

### 9. Alias Expiration & Renewal
- [ ] Add `expires_at` to vendor_aliases table
- [ ] Notification system for expiring aliases (30 days, 7 days, expired)
- [ ] Auto-renewal option with admin approval
- [ ] Grace period for expired aliases

### 10. Multi-Tenant Support
- [ ] Support for multiple organizations/tenants
- [ ] Alias uniqueness per tenant (not global)
- [ ] Tenant-specific reserved aliases

### 11. Webhook Notifications
- [ ] Fire webhook when alias assigned
- [ ] Fire webhook when voucher redeemed (include alias info)
- [ ] Configurable webhook URLs per merchant

---

## Known Limitations

1. **No alias transfer**: Once assigned, an alias cannot be transferred to another user (must revoke + reassign)
2. **No alias history**: Soft deletes only track latest state, not full audit trail
3. **Reserved list is static**: No admin UI to add/remove reserved aliases dynamically
4. **Single primary alias**: User can only have one active primary alias at a time

---

## Success Metrics

Track these to measure feature adoption:

- [ ] Number of vendors with assigned aliases
- [ ] Percentage of vouchers using payable restriction
- [ ] Reduction in support tickets for wrong merchant redemptions
- [ ] API usage (if implemented)
- [ ] Time saved in voucher distribution (vs manual coordination)

---

## Questions for Product Team

1. Should merchants be able to choose their own aliases (with admin approval)?
2. What's the alias lifecycle policy (expiration, renewal)?
3. Do we need multi-level aliases (e.g., MAYA-BRANCH1, MAYA-BRANCH2)?
4. Should we support alias hierarchies or grouping?
5. What's the process for revoking an alias (grace period, notification)?

---

## Federation Support (Future)

### Vision
Allow vendor aliases to work across a federation of servers (Maya, LandBank, NetBank, BDO) where the same merchant can operate on multiple deployments.

### Current Architecture Status
✅ **Federation-Ready**
- Service abstraction allows swapping implementations
- Action pattern supports extension
- Config-driven design enables feature toggles
- Package isolation prevents coupling
- No breaking changes needed for federation

### Key Decisions Required Before Implementation

#### 1. Alias Ownership Model
**Question**: How do we handle the same alias across multiple servers?

**Option A: Single Owner (Recommended)**
- `MAYA` alias registered on Maya server is THE canonical owner
- Other servers see it as "federated" (read-only reference)
- Prevents conflicts, clear ownership

**Option B: Multi-Owner**
- `MAYA` on Maya server ≠ `MAYA` on LandBank server
- Same alias, different entities per server
- Requires namespacing (e.g., `MAYA@maya-server`)

**Option C: Hybrid**
- System aliases (MAYA, GCASH, BDO) are single-owner
- Vendor aliases (VNDR1, SHOP2) are multi-owner per server

**Decision**: ___________ (To be decided)

#### 2. Conflict Resolution Strategy
**Question**: What happens when two servers try to register the same alias simultaneously?

**Option A: First-Come-First-Serve**
- Central registry assigns to first request
- Second request gets rejection
- Simple but may frustrate users

**Option B: Priority by Server Type**
- Payment institution servers (Maya, GCash) have priority
- Bank servers (LandBank, BDO) have lower priority
- Requires server tier configuration

**Option C: Manual Resolution**
- Conflicts go to admin queue
- Human decides based on business rules
- Slow but most flexible

**Decision**: ___________ (To be decided)

#### 3. Synchronization Strategy
**Question**: How do servers stay in sync with the registry?

**Option A: Real-Time API Calls**
- Every validation checks central registry
- Always up-to-date
- High latency, registry becomes bottleneck

**Option B: Periodic Sync**
- Background job syncs every N minutes
- Local cache with TTL
- Eventual consistency, better performance

**Option C: Event-Driven (Webhooks)**
- Registry pushes updates to all servers
- Near real-time with good performance
- Requires webhook infrastructure

**Decision**: ___________ (To be decided)

#### 4. Availability Guarantees
**Question**: What happens if the central registry is unavailable?

**Option A: Fail Closed**
- Reject all alias validations
- Secure but affects operations

**Option B: Fail Open**
- Allow local-only validation
- Operations continue, risk of conflicts

**Option C: Degraded Mode**
- Use last known sync state
- Flag as "unverified" in metadata
- Reconcile when registry returns

**Decision**: ___________ (To be decided)

### Implementation Roadmap (When Needed)

#### Phase 1: Database Schema (Non-Breaking)
```sql
ALTER TABLE vendor_aliases ADD COLUMN is_federated BOOLEAN DEFAULT FALSE;
ALTER TABLE vendor_aliases ADD COLUMN federation_server_id VARCHAR(50);
ALTER TABLE vendor_aliases ADD COLUMN federation_registry_id VARCHAR(100);
ALTER TABLE vendor_aliases ADD COLUMN synced_at TIMESTAMP;
```

#### Phase 2: Central Registry Service
Build standalone API:
- `POST /api/aliases/check` - Check availability across federation
- `POST /api/aliases/register` - Register new alias globally
- `POST /api/aliases/sync` - Sync local aliases to registry
- `GET /api/aliases/{alias}` - Get canonical owner info
- `POST /api/webhooks/alias-updated` - Push updates to servers

#### Phase 3: Extend VendorAliasService
```php
class FederatedVendorAliasService extends VendorAliasService
{
    public function isReserved(string $alias): bool
    {
        return parent::isReserved($alias) 
            || $this->checkFederationRegistry($alias);
    }
}
```

#### Phase 4: Config Toggle
```php
// config/merchant.php
'federation' => [
    'enabled' => env('FEDERATION_ENABLED', false),
    'server_id' => env('FEDERATION_SERVER_ID'),
    'registry_url' => env('FEDERATION_REGISTRY_URL'),
],
```

#### Phase 5: Background Sync Jobs
- `SyncAliasesToRegistry` - Push local changes to registry
- `SyncAliasesFromRegistry` - Pull federated aliases
- `ReconcileConflicts` - Handle sync conflicts

### Extension Points (Already Built)
1. **VendorAliasService**: Can be extended with federation logic
2. **AssignVendorAlias Action**: Can register with federation before local save
3. **Config-driven**: Federation can be toggled without code changes
4. **Model scopes**: Can filter local vs federated aliases

### Testing Strategy for Federation
- Unit tests: Mock registry API responses
- Integration tests: Use test registry instance
- E2E tests: Multi-server setup with Docker Compose
- Chaos tests: Registry downtime scenarios

### Monitoring & Observability
- Registry API latency metrics
- Sync lag monitoring (local vs registry)
- Conflict detection alerts
- Cross-server alias usage analytics

---

## Resources

- [WARP.md Implementation Notes](/Users/rli/PhpstormProjects/redeem-x/WARP.md#vendor-alias-registry-system)
- [Test Summary](/tmp/merchant_test_summary.md)
- Manual Testing Notes: All tested via `php artisan tinker` on 2026-01-03

---

**Last Updated**: 2026-01-03  
**Maintainer**: @lbhurtado  
**Status**: ✅ Production-ready, enhancements planned
