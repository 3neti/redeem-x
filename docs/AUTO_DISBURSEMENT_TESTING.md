# Auto-Disbursement Feature - Testing Guide

## Prerequisites ✅

Your environment is ready:
- ✅ Auto-disburse threshold: ₱25
- ✅ Test user: admin@disburse.cash
- ✅ Bank accounts: 2 saved (GCash 2 is default)

---

## Test Scenario 1: Happy Path (Complete Auto-Disbursement)

### Step 1: Create a Settlement Voucher

**Via UI** (Recommended):
1. Navigate to: `http://redeem-x.test/portal`
2. Fill in the voucher generation form:
   - **Amount**: ₱100
   - **Type**: Settlement/Payable voucher
   - **Count**: 1
3. Click "Generate"
4. Copy the voucher code (e.g., `ABC1`)

**Via CLI** (Alternative):
```bash
php artisan tinker
```
```php
use App\Actions\Api\Vouchers\GenerateVouchers;
use App\Models\User;

$user = User::first();
$action = new GenerateVouchers();

// Generate settlement voucher
$result = $action->handle($user, [
    'amount' => 100,
    'type' => 'settlement',
    'count' => 1,
]);

echo "Voucher code: " . $result['vouchers'][0]->code;
```

### Step 2: View the Settlement Voucher

1. Navigate to: `http://redeem-x.test/vouchers/{CODE}/show`
   - Replace `{CODE}` with your voucher code
2. Verify:
   - Amount: ₱100
   - Type: Settlement
   - Status: Pending payment

### Step 3: Simulate a Payment (Create Payment Request)

**Via UI** (Payment Page):
1. Navigate to: `http://redeem-x.test/pay?code={CODE}`
2. Enter payment amount: ₱100
3. Generate QR code or mark payment as done
4. This creates a "pending payment request" awaiting confirmation

**Via API** (Alternative):
```bash
curl -X POST http://redeem-x.test/api/v1/pay/mark-done \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "voucher_code": "ABC1",
    "amount": 100,
    "payer_mobile": "09171234567"
  }'
```

### Step 4: Test Auto-Disbursement Modal

1. Go back to the voucher page: `http://redeem-x.test/vouchers/{CODE}/show`
2. You should see a **"Pending Payment Requests"** section
3. Click the **"Confirm Payment"** button
4. 🎯 **Expected Result**: The **AutoDisbursementModal** should appear with:
   - Amount display: ₱100.00
   - Bank account dropdown (showing your 2 accounts)
   - GCash 2 pre-selected (as default)
   - "Remember my choice" toggle
   - Two buttons:
     - "Transfer to Wallet" (left)
     - "Disburse to Bank" (right)

### Step 5A: Test "Transfer to Wallet" Option

1. Click **"Transfer to Wallet"** button
2. 🎯 **Expected Result**:
   - Modal closes
   - Payment is confirmed
   - Funds go to voucher's cash wallet
   - NO disbursement occurs
   - Page refreshes showing updated balance

### Step 5B: Test "Disburse to Bank" Option (Main Feature)

1. Select a bank account from dropdown (or keep default)
2. Toggle "Remember my choice" ON
3. Click **"Disburse to Bank"** button
4. 🎯 **Expected Results**:
   - Button shows "Disbursing..." with spinner
   - Payment is confirmed
   - DisbursementService validates:
     - ✓ Amount (₱100) >= threshold (₱25)
     - ✓ Voucher is fully paid
     - ✓ Bank account exists
   - Payment gateway is called
   - Modal closes on success
   - User preference saved for future

---

## Test Scenario 2: Below Threshold

### Steps:
1. Create settlement voucher for **₱10** (below ₱25 threshold)
2. Create payment request for ₱10
3. Confirm payment → Modal appears
4. Click "Disburse to Bank"

### 🎯 Expected Result:
- Error message: "Amount ₱10 is below minimum threshold ₱25"
- Payment IS confirmed (goes to wallet)
- Disbursement does NOT occur

---

## Test Scenario 3: Partial Payment (Not Fully Paid)

### Steps:
1. Create settlement voucher for **₱200**
2. Create payment request for **₱100** (partial)
3. Confirm payment → Modal appears
4. Click "Disburse to Bank"

### 🎯 Expected Result:
- Error message: "Voucher not fully paid yet. Auto-disbursement only works for fully paid vouchers."
- ₱100 is confirmed (goes to wallet)
- Disbursement does NOT occur
- Remaining balance: ₱100

---

## Test Scenario 4: No Bank Accounts

### Steps:
1. Remove all bank accounts from user:
```php
$user = User::first();
$user->bank_accounts = [];
$user->save();
```

