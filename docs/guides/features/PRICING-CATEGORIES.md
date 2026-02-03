# Pricing Categories

This document describes how InstructionItems are categorized and displayed on the Admin Pricing page.

## Overview
- Each InstructionItem has a `meta.category` value used to group and display items in the admin UI.
- Categories are defined in `config/pricing.php` under `categories`.
- The Admin Pricing page (`/admin/pricing`) groups items into Cards per category, with icons and descriptions.

## Category Config

Location: `config/pricing.php`

```
return [
    'categories' => [
        'base' => [
            'name' => 'Base Charges',
            'description' => 'Core voucher generation costs',
            'icon' => 'dollar-sign',
            'order' => 1,
        ],
        'input_fields' => [
            'name' => 'Input Fields',
            'description' => 'Charges for collecting user information during redemption',
            'icon' => 'file-text',
            'order' => 2,
        ],
        'feedback' => [
            'name' => 'Feedback Channels',
            'description' => 'Notification and webhook charges',
            'icon' => 'bell',
            'order' => 3,
        ],
        'validation' => [
            'name' => 'Validation Rules',
            'description' => 'Security and validation features',
            'icon' => 'shield-check',
            'order' => 4,
        ],
        'rider' => [
            'name' => 'Rider Features',
            'description' => 'Post-redemption messages and redirects',
            'icon' => 'message-square',
            'order' => 5,
        ],
        'other' => [
            'name' => 'Other',
            'description' => 'Miscellaneous pricing items',
            'icon' => 'folder',
            'order' => 99,
        ],
    ],
];
```

## Where Category Comes From
- Seeded from `config/redeem.php` `pricelist` (each item now includes `category`)
- Stored into `instruction_items.meta.category` by `InstructionItemSeeder`
- Exposed via accessor `InstructionItem::getCategoryAttribute()`

## UI Grouping
- Controller: `App/Http/Controllers/Admin/PricingController@index`
  - Groups items by `category`
  - Sorts category groups by `config('pricing.categories')[category].order`
  - Sends both grouped items and category config to Inertia
- View Component: `resources/js/pages/admin/pricing/Index.vue`
  - Renders a Card per category with icon, title, description
  - Displays a table of items inside each category card

## Updating Categories
1. Edit category labels/descriptions/icons in `config/pricing.php`
2. To change item category defaults, edit `config/redeem.php` `pricelist` entries
3. Re-seed to apply category changes to DB (updates existing records):

```
php artisan db:seed --class=InstructionItemSeeder
```

Note: Admins can still edit item labels/prices individually at `/admin/pricing/{id}/edit`.
