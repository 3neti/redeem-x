# Pricing System - Remaining TODO Items

## ✅ Completed (Steps 1-9)
- [x] Step 1: Database Setup (migrations, spatie/laravel-permission)
- [x] Step 2: Configuration (config/redeem.php pricelist)
- [x] Step 3: Models (InstructionItem, PriceHistory, Charges, User relationships)
- [x] Step 4: Repository (InstructionItemRepository)
- [x] Step 5: Service Layer (InstructionCostEvaluator)
- [x] Step 6: DTOs and Actions (ChargeBreakdownData, CalculateChargeAction)
- [x] Step 7: Seeders (InstructionItemSeeder, RolePermissionSeeder)
- [x] Step 8: Controllers (Pricing, Billing, API)
- [x] Step 9: Routes (admin, user, API)
- [x] Step 10 (Partial): Frontend composable (useChargeBreakdown.ts)
- [x] Testing: 60 tests passing (18 new controller tests, all backend logic verified)

## 🔄 In Progress

### Step 10: Frontend Implementation
**Priority**: High  
**Estimate**: 4-6 hours

- [ ] Update VoucherInstructionsForm.vue to integrate live pricing
  - [ ] Import and use useChargeBreakdown composable
  - [ ] Display charge breakdown in sidebar or collapsible section
  - [ ] Show total cost prominently
  - [ ] Update on field changes with debouncing
  
**Files to modify**:
- `resources/js/components/VoucherInstructionsForm.vue`

**Acceptance Criteria**:
- Live pricing updates as user fills out form
- Breakdown shows each charged item with price
- Total displayed clearly
- Performance: No lag from real-time calculations

### Step 11: Admin UI Pages
**Priority**: High  
**Estimate**: 6-8 hours

#### Pricing Management Pages

- [ ] Create `admin/pricing/Index.vue`
  - [ ] Table listing all instruction items
  - [ ] Columns: name, type, current price, last updated
  - [ ] Edit button for each item
  - [ ] Search/filter by type
  
- [ ] Create `admin/pricing/Edit.vue`
  - [ ] Form to update price (displays in ₱)
  - [ ] Reason field (required)
  - [ ] Label and description fields (optional metadata)
  - [ ] Price history table showing past changes
  - [ ] History shows: date, old price, new price, changed by, reason
  
**Files to create**:
- `resources/js/pages/admin/pricing/Index.vue`
- `resources/js/pages/admin/pricing/Edit.vue`

**Acceptance Criteria**:
- Admin can view all pricing items
- Admin can update prices with mandatory reason
- Price history visible on edit page
- Cannot set negative prices
- Cannot update without reason

#### Billing Pages

- [ ] Create `admin/billing/Index.vue`
  - [ ] Table listing all VoucherGenerationCharges across all users
  - [ ] Columns: user, date, campaign, voucher count, total charge
  - [ ] Filters: user dropdown, date range picker
  - [ ] Pagination (20 per page)
  - [ ] Click row to view details
  
- [ ] Create `admin/billing/Show.vue`
  - [ ] Detailed charge information
  - [ ] User info, campaign name
  - [ ] List of voucher codes generated
  - [ ] Charge breakdown table
  - [ ] Instructions snapshot (collapsible JSON viewer)
  - [ ] Total charge and per-voucher cost
  
- [ ] Create `billing/Index.vue` (user-facing)
  - [ ] Table showing user's own charges only
  - [ ] Same columns as admin except no user column
  - [ ] Date range filter
  - [ ] Summary cards: total vouchers, total charges, current month
  
**Files to create**:
- `resources/js/pages/admin/billing/Index.vue`
- `resources/js/pages/admin/billing/Show.vue`
- `resources/js/pages/billing/Index.vue`

**Acceptance Criteria**:
- Admin sees all users' charges
- Admin can filter by user and date range
- Users see only their own charges
- Summary statistics calculate correctly
- Charge breakdown is clear and accurate

## Step 12: Integration
**Priority**: High  
**Estimate**: 3-4 hours

- [ ] Modify VoucherGenerationController to record charges
  - [ ] After successful voucher generation:
    - [ ] Calculate charges using CalculateChargeAction
    - [ ] Create VoucherGenerationCharge record
    - [ ] Link vouchers to user via user_voucher pivot
    - [ ] Store instructions snapshot
    - [ ] Store charge breakdown JSON
  
- [ ] Update voucher generation success page
  - [ ] Display charge breakdown
  - [ ] Show total cost
  - [ ] Link to billing history

**Files to modify**:
- `app/Http/Controllers/VoucherGenerationController.php`
- `resources/js/pages/vouchers/generate/Success.vue` (or equivalent)

