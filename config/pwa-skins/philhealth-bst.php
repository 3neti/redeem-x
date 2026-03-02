<?php

/**
 * PhilHealth BST Kiosk Skin Configuration
 *
 * Complete kiosk configuration for PhilHealth BST vouchers.
 * Usage: /pwa/portal?skin=philhealth-bst
 * Published to: config/pwa-skins/philhealth-bst.php
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Kiosk Identity
    |--------------------------------------------------------------------------
    */
    'title' => 'PhilHealth POS',
    'subtitle' => 'Benefit Settlement Token',
    'voucher_type' => 'settlement',

    /*
    |--------------------------------------------------------------------------
    | Voucher Configuration
    |--------------------------------------------------------------------------
    */
    'config' => [
        'campaign' => 'philhealth-bst',
        'driver' => 'philhealth-bst@1.0.0',
        'amount' => 0,  // Hidden - no deposit required
        'target_amount' => null,  // User enters reimbursement amount
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Fields
    |--------------------------------------------------------------------------
    */
    'fields' => [
        // Required on redemption
        'inputs' => ['mobile', 'name'],
        // Collected at issuance (settlement payload)
        'payload' => ['reference_id', 'device_id'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Callbacks
    |--------------------------------------------------------------------------
    */
    'callbacks' => [
        'feedback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Labels & Theme
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'logo' => 'pwa/skins/philhealth-bst/logo.png',
        'theme_color' => '#0066cc',
        'amount_label' => 'Deposit Amount',
        'amount_placeholder' => 'Enter deposit amt',
        'target_label' => 'Reimbursement Amount',
        'target_placeholder' => 'Enter amount',
        'button_text' => 'Issue BST',
        'success_title' => 'BST Issued!',
        'success_message' => 'Present this QR code to the cashier for payment',
        'print_button' => 'Print Receipt',
        'new_button' => 'Issue Another BST',
        'error_title' => 'Issuance Failed',
        'retry_button' => 'Try Again',
    ],
];
