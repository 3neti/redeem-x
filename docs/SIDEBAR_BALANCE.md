# Sidebar Balance Display

This document describes the configurable wallet balance display in the application sidebar.

## Overview

The sidebar balance feature displays the user's wallet balance in the application sidebar with real-time updates via WebSocket (Laravel Echo). The display is fully configurable through environment variables and adapts to both collapsed and expanded sidebar states.

## Features

- ✅ **Real-time Updates**: Balance updates automatically via WebSocket when transactions occur
- ✅ **Responsive Design**: Adapts to collapsed/expanded sidebar states
- ✅ **Configurable Display**: Control visibility, style, labels, and additional info
- ✅ **Loading States**: Shows skeleton loader while fetching balance
- ✅ **Error Handling**: Displays error messages when balance fetch fails
- ✅ **Optional Refresh**: Configurable manual refresh button
- ✅ **Timestamp Display**: Optional last updated timestamp
- ✅ **Clickable Navigation**: Click balance to navigate to Balance Monitoring page (role-based)
- ✅ **Permission-Based Access**: Only users with required role can navigate to `/balances`

## Configuration

All configuration is managed through `config/sidebar.php` and can be overridden via environment variables in `.env`.

### Environment Variables

```bash
# Show/hide balance in sidebar (default: true)
SIDEBAR_SHOW_BALANCE=true

# Label text (default: "Wallet Balance")
SIDEBAR_BALANCE_LABEL="Wallet Balance"

# Display style: compact or full (default: compact)
# - compact: Shows only balance amount with minimal styling
# - full: Shows balance in a card with additional styling
SIDEBAR_BALANCE_STYLE=compact

# Show currency symbol (default: true)
SIDEBAR_BALANCE_SHOW_CURRENCY=true

# Show wallet icon (default: true)
SIDEBAR_BALANCE_SHOW_ICON=true

# Show manual refresh button (default: false)
SIDEBAR_BALANCE_SHOW_REFRESH=false

# Show last updated timestamp (default: false)
SIDEBAR_BALANCE_SHOW_UPDATED=false

# Position: above-footer or in-content (default: above-footer)
SIDEBAR_BALANCE_POSITION=above-footer

# Role/permission configuration for clicking to navigate to /balances
# These are shared from balance config (see config/balance.php)
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=admin
```

## Display Styles

### Compact Style
The default style shows a minimal balance display:
- Small icon and label
- Large formatted balance
- Optional refresh button
- Optional last updated timestamp

Best for: Standard sidebar usage, minimal visual footprint

### Full Style
A more prominent card-based display:
- Card container with border and shadow
- Medium icon and label header
- Extra large formatted balance
- Optional refresh button
- Optional last updated timestamp

Best for: Emphasis on wallet balance, when it's a primary feature

## Component Architecture

### Files Created

1. **`config/sidebar.php`** - Configuration file
2. **`resources/js/components/NavBalance.vue`** - Balance display component
3. **`docs/SIDEBAR_BALANCE.md`** - This documentation

### Files Modified

1. **`app/Http/Middleware/HandleInertiaRequests.php`** - Shares config with frontend
2. **`resources/js/components/AppSidebar.vue`** - Integrates NavBalance component
3. **`.env.example`** - Adds configuration examples

## Clickable Navigation

The balance widget is **clickable** and navigates to the Balance Monitoring page (`/balances`) when clicked, but **only if the user has the required role**.

### Permission Check

The component checks:
1. **`BALANCE_VIEW_ENABLED`** - Is balance viewing enabled globally?
2. **`BALANCE_VIEW_ROLE`** - What role is required? (default: `admin`)
3. **User's roles** - Does the user have the required role?

If all checks pass, clicking the balance:
- Shows hover effect (background highlight)
- Cursor changes to pointer
- Navigates to `/balances` page

If checks fail:
- No hover effect
- Cursor remains default
- Click does nothing

### Visual Indicators

**Clickable (has permission):**
- Hover background color change
- Pointer cursor
- Smooth transition animation

**Non-clickable (no permission):**
- No hover effects
- Default cursor
- Static display only

## Technical Details

### Real-time Updates

The component uses the existing `useWalletBalance` composable which:
1. Fetches initial balance on mount via API endpoint
2. Subscribes to user-specific Echo channel (`user.{id}`)
3. Listens for `.balance.updated` events
4. Updates balance automatically when events are received

### Responsive Behavior

The component detects sidebar state using `useSidebar` composable:
- **Collapsed**: Shows only the wallet icon (if enabled)
- **Expanded**: Shows full balance display based on configured style

