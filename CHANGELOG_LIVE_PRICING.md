# Live Pricing System Implementation

## Overview
Implemented a comprehensive live pricing system that dynamically calculates voucher generation costs based on database-driven pricing rules. The system provides real-time cost breakdowns across the application.

## Features

### 1. Backend Pricing Infrastructure

#### Database-Driven Pricing
- **InstructionItem Model**: Stores pricing rules for all voucher instruction components
- **Categories**: Organized pricing items into logical groups
  - `base` - Base Charges (amount-based)
  - `input_fields` - Required Input Fields
  - `feedback` - Feedback Channels (email, mobile, webhook)
  - `validation` - Validation Rules (secret, mobile restriction)
  - `rider` - Rider Features (message, redirect URL)
  - `other` - Miscellaneous charges

#### Pricing Evaluation Service
- **InstructionCostEvaluator** (`app/Services/InstructionCostEvaluator.php`)
  - Evaluates `VoucherInstructionsData` against pricing rules
  - Handles special cases:
    - Enum-to-string conversion for input fields
    - Case-insensitive field matching
    - Truthy value detection for optional features
  - Returns itemized breakdown with labels and formatted prices

#### API Endpoint
- **POST `/api/v1/calculate-charges`**
  - Accepts voucher instructions payload
  - Validates via `CalculateChargeRequest`
  - Returns breakdown in centavos with formatted display values
  - Response format:
    ```json
    {
      "breakdown": [
        {
          "index": "cash.amount",
          "label": "Cash Amount",
          "value": 50,
          "price": 5000,
          "price_formatted": "₱50.00",
          "currency": "PHP"
        }
      ],
      "total": 5000,
      "total_formatted": "₱50.00"
    }
    ```

### 2. Frontend Reactive Pricing

#### useChargeBreakdown Composable
- **Location**: `resources/js/composables/useChargeBreakdown.ts`
- **Features**:
  - Accepts reactive `instructions` ref
  - Debounced API calls (configurable, default 500ms)
  - Auto-calculation on payload changes (optional)
  - Loading and error states
  - Returns formatted breakdown with totals
- **Pattern** (from x-change codebase):
  - Watches computed payload ref for deep reactivity
  - Lodash debounce for efficient API calls
  - Skips calculation if amount is not set

#### Integration Points

**Settings > Campaigns (VoucherInstructionsForm.vue)**
- Computes `instructionsForPricing` from local form state
- Passes to `useChargeBreakdown` with auto-calculation enabled
- Displays reactive Cost Breakdown in sidebar
- Updates when any field changes (amount, inputs, feedback, rider, validation)

**Vouchers > Generate (Create.vue)** ✅ **NEW**
- Replaced hardcoded cost calculation with live API pricing
- Computes `instructionsForPricing` from all form refs
- Uses `useChargeBreakdown` composable for reactive updates
- UI improvements:
  - Loading state: "Calculating charges..."
  - Error state: "Error calculating charges. Using fallback pricing."
  - Detailed breakdown: Shows each pricing item with label and formatted price
  - Fallback: Simple base charge calculation if API unavailable
- Fully reactive across all fields:
  - Amount, Quantity
  - Input Fields (email, mobile, name, address)
  - Code Prefix, Mask, TTL
  - Feedback Channels (email, mobile, webhook)
  - Validation Rules (secret, mobile restriction)
  - Rider Features (message, redirect URL)

### 3. Admin Pricing Management

#### Pricing CRUD Page
- **Route**: `/admin/pricing`
- **Permissions**: `role:super-admin` + `permission:manage pricing`
- **Features**:
  - Grouped display by categories
  - Category cards with icons and descriptions
  - Edit pricing items
  - Enable/disable items
  - Backend-controlled category configuration

