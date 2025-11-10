# Finalize Page Implementation

## Overview
Implemented the Finalize (review) page for the redemption flow. This page allows users to review all collected data and confirm the voucher redemption before final submission.

## Features

### 1. Data Collection & Processing
- **Voucher Data**: Fetches amount and currency from VoucherData DTO (instructions.cash)
- **Session Data**: Merges mobile, country, bank account, and collected inputs from sessionStorage
- **Location Parsing**: Automatically parses JSON location data and displays formatted address
- **Image Handling**: Displays captured items (selfie, signature, location) as badges with icons

### 2. Configurable Layout
The Finalize page follows the same configuration pattern as Start, Success, and Wallet pages.

**Configuration Structure:**
```php
'finalize' => [
    // Header section
    'show_header' => true,
    'header' => [
        'show_title' => true,
        'title' => 'Review Your Redemption',
        'show_description' => true,
        'description' => 'Please verify all details before confirming',
    ],

    // Summary table
    'show_summary_table' => true,
    'summary_table' => [
        'show_header' => false,
        'show_title' => false,
        'show_description' => false,
        
        // Row visibility controls
        'show_voucher_code' => true,
        'voucher_code_label' => 'Voucher Code',
        
        'show_amount' => true,
        'amount_label' => 'Amount',
        
        'show_mobile' => true,
        'mobile_label' => 'Mobile Number',
        
        'show_bank_account' => true,
        'bank_account_label' => 'Bank Account',
        
        'show_collected_inputs' => true,
        'show_captured_items' => true,
        'captured_items_label' => 'Captured Items',
        
        'show_copy_buttons' => true,
    ],

    // Confirmation notice
    'show_confirmation_notice' => true,
    'confirmation_notice' => [
        'show_title' => true,
        'title' => 'Important:',
        'show_message' => true,
        'message' => 'By confirming, you agree that the information provided is accurate...',
    ],

    // Action buttons
    'show_action_buttons' => true,
    'action_buttons' => [
        'show_back_button' => true,
        'back_button_text' => 'Back',
        'show_confirm_button' => true,
        'confirm_button_text' => 'Confirm Redemption',
        'confirm_button_processing_text' => 'Processing...',
    ],
]
```

### 3. Environment Variables
All configuration options can be overridden via `.env`:
```
REDEEM_FINALIZE_SHOW_HEADER=true
REDEEM_FINALIZE_SHOW_TITLE=true
REDEEM_FINALIZE_TITLE=Review Your Redemption
REDEEM_FINALIZE_SHOW_DESCRIPTION=true
REDEEM_FINALIZE_DESCRIPTION=Please verify all details before confirming

# Summary table controls
REDEEM_FINALIZE_SHOW_SUMMARY_TABLE=true
REDEEM_FINALIZE_SHOW_VOUCHER_CODE=true
REDEEM_FINALIZE_VOUCHER_CODE_LABEL=Voucher Code
REDEEM_FINALIZE_SHOW_AMOUNT=true
REDEEM_FINALIZE_AMOUNT_LABEL=Amount
REDEEM_FINALIZE_SHOW_MOBILE=true
REDEEM_FINALIZE_MOBILE_LABEL=Mobile Number
REDEEM_FINALIZE_SHOW_BANK_ACCOUNT=true
REDEEM_FINALIZE_BANK_ACCOUNT_LABEL=Bank Account
REDEEM_FINALIZE_SHOW_COLLECTED_INPUTS=true
REDEEM_FINALIZE_SHOW_CAPTURED_ITEMS=true
REDEEM_FINALIZE_CAPTURED_ITEMS_LABEL=Captured Items
REDEEM_FINALIZE_SHOW_COPY_BUTTONS=true

# Confirmation notice
REDEEM_FINALIZE_SHOW_CONFIRMATION_NOTICE=true
REDEEM_FINALIZE_CONFIRMATION_SHOW_TITLE=true
REDEEM_FINALIZE_CONFIRMATION_TITLE=Important:
REDEEM_FINALIZE_CONFIRMATION_SHOW_MESSAGE=true
REDEEM_FINALIZE_CONFIRMATION_MESSAGE=By confirming, you agree...

# Action buttons
REDEEM_FINALIZE_SHOW_ACTION_BUTTONS=true
REDEEM_FINALIZE_SHOW_BACK_BUTTON=true
REDEEM_FINALIZE_BACK_BUTTON_TEXT=Back
REDEEM_FINALIZE_SHOW_CONFIRM_BUTTON=true
REDEEM_FINALIZE_CONFIRM_BUTTON_TEXT=Confirm Redemption
REDEEM_FINALIZE_CONFIRM_BUTTON_PROCESSING_TEXT=Processing...
```

