# Pricing System - Manual Testing Guide

## Prerequisites

1. **Database Setup** (if not already done):
   ```bash
   php artisan migrate
   php artisan db:seed --class=InstructionItemSeeder
   php artisan db:seed --class=RolePermissionSeeder
   ```

2. **Create Admin User**:
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   ```
   This creates: `admin@redeem.test` with super-admin role

3. **Start Development Server**:
   ```bash
   composer dev
   ```
   This starts the web server, queue worker, log viewer, and Vite

## Test Plan

### 1. Admin Login
- **URL**: `http://localhost:8000/dev-login/admin@redeem.test`
- **Expected**: Logged in as Admin User
- **Verify**: Check top-right corner shows "Admin User"

### 2. Pricing Management

#### 2.1 View Pricing Items
- **URL**: `http://localhost:8000/admin/pricing`
- **Expected**:
  - See pricing items grouped by type (cash, feedback, inputs, rider)
  - Each item shows: name, index (code), current price, last updated
  - "Edit" button for each item
- **Verify**:
  - 20 pricing items displayed
  - Prices formatted as ₱20.00, ₱1.00, etc.
  - Items grouped by type

#### 2.2 Edit Pricing - Validation
- **Action**: Click "Edit" on any item (e.g., "Cash Amount")
- **Test**: Try to save without entering a reason
- **Expected**: Validation error "The reason field is required"
- **Test**: Try to enter negative price
- **Expected**: Validation prevents submission

#### 2.3 Edit Pricing - Success
- **Action**: Edit "Email" pricing item
- **Steps**:
  1. Change price from ₱1.00 to ₱1.50
  2. Enter reason: "Increased email delivery costs"
  3. Optional: Update label and description
  4. Click "Save Changes"
- **Expected**:
  - Redirected to pricing index
  - Success message shown
  - Price updated to ₱1.50
- **Verify Price History**:
  - Go back to edit page
  - See history entry showing: ₱1.00 → ₱1.50
  - Shows your name as changer
  - Shows the reason you entered

### 3. Voucher Generation with Live Pricing

#### 3.1 Login as Regular User
- **Action**: Logout and login as regular user (create one if needed)
- **OR** use: `http://localhost:8000/dev-login/your-test-user@example.com`

#### 3.2 Generate Vouchers Form
- **URL**: `http://localhost:8000/vouchers/generate`
- **Test Live Pricing**:
  1. Fill in Amount: `100`
  2. Watch "Pricing Breakdown" section
  3. Should see "Base Fee: ₱20.00" (or current cash.amount price)
  4. Total: ₱20.00
  
  5. Add Email: `test@example.com`
  6. Wait ~500ms (debounce)
  7. Should see new line "Email Address: ₱1.50" (or current price)
  8. Total updates to ₱21.50
  
  9. Add Mobile: `09171234567`
  10. Total updates to ₱23.30 (20.00 + 1.50 + 1.80)
  
  11. Select input field: "Signature"
  12. Total updates to ₱26.10 (adds ₱2.80)

- **Verify**:
  - Pricing updates automatically (500ms delay)
  - Shows "Calculating charges..." briefly
  - Each line item clearly labeled
  - Total shown prominently
  - Per-voucher cost shown at bottom

#### 3.3 Generate Vouchers
- **Action**: Complete form and click "Generate Vouchers"
- **Expected**: 
  - Success page shows generated vouchers
  - Redirected to success page

### 4. User Billing

#### 4.1 View Own Billing
- **URL**: `http://localhost:8000/billing`
- **Expected**:
  - Three summary cards:
    - Total Vouchers: Shows count
    - Total Charges: Shows total ₱ amount
    - This Month: Shows current month charges
  - Table showing recent charges:
    - Date/time
    - Campaign name (or "Direct")
    - Voucher count
    - Total charge
    - Per-voucher cost
- **Verify**:
  - Your recent voucher generation appears in table
  - Amounts match what was shown during generation
  - Summary cards update correctly

### 5. Admin Billing

#### 5.1 View All Users' Charges
- **Action**: Login as admin
- **URL**: `http://localhost:8000/admin/billing`
- **Expected**:
  - Table showing ALL users' charges
  - Columns: User (name/email), Date, Campaign, Vouchers, Total
  - Each row has "View" button
- **Verify**:
  - See charges from all users (not just admin)
  - Total count shown in header

