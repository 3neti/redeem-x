# Voucher-Based Payment System

## Overview
The voucher payment system enables users to redeem vouchers directly to their wallet without external bank rails. This provides a fast, in-system payment method complementing the traditional bank disbursement flow.

## Two Redemption Paths

### 1. Disburse (Cash Out)
- Route: `/disburse`
- Flow: Cash wallet → External bank account
- Use case: Convert voucher to real-world money
- Pipeline: Triggers post-redemption disbursement

### 2. Pay with Voucher (Wallet Credit)
- Route: `/pay/voucher`
- Flow: Cash wallet → User wallet
- Use case: Keep money in-system for future use
- Pipeline: Bypasses disbursement to keep money internal

## Money Flow

```
Voucher Generation:
User wallet → Cash wallet (escrow)

Redemption (Pay with Voucher):
Cash wallet → Redeemer wallet

Reclaim (Issuer redeems own voucher):
Cash wallet → Issuer wallet (returns escrow)
```

## API Endpoint

### POST /pay/voucher

**Authentication**: Required
**Rate Limit**: 5 requests/minute

**Request Body**:
```json
{
  "code": "ABC-123456"
}
```

**Success Response** (200):
```json
{
  "success": true,
  "amount": 100,
  "new_balance": 150.50,
  "voucher_code": "ABC-123456",
  "message": "Voucher redeemed successfully! ₱100.00 added to your wallet."
}
```

**Error Response** (422):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "code": ["This voucher has already been redeemed."]
  }
}
```

## Frontend Integration

### VoucherPaymentModal Component
Location: `resources/js/components/VoucherPaymentModal.vue`

Features:
- Voucher code input with auto-uppercase
- Loading state during redemption
- Error message display
- Success state with balance update
- Keyboard shortcuts (Escape to close)

### TopUp Page Integration
- "Have a voucher?" button triggers modal
- Seamless integration with existing top-up UI
- Success auto-closes modal and shows updated balance

## Backend Architecture

### PayWithVoucher Action
Location: `app/Actions/Payment/PayWithVoucher.php`

Responsibilities:
1. Validate voucher code (reuses `ValidateVoucherCode`)
2. Transfer funds from Cash wallet to User wallet
3. Mark voucher as redeemed with metadata
4. Return success response with new balance

Key features:
- Atomic database transaction
- Bypasses VoucherObserver (direct update)
- Stores redemption metadata in `voucher.metadata`

### PaymentController
Location: `app/Http/Controllers/Payment/PaymentController.php`

Thin controller that:
- Validates incoming request
- Delegates to PayWithVoucher action
- Handles ValidationException
- Returns JSON response

## Metadata Tracking

Voucher `metadata` column stores:
```php
[
  'redemption_type' => 'voucher_payment',
  'redeemer_user_id' => 123,
  'transfer_uuid' => '9d1e3f5a-...'
]
```

Transfer metadata (passed to `Cash::transfer()`):
```php
[
  'type' => 'voucher_payment',
  'voucher_code' => 'ABC-123456',
  'voucher_uuid' => '9d1e3f5a-...',
  'issuer_id' => 456
]
```

## Validation Rules

A voucher can be redeemed if:
- ✅ Voucher code exists
- ✅ Not expired (`expires_at > now()`)
- ✅ Not already redeemed (`redeemed_at IS NULL`)
- ✅ User is authenticated

Future validation (TODO):
- ⏳ Voucher has started (`starts_at <= now()`)

## Edge Cases

### Issuer Reclaim
- Issuers can redeem their own unredeemed vouchers
- Returns escrowed funds back to issuer wallet
- No fees deducted (pure escrow return)

### Code Normalization
- Lowercase codes accepted: `abc-123456` → `ABC-123456`
- Whitespace trimmed automatically

### Error Handling
- Invalid code: "Voucher code not found."
- Already redeemed: "This voucher has already been redeemed."
- Expired: "This voucher has expired."

## Testing

Test suite: `tests/Feature/Actions/Payment/PayWithVoucherTest.php`

Coverage (10 tests, 29 assertions):
1. ✅ Transfers money from Cash to User wallet
2. ✅ Marks voucher as redeemed with correct metadata
3. ✅ Creates Transfer transaction record
4. ✅ Rejects already redeemed voucher
5. ✅ Rejects expired voucher
6. ✅ Rejects invalid voucher code
7. ✅ Issuer can reclaim own voucher
8. ✅ Normalizes voucher code to uppercase
9. ✅ Includes issuer_id in voucher metadata
10. ✅ Bypasses post-redemption pipeline

Run tests:
```bash
php artisan test --filter PayWithVoucherTest
```

## Security Considerations

### Rate Limiting
- Prevents brute-force voucher code guessing
- 5 attempts per minute per authenticated user
- Returns 429 Too Many Requests on limit

### Authentication
- Only authenticated users can redeem vouchers
- Uses Laravel Sanctum token authentication

### Atomic Transactions
- Money transfer wrapped in DB transaction
- Either both voucher update and wallet transfer succeed, or both fail

## Future Enhancements

### Planned Features
- [ ] Start date validation (`starts_at` check)
- [ ] Multi-currency support
- [ ] Partial redemption (split voucher value)
- [ ] Redemption notifications (email/SMS)
- [ ] Redemption history page
- [ ] QR code scanning for voucher input

### Performance Optimizations
- [ ] Cache voucher lookups
- [ ] Batch transfer operations
- [ ] Async notification dispatch

## Troubleshooting

### "Voucher code not found"
- Check code is correct and uppercase
- Verify voucher exists in database
- Check voucher wasn't deleted

### "This voucher has already been redeemed"
- Voucher can only be redeemed once
- Check `redeemed_at` column for timestamp
- Review transaction history for details

### "This voucher has expired"
- Check `expires_at` timestamp
- Contact voucher issuer for replacement

### Rate limit exceeded
- Wait 60 seconds before retrying
- Check for accidental automated requests

## Development Notes

### Branch
Feature developed on: `feature/voucher-payment-system`

### Commits
1. Initial implementation (action, controller, route)
2. Frontend modal component
3. Test suite creation
4. Fix metadata column name
5. Update documentation

### Database Schema
No migrations required - uses existing voucher and wallet tables.

### Dependencies
- `lbhurtado/voucher` - Voucher package
- `bavix/laravel-wallet` - Wallet system
- `lorisleiva/laravel-actions` - Action pattern
- `inertiajs/inertia-laravel` - Frontend framework
