# Public QR Load Page

This document describes the public-facing QR code display page accessible via `/load/{uuid}`.

## Overview

The public QR load page provides a shareable, authentication-free URL where anyone can view a merchant's QR code for payment. Each merchant has a unique UUID-based URL that can be shared publicly.

## URL Format

```
/load/{uuid}
```

Example: `https://redeem-x.test/load/550e8400-e29b-41d4-a716-446655440000`

## Features

- **No authentication required** - Public access for anyone with the URL
- **Unique per merchant** - Each merchant has a UUID-based URL
- **Non-guessable** - UUIDs are secure and cannot be enumerated
- **Shareable** - Can be embedded in websites, QR codes, or shared via links
- **Configurable** - All elements can be customized via environment variables
- **Minimal design** - Clean, focused UI showing only the QR code

## Implementation

### Database

**Merchants Table** - Added UUID column:

```php
$table->uuid('uuid')->unique()->index();
```

- Auto-generated on merchant creation via `HasUuids` trait
- Unique and indexed for fast lookups
- Separate from primary key `id`

### Backend

**Route**: `routes/web.php`

```php
Route::get('/load/{uuid}', \App\Http\Controllers\Wallet\LoadPublicController::class)
    ->name('load.public');
```

- No authentication middleware
- Public access

**Controller**: `app/Http/Controllers/Wallet/LoadPublicController.php`

```php
public function __invoke(Request $request, string $uuid): Response
{
    $merchant = Merchant::where('uuid', $uuid)->firstOrFail();
    
    return Inertia::render('Wallet/LoadPublic', [
        'merchantUuid' => $uuid,
        'merchantName' => $merchant->name,
        'merchantCity' => $merchant->city,
        'config' => config('load-wallet.public', []),
    ]);
}
```

### Frontend

**Component**: `resources/js/pages/Wallet/LoadPublic.vue`

Features:
- Gradient background
- Centered card layout
- App logo (optional)
- Merchant name and city (optional)
- QR code display
- Scan instructions
- Powered by footer (optional)

### Integration

**QR Generation** - `app/Actions/Api/Wallet/GenerateQrCode.php`:

```php
$shareableUrl = route('load.public', ['uuid' => $merchant->uuid]);
```

The shareable URL in QR generation responses now points to the public page.

## Configuration

**Config File**: `config/load-wallet.php`

```php
'public' => [
    'show_logo' => env('LOAD_WALLET_PUBLIC_SHOW_LOGO', true),
    'title_prefix' => env('LOAD_WALLET_PUBLIC_TITLE_PREFIX', 'Pay'),
    'show_merchant_name' => env('LOAD_WALLET_PUBLIC_SHOW_MERCHANT_NAME', true),
    'show_merchant_city' => env('LOAD_WALLET_PUBLIC_SHOW_MERCHANT_CITY', true),
    'instruction_title' => env('LOAD_WALLET_PUBLIC_INSTRUCTION_TITLE', 'Scan to Pay'),
    'instruction_description' => env('LOAD_WALLET_PUBLIC_INSTRUCTION_DESCRIPTION', 'Use your GCash, PayMaya, or any QR Ph compatible app'),
    'show_footer' => env('LOAD_WALLET_PUBLIC_SHOW_FOOTER', true),
    'footer_text' => env('LOAD_WALLET_PUBLIC_FOOTER_TEXT', null),
],
```

### Environment Variables

Add to `.env` to customize:

```bash
# Show/hide elements
LOAD_WALLET_PUBLIC_SHOW_LOGO=true
LOAD_WALLET_PUBLIC_SHOW_MERCHANT_NAME=true
LOAD_WALLET_PUBLIC_SHOW_MERCHANT_CITY=true
LOAD_WALLET_PUBLIC_SHOW_FOOTER=true

# Text customization
LOAD_WALLET_PUBLIC_TITLE_PREFIX="Pay"
LOAD_WALLET_PUBLIC_INSTRUCTION_TITLE="Scan to Pay"
LOAD_WALLET_PUBLIC_INSTRUCTION_DESCRIPTION="Use your GCash, PayMaya, or any QR Ph compatible app"

# Custom footer (leave unset for "Powered by {app_name}")
LOAD_WALLET_PUBLIC_FOOTER_TEXT="Custom footer text"
```

## Usage Examples

### Default Configuration

Shows all elements with default text:
- App logo
- Merchant name and city
- QR code
- "Scan to Pay" instructions
- "Powered by redeem-x" footer

### Minimal (QR Only)

