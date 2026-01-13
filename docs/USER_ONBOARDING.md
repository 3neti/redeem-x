# User Onboarding System

Complete documentation for the seamless user onboarding flow that guides new users through mobile number collection and wallet funding requirements.

## Overview

New users signing up via WorkOS need to:
1. **Add their mobile number** (required for QR code generation and InstaPay payments)
2. **Fund their wallet** (required to generate vouchers)

This system uses guard middleware to enforce these requirements with automatic redirects and visual feedback.

## Architecture

### Components

**Backend Middleware:**
- `RequiresMobile` - Ensures user has a mobile number
- `RequiresWalletBalance` - Ensures user has positive wallet balance

**Frontend Pages:**
- Profile Settings (`settings/Profile.vue`) - Mobile collection with visual feedback
- Wallet QR (`wallet/Qr.vue`) - Wallet funding with real-time polling

**Supporting Files:**
- `database/factories/UserFactory.php` - Factory state for testing
- `tests/Feature/Middleware/` - Comprehensive test suite (19 tests)

### Middleware Chain

```
User visits /portal
    ↓
RequiresMobile checks
    ├─ Has mobile? → Continue
    └─ No mobile? → Redirect to /settings/profile
        ↓
    User adds mobile → Save
        ↓
    Redirect back to /portal
        ↓
RequiresWalletBalance checks
    ├─ Has balance > 0? → Continue
    └─ No balance? → Redirect to /wallet/qr
        ↓
    User generates QR → Gets paid
        ↓
    Auto-redirect to /portal (via polling)
        ↓
    User can now generate vouchers! ✅
```

## User Flow

### Step 1: Mobile Number Collection

**Trigger:** User visits a protected route without mobile number

**Redirect:** `/settings/profile?reason=mobile_required&return_to=<original-url>`

**Visual Feedback:**
- 🔴 Red alert banner: "Mobile Number Required"
- 🔴 Red label and input border on mobile field
- 🎯 Auto-focus on mobile input
- 📱 Smooth scroll to mobile field

**Implementation:**
```php
// app/Http/Middleware/RequiresMobile.php
if (!$user->mobile) {
    return redirect()->route('profile.edit', [
        'reason' => 'mobile_required',
        'return_to' => $request->fullUrl(),
    ])->with('flash', [
        'type' => 'warning',
        'message' => 'Please add your mobile number to continue.',
    ]);
}
```

### Step 2: Wallet Funding

**Trigger:** User has mobile but zero wallet balance

**Redirect:** `/wallet/qr?reason=insufficient_balance&return_to=<original-url>`

**Visual Feedback:**
- 🟠 Orange alert banner: "Wallet Balance Required"
- ⏱️ Polling indicator: "Watching for payments... You'll be redirected automatically"
- 🔄 Real-time balance checking (every 3 seconds)
- ✅ Success toast on payment received

**Implementation:**
```php
// app/Http/Middleware/RequiresWalletBalance.php
if ($user->balanceFloat <= 0) {
    return redirect()->route('wallet.qr', [
        'reason' => 'insufficient_balance',
        'return_to' => $request->fullUrl(),
    ])->with('flash', [
        'type' => 'warning',
        'message' => 'Please add funds to your wallet to generate vouchers.',
    ]);
}
```

**Frontend Polling:**
```typescript
// Check balance every 3 seconds
const checkBalance = async () => {
    const { data } = await axios.get('/api/v1/wallet/balance');
    const balance = data.data?.balance || 0;
    
    if (balance > 0 && props.return_to) {
        // Stop polling
        clearInterval(pollingInterval.value);
        
        // Show success toast
        toast({
            title: 'Wallet Funded!',
            description: 'Your wallet has been credited. Continuing...',
        });
        
        // Redirect after 1 second
        setTimeout(() => router.visit(props.return_to!), 1000);
    }
};
```

## Protected Routes

Routes that require both mobile and wallet balance:
- `/portal` - Main landing page after WorkOS auth
- `/vouchers/generate` - Voucher generation page
- `/vouchers/generate/bulk` - Bulk voucher generation

