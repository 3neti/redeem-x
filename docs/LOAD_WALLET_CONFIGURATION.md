# Load Wallet Configuration

This document describes the configuration system for the Load Wallet page.

## Overview

The Load Wallet page is fully configurable through the `config/load-wallet.php` file, following the same pattern as the redemption pages (`config/redeem.php`).

## Configuration File

**Location**: `config/load-wallet.php`

## Configuration Options

### Page Header

```php
'header' => [
    'title' => env('LOAD_WALLET_TITLE', 'Load Your Wallet'),
    'show_balance' => env('LOAD_WALLET_SHOW_BALANCE', true),
    'balance_prefix' => env('LOAD_WALLET_BALANCE_PREFIX', 'Current Balance:'),
],
```

**Controls**:
- Page title
- Whether to show wallet balance
- Text prefix before balance amount

### QR Display Card

```php
'qr_card' => [
    'show' => env('LOAD_WALLET_SHOW_QR_CARD', true),
    'title' => env('LOAD_WALLET_QR_TITLE', 'QR Code'),
    'description' => env('LOAD_WALLET_QR_DESCRIPTION', 'Scan to load.'),
    'show_regenerate_button' => env('LOAD_WALLET_SHOW_REGENERATE_BUTTON', true),
    'regenerate_button_text' => env('LOAD_WALLET_REGENERATE_BUTTON_TEXT', 'Regenerate QR Code'),
    'regenerate_button_loading_text' => env('LOAD_WALLET_REGENERATE_BUTTON_LOADING_TEXT', 'Generating...'),
],
```

**Controls**:
- Show/hide entire QR card
- Card title and description
- Regenerate button visibility and text

### Display Settings Card

```php
'display_settings_card' => [
    'show' => env('LOAD_WALLET_SHOW_DISPLAY_SETTINGS', true),
    'title' => env('LOAD_WALLET_DISPLAY_SETTINGS_TITLE', 'Display Settings'),
    'description' => env('LOAD_WALLET_DISPLAY_SETTINGS_DESCRIPTION', 'Customize how your name appears on QR codes'),
],
```

**Controls**:
- Show/hide merchant name template editor
- Card title and description

### Amount Settings Card

```php
'amount_settings_card' => [
    'show' => env('LOAD_WALLET_SHOW_AMOUNT_SETTINGS', true),
    'title' => env('LOAD_WALLET_AMOUNT_SETTINGS_TITLE', 'Amount Settings'),
    'description' => env('LOAD_WALLET_AMOUNT_SETTINGS_DESCRIPTION', 'These settings control your wallet-load QR behavior'),
    'show_save_button' => env('LOAD_WALLET_SHOW_SAVE_BUTTON', true),
    'save_button_text' => env('LOAD_WALLET_SAVE_BUTTON_TEXT', 'Save Amount Settings'),
    'save_button_loading_text' => env('LOAD_WALLET_SAVE_BUTTON_LOADING_TEXT', 'Saving...'),
],
```

**Controls**:
- Show/hide amount settings (dynamic amount, default amount, min/max, tips)
- Card title and description
- Save button visibility and text

### Share Panel

```php
'share_panel' => [
    'show' => env('LOAD_WALLET_SHOW_SHARE_PANEL', true),
],
```

**Controls**:
- Show/hide QR sharing panel

## Environment Variables

Add to `.env` to customize the Load Wallet page:

```bash
# Page Header
LOAD_WALLET_TITLE="Load Your Wallet"
LOAD_WALLET_SHOW_BALANCE=true
LOAD_WALLET_BALANCE_PREFIX="Current Balance:"

# QR Display Card
LOAD_WALLET_SHOW_QR_CARD=true
LOAD_WALLET_QR_TITLE="QR Code"
LOAD_WALLET_QR_DESCRIPTION="Scan to load."
LOAD_WALLET_SHOW_REGENERATE_BUTTON=true
LOAD_WALLET_REGENERATE_BUTTON_TEXT="Regenerate QR Code"
LOAD_WALLET_REGENERATE_BUTTON_LOADING_TEXT="Generating..."

# Display Settings Card
LOAD_WALLET_SHOW_DISPLAY_SETTINGS=true
LOAD_WALLET_DISPLAY_SETTINGS_TITLE="Display Settings"
LOAD_WALLET_DISPLAY_SETTINGS_DESCRIPTION="Customize how your name appears on QR codes"

# Amount Settings Card
LOAD_WALLET_SHOW_AMOUNT_SETTINGS=true
LOAD_WALLET_AMOUNT_SETTINGS_TITLE="Amount Settings"
LOAD_WALLET_AMOUNT_SETTINGS_DESCRIPTION="These settings control your wallet-load QR behavior"
LOAD_WALLET_SHOW_SAVE_BUTTON=true
LOAD_WALLET_SAVE_BUTTON_TEXT="Save Amount Settings"
LOAD_WALLET_SAVE_BUTTON_LOADING_TEXT="Saving..."

# Share Panel
LOAD_WALLET_SHOW_SHARE_PANEL=true
```