**Acceptance Criteria**:
- Charges recorded on every voucher generation
- Charge breakdown matches real-time preview
- Vouchers linked to generating user
- Instructions snapshot stored for auditability
- Success page shows costs clearly

## Step 13: Testing & Documentation
**Priority**: High  
**Estimate**: 2-3 hours

### Manual Testing Checklist

- [ ] **Setup**
  - [ ] Create super-admin user: `php artisan tinker`
    ```php
    $admin = User::factory()->create(['email' => 'admin@redeem.test']);
    $admin->assignRole('super-admin');
    ```
  
- [ ] **Pricing Management**
  - [ ] Navigate to /admin/pricing
  - [ ] View all pricing items
  - [ ] Click edit on any item
  - [ ] Try to update price without reason (should fail)
  - [ ] Update price with reason (should succeed)
  - [ ] Verify price history shows new entry
  - [ ] Verify old and new prices are correct
  - [ ] Verify changed by shows admin name
  
- [ ] **Voucher Generation with Pricing**
  - [ ] Login as regular user
  - [ ] Go to generate vouchers page
  - [ ] Fill out form and watch real-time pricing update
  - [ ] Enable email feedback → price should increase
  - [ ] Add signature field → price should increase
  - [ ] Verify total matches expected calculation
  - [ ] Generate vouchers
  - [ ] Verify success page shows charge breakdown
  
- [ ] **User Billing**
  - [ ] Navigate to /billing
  - [ ] Verify charge appears in table
  - [ ] Verify summary shows correct statistics
  - [ ] Filter by date range
  - [ ] Verify only own charges visible
  
- [ ] **Admin Billing**
  - [ ] Login as super-admin
  - [ ] Navigate to /admin/billing
  - [ ] Verify all users' charges visible
  - [ ] Filter by user
  - [ ] Filter by date range
  - [ ] Click on a charge to view details
  - [ ] Verify voucher codes, breakdown, snapshot all present
  
- [ ] **API Testing**
  - [ ] Use Postman/Insomnia or `curl` to test API:
    ```bash
    curl -X POST http://localhost:8000/api/v1/calculate-charges \
      -H "Authorization: Bearer {token}" \
      -H "Content-Type: application/json" \
      -d '{"cash":{"amount":100},"feedback":{"email":"test@example.com"}}'
    ```
  - [ ] Verify response matches expected format
  - [ ] Test with various instruction combinations
  - [ ] Verify excluded fields (count, mask, ttl) don't affect price

### Documentation

- [ ] Update README.md with:
  - [ ] Link to docs/IMPLEMENTATION-SUMMARY.md
  - [ ] Brief description of pricing system
  - [ ] Admin setup instructions
  
- [ ] Create CHANGELOG entry
  - [ ] Version number
  - [ ] Features added
  - [ ] Breaking changes (if any)

### Performance Testing

- [ ] Test with 100 vouchers generation (should handle well)
- [ ] Test API response time (<200ms for charge calculation)
- [ ] Test admin billing page with 1000+ charges (pagination performance)

## Future Enhancements (Post-MVP)

### Phase 2: Business Features
- [ ] Volume discounts (e.g., 10% off for 100+ vouchers)
- [ ] Customer tiers (Bronze/Silver/Gold with different pricing)
- [ ] Promotional pricing (temporary discounts)
- [ ] Package deals (bundles of features at discount)

### Phase 3: Reporting
- [ ] Monthly billing reports (PDF export)
- [ ] Revenue analytics dashboard
- [ ] Top customers report
- [ ] Pricing trend analysis

### Phase 4: Payment Integration
- [ ] Stripe/PayPal integration
- [ ] Automatic billing on generation
- [ ] Invoice generation
- [ ] Payment history

### Phase 5: Advanced Features
- [ ] Credit system (pre-purchase credits)
- [ ] Subscription plans
- [ ] API usage tracking and billing
- [ ] Webhooks for billing events

## Notes

### Known Limitations
- Currency: Only PHP supported currently (easy to extend)
- No retroactive price changes (by design for auditability)
- Price history cannot be deleted (by design for compliance)

### Deployment Checklist
- [ ] Run migrations on production: `php artisan migrate`
- [ ] Seed instruction items: `php artisan db:seed --class=InstructionItemSeeder`
- [ ] Seed roles/permissions: `php artisan db:seed --class=RolePermissionSeeder`
- [ ] Create super-admin user manually
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Optimize: `php artisan optimize`

### Maintenance
- Pricing updates should be infrequent and documented
- Always provide clear reason for price changes
- Review price history quarterly
- Monitor total charges for anomalies