2. Create settlement voucher for ₱100
3. Create payment request for ₱100
4. Confirm payment → Modal appears

### 🎯 Expected Result:
- Alert box shows: "You don't have any saved bank accounts yet..."
- Link to `/settings/profile` to add account
- "Disburse to Bank" button is DISABLED
- Can only use "Transfer to Wallet"

---

## Test Scenario 5: Remember Choice

### Steps:
1. Ensure user has bank accounts
2. Create settlement voucher for ₱100
3. Confirm payment with:
   - Bank account selected
   - "Remember my choice" = ON
   - Click "Disburse to Bank"
4. Check user preferences:
```php
$user = User::first();
$prefs = $user->ui_preferences;
// Should have: auto_disburse_on_settlement = true
// Should have: auto_disburse_bank_account_id = <UUID>
```

### 🎯 Expected Result:
- Preferences saved in database
- Future feature: Auto-select this account on next payment
  (Not yet implemented in modal - can be added later)

---

## API Testing (Without UI)

### Test ConfirmPayment API Directly

```bash
# Get CSRF token
CSRF_TOKEN=$(curl -s http://redeem-x.test/login | grep -o 'csrf-token" content="[^"]*' | cut -d'"' -f3)

# Login (get session cookie)
curl -X POST http://redeem-x.test/login \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
  -c cookies.txt \
  -d '{"email":"admin@disburse.cash","password":"your-password"}'

# Confirm payment WITH auto-disbursement
curl -X POST http://redeem-x.test/api/v1/vouchers/confirm-payment \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
  -b cookies.txt \
  -d '{
    "voucher_code": "ABC1",
    "amount": 100,
    "disburse_now": true,
    "bank_account_id": "<YOUR_BANK_ACCOUNT_UUID>",
    "remember_choice": true
  }'
```

### Expected Response:
```json
{
  "success": true,
  "message": "Payment confirmed successfully",
  "data": {
    "amount": 100,
    "payment_id": "WEB-1234567890",
    "new_paid_total": 100,
    "remaining": 0,
    "disbursement": {
      "success": true,
      "message": "Disbursement successful",
      "transaction_id": "NETBANK-TX-123456",
      "reference_id": "REF-ABC123",
      "error": null
    }
  }
}
```

---

## Troubleshooting

### Modal doesn't appear?
1. Check browser console for errors
2. Verify Vue component is registered
3. Check if PaymentsCard.vue is importing AutoDisbursementModal

### "Bank account not found" error?
1. Get your bank account UUID:
```php
$user = User::first();
$accounts = $user->getBankAccounts();
print_r($accounts);
```
2. Use the correct UUID in API calls

### Disbursement fails with gateway error?
1. Check `.env` settings:
```bash
USE_OMNIPAY=true
PAYMENT_GATEWAY=netbank
DISBURSE_DISABLE=false  # Must be false!
```

2. Check if gateway credentials are set (if using real NetBank)

### Threshold not working?
1. Verify threshold setting:
```php
$settings = app(\App\Settings\VoucherSettings::class);
echo $settings->auto_disburse_minimum; // Should be 25
```

2. Adjust in admin preferences if needed:
   - Navigate to: `http://redeem-x.test/admin/preferences`
   - Change "Auto-Disburse Minimum Amount"

---

## Success Criteria ✅

The feature is working correctly if:

1. ✅ Modal appears after payment confirmation
2. ✅ Bank accounts are listed in dropdown
3. ✅ Default account is pre-selected
4. ✅ "Transfer to Wallet" works (no disbursement)
5. ✅ "Disburse to Bank" calls API correctly
6. ✅ Threshold validation works (blocks < ₱25)
7. ✅ Fully paid validation works (blocks partial)
8. ✅ User preference is saved when "Remember" is checked
9. ✅ Error messages display correctly
10. ✅ API returns disbursement result in response

---

## Next Steps After Testing

Once testing is complete:

1. **Integrate Modal** into PaymentsCard.vue:
   - Import AutoDisbursementModal
   - Show modal after successful payment confirmation
   - Pass voucher code, amount, payment request ID

2. **Optional Enhancements**:
   - Email notification on disbursement
   - Feature flag for gradual rollout
   - Comprehensive automated tests
   - Add disbursement tracking to voucher metadata

3. **Production Checklist**:
   - Test with real NetBank credentials (sandbox)
   - Verify settlement rail selection works
   - Test with various amounts (< ₱25, < ₱50k, > ₱50k)
   - Monitor logs for errors
   - Set appropriate threshold for production (₱100? ₱500?)