```bash
LOAD_WALLET_PUBLIC_SHOW_LOGO=false
LOAD_WALLET_PUBLIC_SHOW_MERCHANT_NAME=false
LOAD_WALLET_PUBLIC_SHOW_MERCHANT_CITY=false
LOAD_WALLET_PUBLIC_SHOW_FOOTER=false
```

Shows only the QR code in a card.

### White-label

```bash
LOAD_WALLET_PUBLIC_FOOTER_TEXT="© 2025 Your Company Name"
LOAD_WALLET_PUBLIC_INSTRUCTION_TITLE="Send Payment"
```

### Multi-language

```bash
LOAD_WALLET_PUBLIC_INSTRUCTION_TITLE="I-scan para magbayad"
LOAD_WALLET_PUBLIC_INSTRUCTION_DESCRIPTION="Gamitin ang iyong GCash o PayMaya"
LOAD_WALLET_PUBLIC_FOOTER_TEXT="Pinapagana ng Kompanya Ko"
```

## User Flow

### Merchant Side

1. Login to `/wallet/load`
2. Generate QR code
3. View shareable URL in share panel: `/load/{uuid}`
4. Share URL via:
   - Copy/paste to social media
   - Email or SMS
   - Embed in website
   - Print on marketing materials

### Payer Side

1. Receive URL: `/load/{uuid}`
2. Open in browser (no login required)
3. See merchant's QR code
4. Scan with GCash/PayMaya
5. Complete payment

## Security

### UUID Benefits

- **Non-guessable**: Cannot enumerate merchant pages
- **Secure**: 128-bit random identifier
- **Unique**: Guaranteed uniqueness per merchant
- **Public-safe**: Safe to share publicly

### No Sensitive Data

The public page only displays:
- Merchant name (public information)
- City (public information)
- QR code (payment request only)

No sensitive merchant or user data is exposed.

## Use Cases

### 1. Social Media Bio

Add the URL to Instagram/Facebook bio for easy payments.

### 2. Website Embedding

Embed via iframe:

```html
<iframe src="https://yourapp.com/load/{uuid}" 
        width="400" 
        height="600"
        frameborder="0">
</iframe>
```

### 3. Printed Materials

Generate a QR code that links to the URL for:
- Business cards
- Flyers
- Store signage
- Receipts

### 4. Payment Links

Share directly via:
- WhatsApp: "Pay me here: https://..."
- Email signature
- SMS

## Files Changed

### Backend
- `packages/payment-gateway/database/migrations/1999_03_17_000000_create_merchants_table.php` - Added uuid column
- `packages/payment-gateway/src/Models/Merchant.php` - Added HasUuids trait and uniqueIds() method
- `app/Http/Controllers/Wallet/LoadPublicController.php` (new) - Public page controller
- `routes/web.php` - Added public route
- `app/Actions/Api/Wallet/GenerateQrCode.php` - Updated shareable URL
- `config/load-wallet.php` - Added public page configuration

### Frontend
- `resources/js/pages/Wallet/LoadPublic.vue` (new) - Public QR display page

### Documentation
- `docs/PUBLIC_QR_LOAD_PAGE.md` (new) - This document

### Configuration
- `.env.example` - Added public page environment variables

## Migration

The `uuid` column was added to the merchants table. Run:

```bash
php artisan migrate:fresh --seed
```

All existing merchants will receive UUIDs automatically via the `HasUuids` trait.

## Testing

1. **Login**: `/dev-login/lester@hurtado.ph`
2. **Generate QR**: Visit `/wallet/load`
3. **Get URL**: Check share panel for `/load/{uuid}`
4. **Test public access**: 
   - Copy URL
   - Open in incognito/private window
   - Should see public QR page without login

## Troubleshooting

### UUID not generated

**Issue**: Merchant created without UUID

**Solution**: The `HasUuids` trait requires the `uniqueIds()` method to specify which column. Verify:

```php
public function uniqueIds(): array
{
    return ['uuid'];
}
```

### 404 on public page

**Issue**: Route not found

**Solution**: 
- Clear route cache: `php artisan route:clear`
- Verify route exists: `php artisan route:list --path=load`

### Config not applying

**Issue**: Changes to `.env` not reflected

**Solution**:
- Clear config cache: `php artisan config:clear`
- Verify config: `php artisan tinker` then `config('load-wallet.public')`

## Future Enhancements

Potential additions:

- Analytics tracking (view counts, scan counts)
- Custom themes per merchant
- QR code customization (colors, logo overlay)
- Expiration dates for URLs
- Password protection option
- Custom slugs instead of UUIDs (e.g., `/pay/johndoe`)