Routes that require only mobile:
- `/topup` - Bank-based top-up (admin feature)

## Testing

### Test Suite

**Location:** `tests/Feature/Middleware/`

**Coverage:** 19 tests, 63 assertions

**Run tests:**
```bash
vendor/bin/pest --group=middleware
```

**Test Categories:**

1. **Mobile Requirement (9 tests)**
   - Allow users with mobile to continue
   - Redirect users without mobile to profile
   - Include flash messages
   - Preserve full URL with query params
   - Block bulk generation without mobile
   - Block topup without mobile
   - Allow topup when mobile exists
   - Handle mobile in different formats
   - Don't block unauthenticated users

2. **Wallet Balance Requirement (10 tests)**
   - Allow users with positive balance
   - Redirect zero balance to wallet QR
   - Include flash messages
   - Block exactly zero balance
   - Allow small positive balance (₱0.05+)
   - Block negative balance
   - Block bulk generation with zero balance
   - Preserve return URL with query params
   - Don't apply to wallet QR routes
   - Run after mobile check in chain

### Factory State Pattern

**Problem:** Tests need users with mobile channels pre-created.

**Solution:** Use factory state with `afterCreating()` callback.

```php
// database/factories/UserFactory.php
public function withMobile(string $mobile = '09171234567'): static
{
    return $this->afterCreating(function (User $user) use ($mobile) {
        $user->setChannel('mobile', $mobile);
    });
}

// Usage in tests
$user = User::factory()->withMobile()->create();
```

**Why not `setChannel()` after `create()`?**
- Laravel's auth system caches the user instance
- Middleware receives a different instance without the channel
- Factory state ensures channel exists before user enters auth system

## Manual Testing

### Simulate Deposit

Use the Artisan command to trigger webhooks:

```bash
# Basic usage
php artisan simulate:deposit 09171234567 500

# With force flag (no confirmation)
php artisan simulate:deposit 09171234567 500 --force

# Custom sender
php artisan simulate:deposit lbhurtado@gmail.com 1000 --sender-name="John Doe"

# Use first user (no identifier)
php artisan simulate:deposit 100 --force
```

**Command features:**
- ✅ Accepts mobile or email
- ✅ Amount in major units (pesos)
- ✅ Shows payload before sending
- ✅ Confirms before execution (unless --force)
- ✅ Displays new balance after deposit

### Full Onboarding Test

1. **Reset database:**
   ```bash
   php artisan migrate:fresh --seed
   ```

2. **Create new user via WorkOS** (or use existing)

3. **Remove mobile:**
   ```bash
   php artisan tinker
   $user = User::where('email', 'test@example.com')->first();
   $user->setChannel('mobile', null);
   ```

4. **Visit portal:** `http://redeem-x.test/portal`
   - Should redirect to Profile Settings
   - See red alert and red mobile field
   - Mobile input auto-focused

5. **Add mobile and save**
   - Should redirect back to `/portal`
   - Should then redirect to `/wallet/qr`
   - See orange alert with polling indicator

6. **Simulate deposit:**
   ```bash
   php artisan simulate:deposit test@example.com 500 --force
   ```

7. **Watch auto-redirect:**
   - Polling detects balance (within 3 seconds)
   - Toast: "Wallet Funded!"
   - Auto-redirect to `/portal` after 1 second

8. **Generate voucher** ✅
   - Should now work without redirects!

## Webhook Integration

### Production Flow

1. **User generates QR code** at `/wallet/qr`
2. **User shares QR** via WhatsApp/SMS/Email
3. **Payer scans QR** and sends money via GCash/Maya
4. **NetBank webhook** calls `/api/confirm-deposit`
5. **Wallet credited** via payment gateway
6. **Frontend polling** detects balance change
7. **Auto-redirect** to original destination

### Webhook Endpoint

**URL:** `POST /api/confirm-deposit`

