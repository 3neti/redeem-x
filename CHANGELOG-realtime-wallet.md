# Real-time Wallet Balance Integration

**Date:** November 12, 2025

## Overview
Implemented real-time wallet balance updates using Laravel Echo and Pusher, allowing users to see their wallet balance update instantly when transactions occur without needing to refresh the page.

## Changes

### Backend

#### New Files
- `app/Actions/CheckBalance.php` - Action class for checking wallet balance
- `app/Http/Controllers/CheckWalletBalanceController.php` - Controller for wallet balance API endpoint
- `app/Http/Controllers/Wallet/LoadController.php` - Controller for wallet loading operations
- `routes/channels.php` - Laravel Echo broadcasting channel definitions
- `config/broadcasting.php` - Broadcasting configuration
- `database/seeders/SystemWalletSeeder.php` - Seeder for system wallets
- `database/seeders/UserWalletSeeder.php` - Seeder for user wallets

#### Modified Files
- `app/Http/Middleware/HandleInertiaRequests.php`
  - Added wallet relationship eager loading to user data
  - Ensures wallet ID is available on frontend for Echo filtering

- `routes/web.php`
  - Added `/wallet/balance` API route for fetching current balance
  - Added `/wallet/load` route for wallet operations

### Frontend

#### New Files
- `resources/js/composables/useWalletBalance.ts` - Composable for managing wallet balance with real-time updates
  - Fetches wallet balance via API
  - Listens to Echo broadcasts on `user.{userId}` channel for `.balance.updated` events
  - Filters updates by wallet ID to ensure correct user receives updates
  - Provides reactive balance, formatted balance, and realtime update messages

- `resources/js/composables/useQrCode.ts` - QR code generation composable
- `resources/js/pages/Wallet/Balance.vue` - Wallet balance page component
- `resources/js/components/domain/` - Domain-specific components
- `resources/js/components/ui/toast/` - Toast notification components

#### Modified Files

**Echo/Pusher Integration:**
- `resources/js/app.ts`
  - Configured Laravel Echo with Pusher
  - Set up broadcasting channels and authentication

- `resources/js/composables/useWalletBalance.ts`
  - Fixed route import to use Wayfinder `CheckWalletBalanceController` instead of non-existent `route()` function
  - Added comprehensive Echo broadcast handling
  - Added wallet ID filtering for multi-wallet support
  - Exposed `realtimeNote` and `realtimeTime` for showing update feedback

- `resources/js/pages/Vouchers/Generate/Create.vue`
  - Integrated `useWalletBalance` composable for real-time balance updates
  - Added watcher to log balance changes (with debug flag)
  - Added visual feedback showing realtime update messages below wallet balance

- `resources/js/pages/settings/Wallet.vue`
  - Updated to use real-time balance updates via Echo

**Debug Logging System:**
Added debug flags (default: `false`) to all relevant files for easier troubleshooting:

- `resources/js/composables/useWalletBalance.ts`
  - Logs: User/wallet data, balance fetching, Echo broadcasts, balance updates
  
- `resources/js/composables/useChargeBreakdown.ts`
  - Logs: Payload changes, charge calculations, API responses
  
- `resources/js/components/voucher/forms/VoucherInstructionsForm.vue`
  - Logs: Form value changes, pricing calculations
  
- `resources/js/components/voucher/forms/InputFieldsForm.vue`
  - Logs: Field selection toggles, value updates
  
- `resources/js/lib/axios.ts`
  - Logs: Request/response times, errors, retry attempts
  
- `resources/js/pages/Vouchers/Generate/Create.vue`
  - Logs: Wallet balance updates

**Other Updates:**
- `resources/js/types/index.d.ts`
  - Added `wallet` property to User type with id and balance
  
- `resources/js/layouts/app/AppSidebarLayout.vue`
  - Updated sidebar layout
  
- `resources/js/components/AppSidebar.vue`
  - Updated sidebar component

### Configuration

- `.env.example`
  - Added broadcasting configuration examples
  - Added Pusher credentials placeholders

- `config/app.php`
  - Updated broadcasting service provider

- `config/redeem.php`
  - Updated redeem widget configuration

- `config/broadcasting.php`
  - Configured Pusher as default broadcaster
  - Set up channel authentication routes

### Dependencies

- `package.json`
  - Added `laravel-echo` for real-time broadcasting
  - Added `pusher-js` for Pusher client

- `composer.json`
  - Updated Laravel wallet package dependencies

## Features

### Real-time Balance Updates
- Wallet balance updates automatically via websockets when transactions occur
- No page refresh required
- Filtered by wallet ID for multi-wallet support
- Visual feedback showing update messages (e.g., "Funds added successfully")

### API Endpoints
- `GET /wallet/balance` - Fetch current wallet balance
  - Returns: balance (float), currency, type, datetime
  - Supports optional `type` parameter for wallet type filtering

### Broadcasting
- Channel: `user.{userId}`
- Event: `.balance.updated`
- Payload:
  ```javascript
  {
    walletId: number,
    balanceFloat: number,
    updatedAt: string,
    message: string
  }
  ```

### Debug System
- Centralized debug flags across all components
- Set `const DEBUG = true` to enable logging per file
- Logs are suppressed by default for clean console
- Easy to enable when troubleshooting

## Usage

### Using the Wallet Balance Composable

```typescript
import { useWalletBalance } from '@/composables/useWalletBalance';

const {
    balance,              // Reactive balance value (number | null)
    formattedBalance,     // Formatted balance string (e.g., "₱1,234.56")
    realtimeNote,         // Message from last Echo update
    realtimeTime,         // Timestamp of last update
    fetchBalance,         // Function to manually refresh balance
    status,               // 'idle' | 'loading' | 'success' | 'error'
} = useWalletBalance();
```

### Broadcasting Balance Updates (Backend)

```php
use Illuminate\Support\Facades\Broadcast;

// Trigger balance update event
Broadcast::channel('user.' . $user->id, function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Dispatch event
event(new BalanceUpdated($wallet, $user, 'Funds added successfully'));
```

## Testing

1. Open Generate Vouchers page
2. Add funds to wallet in another tab/window
3. Balance should update automatically without refresh
4. Update message should appear briefly below balance

## Environment Setup

Add to `.env`:
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

## Breaking Changes
None - this is additive functionality

## Migration Notes
- Run `npm install` to install new frontend dependencies
- Run `composer install` if wallet package was updated
- Ensure broadcasting is configured in `.env`
- Run seeders if needed: `php artisan db:seed --class=UserWalletSeeder`

## Known Issues
- HMR (Hot Module Replacement) may occasionally require a hard refresh if balance stops updating
- Solution: Hard refresh (Cmd+Shift+R) or restart dev server

## Future Improvements
- Add toast notifications for balance updates
- Add sound effects for successful transactions (optional)
- Add balance history/timeline component
- Support for multiple simultaneous wallet types
