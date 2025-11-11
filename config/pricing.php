<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pricing Categories
    |--------------------------------------------------------------------------
    |
    | Define categories for organizing pricing items in the admin panel.
    | Each category includes a display name, description, and display order.
    |
    */

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