### Loading States

Three states are handled:
1. **Loading**: Shows skeleton loader (using `Skeleton` component)
2. **Error**: Displays error message in red text
3. **Success**: Shows formatted balance with currency

### API Endpoint

Balance is fetched from: `CheckWalletBalanceController`
- Route: `/api/wallet/balance` (or similar, check routes)
- Method: GET
- Response: `{ balance, currency, type, datetime }`

## Usage Examples

### Hide Balance Completely
```bash
SIDEBAR_SHOW_BALANCE=false
```

### Show Full Style with Refresh and Timestamp
```bash
SIDEBAR_BALANCE_STYLE=full
SIDEBAR_BALANCE_SHOW_REFRESH=true
SIDEBAR_BALANCE_SHOW_UPDATED=true
```

### Custom Label
```bash
SIDEBAR_BALANCE_LABEL="Available Funds"
```

### Minimal Display (Icon Only When Collapsed)
```bash
SIDEBAR_BALANCE_SHOW_ICON=true
SIDEBAR_BALANCE_SHOW_REFRESH=false
SIDEBAR_BALANCE_SHOW_UPDATED=false
```

### Allow All Users to Navigate to Balance Page
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=  # Empty = all authenticated users can click
```

### Disable Navigation (Static Display Only)
```bash
BALANCE_VIEW_ENABLED=false
# Balance shows but is not clickable for anyone
```

### Restrict to Super Admin Only
```bash
BALANCE_VIEW_ENABLED=true
BALANCE_VIEW_ROLE=super-admin
```

## Integration with Existing Systems

### Wallet System
The component integrates with the existing wallet package (`packages/wallet/`) and uses:
- `User` model's `wallet` relationship
- `CheckBalance` action
- `WalletType` enum

### Broadcasting
Real-time updates require:
- Laravel Echo configured (Pusher or other driver)
- Broadcasting enabled in `.env`
- Queue worker running for event dispatch

### Composables
Reuses the existing `useWalletBalance` composable, ensuring consistency with other balance displays in the app (e.g., Load Wallet page, Voucher Generation page).

## Customization Tips

### Change Position
Currently, the balance is positioned between `SidebarContent` and `SidebarFooter`. To change:

1. Edit `resources/js/components/AppSidebar.vue`
2. Move `<NavBalance v-if="showBalance" />` to desired location

### Custom Styling
Modify `resources/js/components/NavBalance.vue`:
- Adjust Tailwind classes in template
- Add custom CSS if needed
- Modify icon sizes, spacing, colors

### Add Additional Info
Extend the component to show:
- Multiple wallet balances (if user has multiple wallets)
- Wallet type indicator
- Transaction count
- Low balance warnings

## Testing

### Manual Testing
1. Start dev server: `composer dev`
2. Navigate to any authenticated page with sidebar
3. Observe balance display in sidebar
4. Toggle sidebar collapse/expand
5. Make a transaction (generate voucher, load wallet)
6. Verify balance updates in real-time

### Configuration Testing
Test each environment variable:
1. Set variable in `.env`
2. Clear config cache: `php artisan config:clear`
3. Refresh browser
4. Verify behavior matches configuration

### WebSocket Testing
1. Ensure queue worker is running: `php artisan queue:listen`
2. Ensure broadcasting is configured
3. Generate a voucher (deducts from balance)
4. Observe balance update without page refresh

## Troubleshooting

### Balance Not Showing
- Check `SIDEBAR_SHOW_BALANCE=true` in `.env`
- Clear config cache: `php artisan config:clear`
- Check browser console for errors

### Balance Not Updating Real-time
- Verify Laravel Echo is configured
- Check Pusher credentials in `.env`
- Ensure queue worker is running
- Check browser console for WebSocket connection errors

### Balance Shows "Failed to load"
- Check API endpoint is accessible
- Verify user has a wallet record
- Check Laravel logs for errors
- Verify authentication middleware

## Future Enhancements

Potential improvements:
- Multiple wallet type display (if user has multiple wallets)
- Balance trend indicator (up/down arrow)
- Quick actions (load wallet button)
- Low balance threshold warnings
- Click to view transaction history
- Animated balance changes

## Related Documentation

- `docs/SYSTEM_WALLET_ARCHITECTURE.md` - Wallet system architecture
- `docs/CHANGELOG-realtime-wallet.md` - Real-time wallet updates
- `WARP.md` - Development commands and architecture overview
