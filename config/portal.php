<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Portal Page Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the Portal page title and subtitle displayed to users.
    | These settings allow you to brand the instant voucher generation
    | experience according to your application's needs.
    |
    */

    'title' => env('PORTAL_TITLE', config('app.name')),

    'subtitle' => env('PORTAL_SUBTITLE', 'Generate vouchers instantly'),

    /*
    |--------------------------------------------------------------------------
    | Branding Configuration
    |--------------------------------------------------------------------------
    |
    | Control the visibility of branding elements on the Portal page.
    |
    */

    'branding' => [
        'show_logo' => env('PORTAL_SHOW_LOGO', true),
        'show_icon' => env('PORTAL_SHOW_ICON', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Labels & Text Configuration
    |--------------------------------------------------------------------------
    |
    | Customize all text labels and button captions on the Portal page.
    |
    */

    'labels' => [
        'amount_label' => env('PORTAL_AMOUNT_LABEL', 'Amount'),
        'wallet_balance_label' => env('PORTAL_WALLET_BALANCE_LABEL', 'Wallet Balance'),
        'balance_after_label' => env('PORTAL_BALANCE_AFTER_LABEL', 'Balance after'),
        'generate_button_text' => env('PORTAL_GENERATE_BUTTON_TEXT', 'Generate voucher'),
        'reset_button_title' => env('PORTAL_RESET_BUTTON_TITLE', 'Reset input'),
        'advanced_mode_link_text' => env('PORTAL_ADVANCED_MODE_LINK_TEXT', 'Need more options? →'),
        'show_advanced_mode_link' => env('PORTAL_SHOW_ADVANCED_MODE_LINK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quick Amounts Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the preset quick amount buttons that appear in the input field.
    | Amounts should be comma-separated integers (e.g., "100,200,500,1000").
    |
    */

    'quick_amounts' => [
        'enabled' => env('PORTAL_QUICK_AMOUNTS_ENABLED', true),
        'amounts' => array_map('intval', array_filter(explode(',', env('PORTAL_QUICK_AMOUNTS', '50,200,500,1000,2000,5000')))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Checkboxes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which input fields are available as checkboxes below the
    | amount input. Each input can be individually enabled/disabled and
    | customized with labels and icons.
    |
    */

    'inputs' => [
        'enabled' => env('PORTAL_INPUTS_ENABLED', true),
        'available' => [
            'otp' => [
                'enabled' => env('PORTAL_INPUT_OTP_ENABLED', true),
                'label' => env('PORTAL_INPUT_OTP_LABEL', 'OTP'),
                'icon' => env('PORTAL_INPUT_OTP_ICON', '🔢'),
            ],
            'selfie' => [
                'enabled' => env('PORTAL_INPUT_SELFIE_ENABLED', true),
                'label' => env('PORTAL_INPUT_SELFIE_LABEL', 'Selfie'),
                'icon' => env('PORTAL_INPUT_SELFIE_ICON', '📸'),
            ],
            'location' => [
                'enabled' => env('PORTAL_INPUT_LOCATION_ENABLED', true),
                'label' => env('PORTAL_INPUT_LOCATION_LABEL', 'Location'),
                'icon' => env('PORTAL_INPUT_LOCATION_ICON', '📍'),
            ],
            'signature' => [
                'enabled' => env('PORTAL_INPUT_SIGNATURE_ENABLED', true),
                'label' => env('PORTAL_INPUT_SIGNATURE_LABEL', 'Signature'),
                'icon' => env('PORTAL_INPUT_SIGNATURE_ICON', '✍️'),
            ],
            'kyc' => [
                'enabled' => env('PORTAL_INPUT_KYC_ENABLED', true),
                'label' => env('PORTAL_INPUT_KYC_LABEL', 'KYC'),
                'icon' => env('PORTAL_INPUT_KYC_ICON', '🆔'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payee Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the payee field (bank check metaphor: blank/CASH, mobile, or
    | vendor alias). Controls who can redeem the voucher.
    |
    */

    'payee' => [
        'enabled' => env('PORTAL_PAYEE_ENABLED', true),
        'show_icon' => env('PORTAL_PAYEE_SHOW_ICON', true),
        'show_label' => env('PORTAL_PAYEE_SHOW_LABEL', true),
        'label' => env('PORTAL_PAYEE_LABEL', 'Payee'),
        'placeholder' => env('PORTAL_PAYEE_PLACEHOLDER', 'CASH'),
        'help_text_anyone' => env('PORTAL_PAYEE_HELP_ANYONE', 'Anyone can redeem'),
        'help_text_mobile' => env('PORTAL_PAYEE_HELP_MOBILE', 'Restricted to mobile number'),
        'help_text_vendor' => env('PORTAL_PAYEE_HELP_VENDOR', 'Restricted to merchant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Success Page Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the success page shown after voucher generation.
    |
    */

    'success' => [
        'share_section_label' => env('PORTAL_SUCCESS_SHARE_LABEL', ''),
        'copy_button_text' => env('PORTAL_SUCCESS_COPY_BUTTON_TEXT', 'Copy Code'),
        'share_button_text' => env('PORTAL_SUCCESS_SHARE_BUTTON_TEXT', 'Share'),
        'create_another_button_text' => env('PORTAL_SUCCESS_CREATE_ANOTHER_TEXT', 'Create Another Voucher'),
        'dashboard_link_text' => env('PORTAL_SUCCESS_DASHBOARD_LINK_TEXT', 'Go to Dashboard →'),
        'show_dashboard_link' => env('PORTAL_SUCCESS_SHOW_DASHBOARD_LINK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modal Configuration
    |--------------------------------------------------------------------------
    |
    | Customize text in the top-up and confirmation modals.
    |
    */

    'modals' => [
        'payee' => [
            'title' => env('PORTAL_PAYEE_MODAL_TITLE', 'Edit Payee'),
            'description' => env('PORTAL_PAYEE_MODAL_DESCRIPTION', 'Specify who can redeem this voucher'),
            'label' => env('PORTAL_PAYEE_MODAL_LABEL', 'Payee'),
            'placeholder' => env('PORTAL_PAYEE_MODAL_PLACEHOLDER', 'CASH (anyone), mobile number, or vendor alias'),
            'cancel_text' => env('PORTAL_PAYEE_MODAL_CANCEL', 'Cancel'),
            'save_text' => env('PORTAL_PAYEE_MODAL_SAVE', 'Save'),
        ],
        'top_up' => [
            'title' => env('PORTAL_TOPUP_MODAL_TITLE', 'Top Up Wallet'),
            'qr_button_text' => env('PORTAL_TOPUP_QR_BUTTON_TEXT', 'Scan QR Code to Load Wallet'),
            'bank_button_text' => env('PORTAL_TOPUP_BANK_BUTTON_TEXT', 'Bank Transfer (Admin Only)'),
        ],
        'confirm' => [
            'title_single' => env('PORTAL_CONFIRM_TITLE_SINGLE', 'Generate voucher?'),
            'title_multiple' => env('PORTAL_CONFIRM_TITLE_MULTIPLE', 'Generate {count} vouchers?'),
            'description' => env('PORTAL_CONFIRM_DESCRIPTION', 'Please confirm the details below'),
            'payee_label' => env('PORTAL_CONFIRM_PAYEE_LABEL', 'Payee'),
            'amount_label' => env('PORTAL_CONFIRM_AMOUNT_LABEL', 'Amount'),
            'total_cost_label' => env('PORTAL_CONFIRM_TOTAL_COST_LABEL', 'Total Cost'),
            'balance_after_label' => env('PORTAL_CONFIRM_BALANCE_AFTER_LABEL', 'Balance After'),
            'required_inputs_label' => env('PORTAL_CONFIRM_REQUIRED_INPUTS_LABEL', 'Required Inputs:'),
            'cancel_button_text' => env('PORTAL_CONFIRM_CANCEL_TEXT', 'Cancel'),
            'confirm_button_text' => env('PORTAL_CONFIRM_BUTTON_TEXT', 'Confirm'),
            'generating_text' => env('PORTAL_CONFIRM_GENERATING_TEXT', 'Generating...'),
        ],
    ],
];