**Payload Structure:**
```json
{
  "alias": "91500",
  "amount": 500,
  "channel": "INSTAPAY",
  "commandId": 12345,
  "externalTransferStatus": "SUCCESS",
  "operationId": 67890,
  "productBranchCode": "001",
  "recipientAccountNumber": "9150009171234567",
  "recipientAccountNumberBankFormat": "09171234567",
  "referenceCode": "REF123",
  "referenceNumber": "TXNREF456",
  "registrationTime": "2026-01-13T05:38:00Z",
  "remarks": "InstaPay transfer",
  "sender": {
    "accountNumber": "1234567890",
    "institutionCode": "GCASH",
    "name": "Test Sender"
  },
  "transferType": "INSTAPAY"
}
```

**Format Notes:**
- `amount`: Major units (pesos), not centavos
- `recipientAccountNumber`: `91500` prefix + mobile (09171234567)
- `recipientAccountNumberBankFormat`: Mobile in national format

## Configuration

### Middleware Registration

**File:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'requires.mobile' => \App\Http\Middleware\RequiresMobile::class,
        'requires.balance' => \App\Http\Middleware\RequiresWalletBalance::class,
    ]);
})
```

### Route Protection

**File:** `routes/web.php`

```php
// Both guards
Route::get('/portal', [PortalController::class, 'show'])
    ->middleware(['requires.mobile', 'requires.balance'])
    ->name('portal');

// Mobile only
Route::get('/topup', [TopUpController::class, 'index'])
    ->middleware('requires.mobile')
    ->name('topup.index');
```

## Troubleshooting

### Alert shows but no redirect

**Issue:** Balance is positive but page doesn't redirect.

**Cause:** Polling only triggers when balance changes from 0 → positive.

**Solution:** Added immediate check on mount:
```typescript
onMounted(async () => {
    if (props.reason === 'insufficient_balance' && props.return_to) {
        await checkBalance(); // Check immediately
        
        if (currentBalance.value <= 0) {
            // Only poll if still zero
            pollingInterval.value = setInterval(checkBalance, 3000);
        }
    }
});
```

### Mobile field not auto-focused

**Issue:** Mobile input doesn't focus on redirect.

**Cause:** DOM not ready when `focus()` is called.

**Solution:** Use `setTimeout()` with scroll:
```typescript
setTimeout(() => {
    const mobileInput = document.getElementById('mobile');
    if (mobileInput) {
        mobileInput.focus();
        mobileInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}, 100);
```

### Tests fail with "User WITH mobile redirected"

**Issue:** Test creates user with mobile but middleware redirects anyway.

**Cause:** Channel created after user instance cached by auth system.

**Solution:** Use factory state pattern (see Testing section).

## Future Enhancements

### Real-time Broadcasting (Optional)

Replace polling with WebSockets/Pusher:

1. **Fire event on deposit:**
   ```php
   event(new WalletCredited($user, $amount));
   ```

2. **Listen in frontend:**
   ```typescript
   Echo.private(`users.${userId}`)
       .listen('WalletCredited', (e) => {
           // Redirect immediately
       });
   ```

**Benefits:**
- Instant updates (no 3-second delay)
- Reduced server load (no polling)
- Better UX for live demos

**Trade-offs:**
- Requires Redis/Pusher setup
- More complex infrastructure
- Current polling works well enough

## Related Documentation

- [Mobile QR Generation](./MOBILE_QR_GENERATION.md)
- [Top-Up System](../WARP.md#top-up--direct-checkout-system)
- [Testing Strategy](../tests/Feature/Middleware/README.md)
- [Webhook Integration](../WARP.md#webhook-integration)

## Changelog

**2026-01-13** - Initial implementation
- Created RequiresMobile and RequiresWalletBalance middleware
- Added visual feedback to Profile and Wallet QR pages
- Implemented real-time polling with auto-redirect
- Added factory state pattern for testing
- 19 tests with 63 assertions (all passing)
- Updated SimulateDepositCommand with --force flag
