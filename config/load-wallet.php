<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load Wallet Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the Load Wallet page.
    | Control what cards/sections are displayed and customize all labels/text.
    |
    */

    // Page Header
    'header' => [
        'title' => env('LOAD_WALLET_TITLE', 'Load Your Wallet'),
        'show_balance' => env('LOAD_WALLET_SHOW_BALANCE', true),
        'balance_prefix' => env('LOAD_WALLET_BALANCE_PREFIX', 'Current Balance:'),
    ],

    // QR Display Card
    'qr_card' => [
        'show' => env('LOAD_WALLET_SHOW_QR_CARD', true),
        'title' => env('LOAD_WALLET_QR_TITLE', 'QR Code'),
        'description' => env('LOAD_WALLET_QR_DESCRIPTION', 'Scan to load.'),
        'show_regenerate_button' => env('LOAD_WALLET_SHOW_REGENERATE_BUTTON', true),
        'regenerate_button_text' => env('LOAD_WALLET_REGENERATE_BUTTON_TEXT', 'Regenerate QR Code'),
        'regenerate_button_loading_text' => env('LOAD_WALLET_REGENERATE_BUTTON_LOADING_TEXT', 'Generating...'),
    ],

    // Display Settings Card
    'display_settings_card' => [
        'show' => env('LOAD_WALLET_SHOW_DISPLAY_SETTINGS', true),
        'title' => env('LOAD_WALLET_DISPLAY_SETTINGS_TITLE', 'Display Settings'),
        'description' => env('LOAD_WALLET_DISPLAY_SETTINGS_DESCRIPTION', 'Customize how your name appears on QR codes'),
    ],

    // Amount Settings Card
    'amount_settings_card' => [
        'show' => env('LOAD_WALLET_SHOW_AMOUNT_SETTINGS', true),
        'title' => env('LOAD_WALLET_AMOUNT_SETTINGS_TITLE', 'Amount Settings'),
        'description' => env('LOAD_WALLET_AMOUNT_SETTINGS_DESCRIPTION', 'These settings control your wallet-load QR behavior'),
        'show_save_button' => env('LOAD_WALLET_SHOW_SAVE_BUTTON', true),
        'save_button_text' => env('LOAD_WALLET_SAVE_BUTTON_TEXT', 'Save Amount Settings'),
        'save_button_loading_text' => env('LOAD_WALLET_SAVE_BUTTON_LOADING_TEXT', 'Saving...'),
    ],

    // Share Panel
    'share_panel' => [
        'show' => env('LOAD_WALLET_SHOW_SHARE_PANEL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public QR Page Configuration (/load/{uuid})
    |--------------------------------------------------------------------------
    |
    | Configure the public-facing QR code display page.
    | This page is accessible without authentication.
    |
    */

    'public' => [
        'show_logo' => env('LOAD_WALLET_PUBLIC_SHOW_LOGO', true),
        
        // Page title (uses merchant name dynamically)
        'title_prefix' => env('LOAD_WALLET_PUBLIC_TITLE_PREFIX', 'Pay'),
        
        // Merchant info display
        'show_merchant_name' => env('LOAD_WALLET_PUBLIC_SHOW_MERCHANT_NAME', true),
        'show_merchant_city' => env('LOAD_WALLET_PUBLIC_SHOW_MERCHANT_CITY', true),
        
        // Instructions section
        'instruction_title' => env('LOAD_WALLET_PUBLIC_INSTRUCTION_TITLE', 'Scan to Pay'),
        'instruction_description' => env('LOAD_WALLET_PUBLIC_INSTRUCTION_DESCRIPTION', 'Use your GCash, PayMaya, or any QR Ph compatible app'),
        
        // Footer
        'show_footer' => env('LOAD_WALLET_PUBLIC_SHOW_FOOTER', true),
        'footer_text' => env('LOAD_WALLET_PUBLIC_FOOTER_TEXT', null), // null = "Powered by {app_name}"
    ],

];