## Usage Examples

### Hide Balance from Header

```bash
LOAD_WALLET_SHOW_BALANCE=false
```

### Customize QR Card Text

```bash
LOAD_WALLET_QR_TITLE="Your Payment QR"
LOAD_WALLET_QR_DESCRIPTION="Present this code to receive payment"
```

### Hide Display Settings Card

```bash
LOAD_WALLET_SHOW_DISPLAY_SETTINGS=false
```

### Minimal Configuration (QR only)

```bash
LOAD_WALLET_SHOW_DISPLAY_SETTINGS=false
LOAD_WALLET_SHOW_AMOUNT_SETTINGS=false
LOAD_WALLET_SHOW_SHARE_PANEL=false
```

This shows only the QR code card.

## Implementation

### Controller

**File**: `app/Http/Controllers/Wallet/LoadController.php`

```php
public function __invoke(Request $request): Response
{
    return Inertia::render('Wallet/Load', [
        'loadWalletConfig' => config('load-wallet'),
    ]);
}
```

The configuration is passed to the Vue component as a prop.

### Frontend Component

**File**: `resources/js/pages/Wallet/Load.vue`

Configuration is accessed via `usePage()`:

```typescript
const page = usePage();
const config = page.props.loadWalletConfig || {};
```

Each section uses conditional rendering:

```vue
<Card v-if="config.qr_card?.show !== false">
    <CardHeader>
        <CardTitle>{{ config.qr_card?.title || 'QR Code' }}</CardTitle>
        <CardDescription>
            {{ config.qr_card?.description || 'Scan to load.' }}
        </CardDescription>
    </CardHeader>
    <!-- ... -->
</Card>
```

## Configuration Pattern

This follows the same pattern as `config/redeem.php`:

1. **Hierarchical structure**: Grouped by page section (header, qr_card, etc.)
2. **Environment variable support**: Each setting can be overridden via `.env`
3. **Sensible defaults**: Works out of the box without configuration
4. **Visibility toggles**: `show` key to hide entire sections
5. **Text customization**: All labels and messages are configurable

## Files Changed

- `config/load-wallet.php` (new)
- `app/Http/Controllers/Wallet/LoadController.php`
- `resources/js/pages/Wallet/Load.vue`

## Benefits

1. **White-labeling**: Customize text for different brands
2. **Simplified UI**: Hide unnecessary cards for specific use cases
3. **Multi-language support**: Change text without modifying code
4. **A/B testing**: Test different copy easily
5. **Client-specific customization**: Different configs per deployment

## Best Practices

1. **Use environment variables**: Keep config file with defaults, override in `.env`
2. **Cache configuration**: Run `php artisan config:cache` in production
3. **Version control**: Commit config file with defaults, not `.env`
4. **Document changes**: Update this file when adding new config options

## Comparison with Redeem Config

The Load Wallet configuration follows the same patterns as `config/redeem.php`:

| Feature | Redeem Pages | Load Wallet |
|---------|--------------|-------------|
| Hierarchical sections | ✓ | ✓ |
| Environment variables | ✓ | ✓ |
| Visibility toggles | ✓ | ✓ |
| Text customization | ✓ | ✓ |
| Sensible defaults | ✓ | ✓ |

This consistency makes it easier for developers to understand and maintain both systems.

## Future Enhancements

Potential additions to the configuration system:

- Custom CSS classes for styling
- Additional card ordering options
- Conditional logic based on user roles
- Per-user configuration overrides
- Layout variants (sidebar vs. stacked)
