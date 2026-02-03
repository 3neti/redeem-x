# Merchant Name Template System

This document describes the configurable merchant name template system for QR code generation.

## Overview

The merchant name template system allows users to customize how their name appears in QR codes when scanned by e-wallets like GCash and PayMaya.

## Features

- **User-configurable templates**: Each merchant can define their own name template
- **Variable substitution**: Support for `{name}`, `{city}`, `{app_name}`
- **Live preview**: See how the name will appear before saving
- **25-character limit**: Preview automatically truncates to match GCash's display limit
- **Database persistence**: Template is stored per merchant in the `merchants` table

## Database Schema

### Merchants Table

Added `merchant_name_template` column:

```php
$table->string('merchant_name_template')->default('{name} - {city}');
```

## Template Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `{name}` | Merchant name from database | `3neti R&D OPC` |
| `{city}` | Merchant city from database | `Manila` |
| `{app_name}` | Application name from config | `redeem-x` |

## Usage

### In Profile Settings

Navigate to **Settings → Profile** and find the "Merchant Name Template" section:

1. Enter your template using variables (e.g., `{name} | {city}`)
2. Click the eye icon (👁️) to preview
3. Preview shows exactly what will display in GCash (truncated to 25 characters)
4. Template is automatically saved when you preview
5. Click "Save Merchant Profile" to persist all changes

### In Load Wallet Page

Navigate to **Wallet → Load** and find the "Display Settings" card:

1. Edit the merchant name template
2. Click preview to see the result and save
3. QR code is automatically regenerated with new template

## Template Rendering

### Backend

**MerchantNameTemplateService** (`app/Services/MerchantNameTemplateService.php`):
- Replaces template variables with actual values
- Handles orphaned separators (e.g., " - " at end)
- Supports uppercase transformation via config
- Provides fallback if template renders empty

Used in:
- `OmnipayPaymentGateway::generate()` - QR generation
- `GenerateQrCode` action - Display name in response

### Frontend

**MerchantNameTemplateComposer** (`resources/js/components/MerchantNameTemplateComposer.vue`):
- Text input for template with v-model binding
- Eye icon button to generate preview
- Preview with 25-character truncation
- Character count display
- Warning about GCash character limit

## Configuration

### Default Template

Set in migration default:
```php
$table->string('merchant_name_template')->default('{name} - {city}');
```

### Environment Variables

Optional config-based template (fallback if merchant template is null):

```bash
QR_MERCHANT_NAME_TEMPLATE="{name} - {city}"
QR_MERCHANT_NAME_UPPERCASE=false
QR_MERCHANT_NAME_FALLBACK="redeem-x"
```

Config file: `config/payment-gateway.php`

```php
'qr_merchant_name' => [
    'template' => env('QR_MERCHANT_NAME_TEMPLATE', '{name} - {city}'),
    'uppercase' => env('QR_MERCHANT_NAME_UPPERCASE', false),
    'fallback' => env('QR_MERCHANT_NAME_FALLBACK', config('app.name')),
],
```

## Character Limit

**GCash displays a maximum of 25 characters** for merchant names. The preview component automatically truncates to this limit to match the actual display.

### Examples

| Template | Rendered | GCash Display (25 char) |
|----------|----------|-------------------------|
| `{name} - {city}` | `3NETI R&D OPC - MANILA` | `3NETI R&D OPC - MANILA` ✓ |
| `{name} - {city}` | `REDEEM-X LESTER B. HURTADO - QUEZON CITY` | `REDEEM-X LESTER B. HURTAD` (truncated) |
| `{app_name}` | `REDEEM-X` | `REDEEM-X` ✓ |

## API Integration

### Update Merchant Profile

**Endpoint**: `PUT /api/v1/merchant/profile`

**Request**:
```json
{
  "merchant_name_template": "{name} | {city}"
}
```

**Validation**: 
- Type: `string`
- Max length: `255`
- Nullable: `yes`

## Migration

The `merchant_name_template` field was added to the `merchants` table migration. Run fresh migrations:

```bash
php artisan migrate:fresh --seed
```

## Files Changed

### Backend
- `packages/payment-gateway/database/migrations/1999_03_17_000000_create_merchants_table.php`
- `packages/payment-gateway/src/Models/Merchant.php`
- `app/Services/MerchantNameTemplateService.php` (new)
- `app/Services/MerchantService.php`
- `app/Http/Controllers/Api/MerchantProfileController.php`
- `app/Actions/Api/Wallet/GenerateQrCode.php`
- `packages/payment-gateway/src/Gateways/Omnipay/OmnipayPaymentGateway.php`
- `packages/payment-gateway/config/payment-gateway.php`

### Frontend
- `resources/js/components/MerchantNameTemplateComposer.vue` (new)
- `resources/js/pages/settings/Profile.vue`
- `resources/js/pages/Wallet/Load.vue`

## Best Practices

1. **Keep it short**: Aim for templates that render under 25 characters
2. **Test before saving**: Use the preview button to see actual display
3. **Use separators wisely**: Choose separators that work when truncated (e.g., `-` better than `•`)
4. **Consider all fields**: Think about how long values can be when combined

## Troubleshooting

### Template not saving
- Ensure you click the preview button (it triggers save)
- Check browser console for API errors
- Verify merchant profile exists

### Preview shows different from QR
- Preview should match GCash display exactly (with 25-char truncation)
- If mismatched, clear cache: `php artisan cache:clear`
- Regenerate QR code

### Characters corrupted in QR
- Avoid special Unicode characters (e.g., `•` can become `�`)
- Stick to ASCII characters, dashes, pipes, commas
- Test in GCash after generating QR
