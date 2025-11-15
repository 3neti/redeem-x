# Merchant Profile & Dynamic QR Codes

## Overview

The Merchant Profile feature allows users to customize how their name and business information appears when others scan their QR codes for wallet loading. Users can configure their QR codes to have either fixed amounts or dynamic amounts (where the payer chooses the amount).

## Features

### Merchant Profile Settings

Located at **Settings > Profile > Merchant Profile**, users can configure:

- **Display Name** - The name shown when QR code is scanned (required)
- **City** - Optional city/location information
- **Description** - Brief description of business or purpose
- **Business Category** - Select from 8 standard merchant category codes:
  - General/Personal (0000)
  - Eating Places/Restaurants (5812)
  - Grocery Stores (5411)
  - Furniture/Home Furnishings (5712)
  - Department Stores (5311)
  - Personal Services (7299)
  - Professional Services (8099)
  - Retail/Miscellaneous (5999)

### Dynamic vs Fixed Amount QR Codes

**Dynamic Amount (Recommended for Most Users)**
- Checkbox: "Dynamic Amount"
- When enabled: QR codes are generated without a fixed amount
- Benefit: Payers can choose how much to send
- Use case: General purpose wallet loading, tips, donations

**Fixed Amount**
- When "Dynamic Amount" is unchecked, you can set:
  - **Default Amount** - Pre-filled amount when QR is scanned
  - **Min Amount** - Minimum allowed amount (optional)
  - **Max Amount** - Maximum allowed amount (optional)
  - **Allow Tips** - Let payers add a tip on top of the amount

## Database Schema

### Merchants Table

```php
Schema::create('merchants', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('name');
    $table->string('city')->nullable();
    $table->text('description')->nullable();
    $table->string('merchant_category_code', 4)->default('0000');
    $table->string('logo_url')->nullable();
    $table->boolean('allow_tip')->default(false);
    $table->boolean('is_dynamic')->default(false); // NEW: Controls dynamic QR behavior
    $table->decimal('default_amount', 10, 2)->nullable();
    $table->decimal('min_amount', 10, 2)->nullable();
    $table->decimal('max_amount', 10, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Key Field: `is_dynamic`

- **Type**: Boolean (default: false)
- **Purpose**: Determines if QR codes should be generated with or without a fixed amount
- **Logic**: 
  - `is_dynamic = true` → QR code generated with amount = 0 (dynamic)
  - `is_dynamic = false` → QR code uses `default_amount` if specified

## Backend Implementation

### Models

**Merchant Model** (`packages/payment-gateway/src/Models/Merchant.php`)
- Fillable fields include `is_dynamic`
- Cast `is_dynamic` as boolean
- Helper methods:
  - `getCategoryCodes()` - Returns array of category codes
  - `getCategoryNameAttribute()` - Returns human-readable category name
  - `hasAmountRestrictions()` - Checks if min/max amounts are set
  - `isAmountValid($amount)` - Validates amount against min/max

### Services

**MerchantService** (`app/Services/MerchantService.php`)
- `getMerchantProfile(User $user)` - Get merchant for user
- `updateMerchantProfile(User $user, array $data)` - Update merchant data
- `clearUserQrCache(User $user)` - Clear cached QR codes when merchant changes
- `getCategoryCodes()` - Get available categories
- `validateMerchantData(array $data)` - Validate and clean merchant data

### API Endpoints

**GET** `/api/v1/merchant/profile`
- Returns merchant profile and available categories
- Auto-creates merchant if doesn't exist

**PUT** `/api/v1/merchant/profile`
- Updates merchant profile
- Validation rules:
  - `name`: string, max 255
  - `city`: nullable string, max 100
  - `description`: nullable string, max 500
  - `merchant_category_code`: string, size 4
  - `is_dynamic`: boolean
  - `default_amount`: nullable numeric, min 0, max 999999
  - `min_amount`: nullable numeric, min 0, max 999999
  - `max_amount`: nullable numeric, min 0, max 999999
  - `allow_tip`: boolean

### QR Code Generation

**GenerateQrCode Action** (`app/Actions/Api/Wallet/GenerateQrCode.php`)

```php
// Get merchant profile
$merchant = $user->getOrCreateMerchant();

// Use default_amount ONLY if not dynamic and amount is set
if ($amountValue === 0.0 && !$merchant->is_dynamic && $merchant->default_amount) {
    $amountValue = (float) $merchant->default_amount;
}
```

**Key Logic:**
1. If request specifies an amount, use it
2. If no amount AND merchant is NOT dynamic AND has default_amount, use default_amount
3. Otherwise, use 0 (dynamic)

### QR Code Caching

- **Cache Key**: `qr_code:{user_id}:dynamic` or `qr_code:{user_id}:{amount}`
- **TTL**: 1 hour (configurable via `PAYMENT_GATEWAY_QR_CACHE_TTL`)
- **Auto-clear**: Cache cleared when merchant profile is updated

## Frontend Implementation

### Components

**Profile Settings** (`resources/js/pages/settings/Profile.vue`)

The merchant profile form includes:
- Dynamic Amount checkbox that enables/disables amount fields
- All merchant fields bound to `merchantForm.value`
- Auto-loads merchant data on mount
- Real-time field disabling when dynamic mode is toggled

```vue
<template>
  <!-- Dynamic Amount Toggle -->
  <input 
    type="checkbox" 
    v-model="merchantForm.is_dynamic"
  />
  
  <!-- Amount fields disabled when is_dynamic is true -->
  <Input 
    v-model="merchantForm.default_amount"
    :disabled="merchantForm.is_dynamic"
  />