### 4. Layout & Design
- **Responsive**: Centered, full-height layout matching Start, Success, and Wallet pages
- **Mobile-friendly**: `max-w-md` container with responsive padding (`p-6 md:p-10`)
- **Table Layout**: Simple two-column key-value table for easy data review
- **Copy Functionality**: Quick-copy buttons for voucher code and mobile number
- **Visual Feedback**: Badge indicators for captured items (location, selfie, signature)

## Data Flow

```
Wallet.vue
  ↓ (stores: mobile, country, bank_code, account_number)
  ↓
Inputs.vue (if needed)
  ↓ (adds: name, email, and other text inputs)
  ↓
Location.vue / Selfie.vue / Signature.vue (if needed)
  ↓ (adds: location, selfie, signature)
  ↓
Finalize.vue ← Fetches amount/currency from VoucherData DTO
  ↓ (merges sessionStorage data with API voucher data)
  ↓
ConfirmRedemption API ← Executes final redemption
  ↓
Success.vue
```

## Files Modified

### Backend
- **`app/Http/Controllers/Redeem/RedeemController.php`**
  - Updated `finalize()` method to pass config to Inertia

- **`app/Actions/Api/Redemption/FinalizeRedemption.php`**
  - Updated to use `VoucherData::fromModel()` for computed amount and currency from instructions.cash

- **`config/redeem.php`**
  - Added new `finalize` configuration section with all customizable options

### Frontend
- **`resources/js/pages/Redeem/Finalize.vue`**
  - Refactored to use simple table layout (key → value)
  - Added config-driven visibility controls for all UI elements
  - Implemented data processors for special fields (location JSON parsing, image handling)
  - Updated layout to match Start/Success/Wallet pattern (centered, full-height)
  - Removed PublicLayout in favor of direct div layout
  - Added computed properties for:
    - `processedInputs`: Parses location JSON, filters images
    - `capturedItems`: Displays badges for location/selfie/signature
    - `capturedItemsText`: Show/hide captured items row

## Key Improvements

1. **Data Accuracy**: Amount now sourced from `instructions.cash.amount` via VoucherData DTO
2. **Flexibility**: Entire page layout is configurable via config/env
3. **User Experience**: 
   - Simplified table layout for easy review
   - Copy-to-clipboard for code and mobile
   - Visual feedback for captured items
4. **Consistency**: Matches design patterns of other redemption pages
5. **Mobile-Friendly**: Responsive layout works on all screen sizes

## Usage Example

### Default Configuration
No changes needed - uses defaults from `config/redeem.php`.

### Minimal View
```php
'finalize' => [
    'show_header' => false,
    'show_confirmation_notice' => false,
    'summary_table' => [
        'show_copy_buttons' => false,
        'show_collected_inputs' => false,
        'show_captured_items' => false,
    ],
]
```

### Custom Labels
```env
REDEEM_FINALIZE_TITLE=Verify Your Details
REDEEM_FINALIZE_VOUCHER_CODE_LABEL=Code
REDEEM_FINALIZE_AMOUNT_LABEL=Value
REDEEM_FINALIZE_MOBILE_LABEL=Phone
REDEEM_FINALIZE_CONFIRM_BUTTON_TEXT=Proceed
```

## Testing

1. **Redeem simple voucher** (no inputs, no location/selfie/signature)
   - Should show: Code, Amount, Mobile, Bank Account, Confirm button

2. **Redeem voucher with text inputs**
   - Should show: All above + collected input fields

3. **Redeem voucher with location/selfie/signature**
   - Should show: All above + "Captured Items" row with badges

4. **Test configuration overrides**
   - Disable specific rows via config
   - Change labels and button text
   - Hide header, notice, or buttons

## Notes

- Location data is automatically parsed from JSON string to formatted address
- Image data (selfie, signature) are displayed as badges (not as data strings)
- The page does not execute redemption - it only confirms and displays data
- Actual redemption happens via API after user confirms