#### 5.2 View Charge Details
- **Action**: Click "View" on any charge
- **Expected**:
  - Charge Details page
  - Two cards:
    1. **Charge Breakdown**:
       - Each item with price
       - Total shown at bottom
    2. **Voucher Codes**:
       - Scrollable list of all generated codes
- **Verify**:
  - Breakdown matches what was charged
  - All voucher codes present
  - Can scroll through codes if many

### 6. Database Verification

#### 6.1 Check Charge Record
```bash
php artisan tinker
```

```php
// View latest charge
$charge = \App\Models\VoucherGenerationCharge::latest()->first();
$charge->toArray(); // See full record

// Check breakdown
$charge->charge_breakdown; // Array of charge items

// Check snapshot
$charge->instructions_snapshot; // Original instructions

// Verify user link
$charge->user->name;
```

#### 6.2 Check User-Voucher Link
```php
$user = \App\Models\User::find(1);
$user->generatedVouchers->count(); // Should match voucher count
$user->voucherGenerationCharges->count(); // Number of generation sessions
```

#### 6.3 Check Price History
```php
$item = \App\Models\InstructionItem::where('index', 'feedback.email')->first();
$item->priceHistory; // Should show any price changes you made
```

### 7. Error Cases

#### 7.1 API Error Handling
- **Action**: Turn off development server
- **Test**: Try to generate vouchers
- **Expected**: Error message shown to user

#### 7.2 Unauthorized Access
- **Test**: Logout
- **Try**: Access `http://localhost:8000/admin/pricing`
- **Expected**: Redirected to login

- **Test**: Login as regular user
- **Try**: Access `http://localhost:8000/admin/pricing`
- **Expected**: 403 Forbidden error

### 8. Integration Tests (Optional)

Run automated tests:
```bash
# All pricing tests
php artisan test --filter=Instruction

# Controller tests only
php artisan test tests/Feature/Api/ChargeCalculationControllerTest.php
php artisan test tests/Feature/Admin/PricingControllerTest.php
php artisan test tests/Feature/BillingControllerTest.php
```

**Expected**: 
- Most tests pass
- Some may fail due to missing Inertia pages (expected in tests)
- All backend logic tests should pass

## Checklist

### Admin Features
- [ ] Can view all pricing items
- [ ] Can edit pricing with reason
- [ ] Price history is recorded
- [ ] Cannot edit without reason
- [ ] Can view all users' billing
- [ ] Can view detailed charge breakdown

### User Features
- [ ] Live pricing updates on form
- [ ] Pricing shows before generation
- [ ] Charges recorded after generation
- [ ] Can view own billing history
- [ ] Summary statistics correct

### Technical Verification
- [ ] Charges saved to database
- [ ] Vouchers linked to users
- [ ] Instructions snapshot stored
- [ ] Charge breakdown matches preview
- [ ] Price history audit trail works

## Troubleshooting

### "Admin user not found"
```bash
php artisan db:seed --class=AdminUserSeeder
```

### "No pricing items"
```bash
php artisan db:seed --class=InstructionItemSeeder
```

### "Permission denied"
```bash
# Verify admin has super-admin role
php artisan tinker
```
```php
$admin = User::where('email', 'admin@redeem.test')->first();
$admin->assignRole('super-admin');
```

### "Live pricing not working"
- Check browser console for errors
- Verify API endpoint: `POST /api/v1/calculate-charges`
- Check network tab for 401/422 errors
- Ensure Sanctum authentication is working

### "Charges not recording"
- Check queue is running: `php artisan queue:listen`
- Check logs: `storage/logs/laravel.log`
- Verify database migration ran

## Success Criteria

✅ All checklist items above complete
✅ Admin can manage pricing
✅ Users see live pricing before generation
✅ Charges automatically recorded
✅ Audit trail working (price history)
✅ No errors in browser console
✅ No errors in Laravel logs

## Next Steps After Testing

1. **Production Deployment**:
   - Run migrations on production
   - Seed instruction items
   - Create production admin user
   - Test with real users

2. **Monitoring**:
   - Monitor charge records
   - Review price history periodically
   - Check for anomalies in billing

3. **Future Enhancements** (see docs/PRICING-TODO.md):
   - Volume discounts
   - Customer tiers
   - Payment integration
   - Monthly billing reports