#### Category Configuration
- **Location**: `config/pricing.php`
- **Structure**:
  ```php
  'categories' => [
      'base' => [
          'name' => 'Base Charges',
          'description' => 'Core voucher charges',
          'icon' => 'DollarSign',
          'order' => 1,
      ],
      // ... more categories
  ]
  ```
- Categories are backend-controlled for consistency
- Icons mapped to Lucide Vue components in frontend

#### Seeding
- **InstructionItemSeeder**: Uses `updateOrCreate` to sync pricing rules
- Pricelist defined in `config/redeem.php` with category assignments
- Re-seeding updates existing items without data loss

### 4. Sidebar Navigation
- Added "Pricing" link in AppSidebar
- Visible to all users (view-only for non-admins)
- Route protection enforces edit permissions on backend

## Technical Implementation

### Data Flow
```
User modifies form field
  ↓
Vue reactivity triggers computed `instructionsForPricing`
  ↓
useChargeBreakdown watcher detects change
  ↓
Debounced API call to /api/v1/calculate-charges
  ↓
Backend validates and evaluates via InstructionCostEvaluator
  ↓
Returns itemized breakdown
  ↓
Frontend updates Cost Breakdown UI
```

### Key Files

#### Backend
- `app/Models/InstructionItem.php` - Pricing model with category accessor
- `app/Services/InstructionCostEvaluator.php` - Core evaluation logic
- `app/Actions/CalculateChargeAction.php` - API action
- `app/Http/Controllers/Api/ChargeCalculationController.php` - API controller
- `app/Http/Controllers/Admin/PricingController.php` - Admin CRUD
- `config/pricing.php` - Category configuration
- `config/redeem.php` - Pricelist definitions
- `database/seeders/InstructionItemSeeder.php` - Pricing seed data

#### Frontend
- `resources/js/composables/useChargeBreakdown.ts` - Reactive pricing composable
- `resources/js/components/voucher/forms/VoucherInstructionsForm.vue` - Campaign form with pricing
- `resources/js/pages/Vouchers/Generate/Create.vue` - Generate page with live pricing ✅ **NEW**
- `resources/js/pages/admin/pricing/Index.vue` - Admin pricing page
- `resources/js/components/AppSidebar.vue` - Navigation with Pricing link

### Special Handling

#### Input Fields Enum Conversion
Input fields are stored as `VoucherInputField` enums but sent to API as strings. The evaluator extracts string values from enum objects:
```php
if (Str::startsWith($priceIndex, 'inputs.fields.')) {
    $selectedFieldsExtracted = [];
    foreach ($selectedFields as $field) {
        if (is_array($field) || is_object($field)) {
            $fieldArray = (array)$field;
            $selectedFieldsExtracted[] = reset($fieldArray);
        }
    }
    $isSelected = in_array(strtoupper($fieldName), 
                          array_map('strtoupper', $selectedFieldsExtracted));
}
```

#### Checkbox Reactivity Fix
Reka-ui Checkbox required `@click` event instead of `@update:checked` to properly trigger v-model updates in `InputFieldsForm.vue`.

## Configuration

### Environment Variables
All pricing is database-driven; no specific env vars needed for pricing calculation. Admin access controlled via standard role/permission system.

### Adding New Pricing Rules
1. Add item to `config/redeem.php` pricelist with category
2. Run seeder: `php artisan db:seed --class=InstructionItemSeeder`
3. Backend automatically picks up new rules
4. Frontend displays in breakdown if applicable

## Benefits

1. **Accuracy**: Single source of truth in database
2. **Flexibility**: Change pricing without code deployment
3. **Transparency**: Users see itemized costs in real-time
4. **Consistency**: Same evaluation logic across all entry points
5. **User Experience**: Instant feedback as users configure vouchers
6. **Maintainability**: Centralized pricing logic in evaluator service

## Future Enhancements

- Dynamic pricing based on user tiers
- Volume discounts
- Promotional pricing rules
- Pricing history and audit logs
- Per-campaign pricing overrides
