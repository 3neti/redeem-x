<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sidebar Balance Display Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the wallet balance display
    | in the application sidebar.
    |
    */

    'balance' => [
        // Show/hide the balance section in sidebar
        'show' => env('SIDEBAR_SHOW_BALANCE', true),

        // Label for the balance section
        'label' => env('SIDEBAR_BALANCE_LABEL', 'Wallet Balance'),

        // Display style: 'compact' or 'full'
        // compact: Shows only balance amount
        // full: Shows balance with label and additional info
        'style' => env('SIDEBAR_BALANCE_STYLE', 'compact'),

        // Show currency symbol
        'show_currency' => env('SIDEBAR_BALANCE_SHOW_CURRENCY', true),

        // Show wallet icon
        'show_icon' => env('SIDEBAR_BALANCE_SHOW_ICON', true),

        // Show refresh button
        'show_refresh_button' => env('SIDEBAR_BALANCE_SHOW_REFRESH', false),

        // Show last updated timestamp
        'show_last_updated' => env('SIDEBAR_BALANCE_SHOW_UPDATED', false),

        // Position: 'above-footer' or 'in-content'
        'position' => env('SIDEBAR_BALANCE_POSITION', 'above-footer'),
    ],

];