</template>
```

### Composables

**useQrGeneration** (`resources/js/composables/useQrGeneration.ts`)
- Handles QR code generation via API
- Manages loading and error states
- Supports force regenerate to bypass cache

## User Workflows

### Setup Merchant Profile (First Time)

1. Navigate to **Settings > Profile**
2. Scroll to **Merchant Profile** section
3. Fill in Display Name (required)
4. Optionally add City, Description, and Category
5. Choose between:
   - Check **"Dynamic Amount"** for flexible QR codes
   - Uncheck and set specific amounts for fixed QR codes
6. Click **"Save Merchant Profile"**

### Generate QR Code

1. Navigate to **Wallet > Load Wallet**
2. QR code is automatically generated using merchant settings
3. If dynamic: QR code has no fixed amount
4. If fixed: QR code shows default amount (if set)
5. Share QR via multiple methods (copy, download, email, SMS, WhatsApp)

### Switch Between Dynamic and Fixed

1. Go to **Settings > Profile > Merchant Profile**
2. Toggle **"Dynamic Amount"** checkbox
   - Checked: Amount fields are disabled, QR will be dynamic
   - Unchecked: Amount fields are enabled, can set specific amounts
3. Click **"Save Merchant Profile"**
4. Go to **Load Wallet** and regenerate QR code to see changes

## Technical Notes

### Why `is_dynamic` Flag?

Instead of using `null` amounts to indicate dynamic QR codes, we use a dedicated boolean flag. This approach:
- ✅ Preserves amount values when switching between dynamic/fixed modes
- ✅ Allows proper validation on amount fields
- ✅ Makes the intent explicit and easier to understand
- ✅ Avoids null-handling complexity in backend/frontend
- ✅ Simplifies the QR generation logic

### Migration Strategy

The `is_dynamic` field was added to the existing merchants migration:

```php
$table->boolean('is_dynamic')->default(false);
```

**Important**: After updating the migration, delete the schema cache:

```bash
rm database/schema/sqlite-schema.sql
php artisan migrate:fresh --seed
```

### Package Structure

The Merchant functionality lives in the `lbhurtado/payment-gateway` package:
- **Migrations**: `packages/payment-gateway/database/migrations/`
- **Model**: `packages/payment-gateway/src/Models/Merchant.php`
- **Traits**: `packages/payment-gateway/src/Traits/HasMerchant.php`

The package migrations are auto-loaded via `PaymentGatewayServiceProvider::boot()`.

## Configuration

### Environment Variables

```bash
# QR code cache TTL (seconds)
PAYMENT_GATEWAY_QR_CACHE_TTL=3600

# Use Omnipay implementation (recommended)
USE_OMNIPAY=true
```

## Testing

### Manual Testing

1. **Test Dynamic QR**:
   - Enable "Dynamic Amount" in merchant profile
   - Save and generate QR code
   - Verify QR has no fixed amount when scanned

2. **Test Fixed Amount QR**:
   - Disable "Dynamic Amount"
   - Set default_amount to 100
   - Save and generate QR code
   - Verify QR shows ₱100 when scanned

3. **Test Profile Updates**:
   - Change merchant name
   - Save profile
   - Regenerate QR code (force)
   - Verify new name appears when QR is scanned

### Database Verification

```sql
-- Check merchant data
SELECT id, name, city, is_dynamic, default_amount FROM merchants;

-- Verify user has merchant
SELECT users.name, merchants.name as merchant_name, merchants.is_dynamic 
FROM users 
LEFT JOIN merchants ON users.id = merchants.id;
```

## Troubleshooting

### QR Code Shows Old Merchant Name

**Solution**: Click "Regenerate QR Code" button to bypass cache, or wait for cache to expire (1 hour by default).

### Amount Fields Won't Update

**Issue**: Schema cache might be stale  
**Solution**: 
```bash
rm database/schema/sqlite-schema.sql
php artisan migrate:fresh --seed
```

### "Unable to cast value to decimal" Error

**Issue**: Old migration used `decimal:2` cast which doesn't handle null well  
**Solution**: Migration now uses `float` cast which properly handles nullable values

## Future Enhancements

- [ ] Logo upload for merchant profile
- [ ] Multiple QR code templates (static vs dynamic saved separately)
- [ ] QR code analytics (scan tracking)
- [ ] Merchant verification system
- [ ] Custom QR code branding/colors
