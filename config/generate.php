<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UI Version & Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which version of the UI is displayed and enable/disable features.
    |
    */

    'ui_version' => env('GENERATE_UI_VERSION', 'v2'), // 'legacy' or 'v2'
    
    'feature_flags' => [
        'progressive_disclosure' => env('GENERATE_UI_V2_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode Presets (Simple vs Advanced)
    |--------------------------------------------------------------------------
    |
    | Define what fields/cards are visible in Simple and Advanced modes.
    |
    */

    'mode_presets' => [
        'simple' => [
            'visible_cards' => ['basic_settings', 'cost_breakdown'],
            'basic_settings_fields' => ['campaign', 'amount', 'quantity'],
        ],
        'advanced' => [
            'visible_cards' => ['all'],
            'collapsible_by_default' => [
                'input_fields',
                'validation_rules',
                'location_validation',
                'time_validation',
                'feedback_channels',
                'rider',
                'preview_controls',
                'json_preview',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generate Voucher Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the Generate Voucher page.
    | Control visibility of sections, default values, labels, and constraints.
    |
    */

    'page' => [
        'title' => env('GENERATE_VOUCHER_TITLE', 'Generate Vouchers'),
        'description' => env('GENERATE_VOUCHER_DESCRIPTION', 'Create vouchers with custom instructions and validation rules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Basic Settings Card
    |--------------------------------------------------------------------------
    |
    | Configure the Basic Settings section where users set amount, quantity,
    | code prefix, mask, and expiry.
    |
    */

    'basic_settings' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_BASIC_CARD', true),

        // Campaign selector
        'show_campaign_selector' => env('GENERATE_VOUCHER_SHOW_CAMPAIGN', true),
        'campaign_selector' => [
            'show_label' => env('GENERATE_VOUCHER_CAMPAIGN_SHOW_LABEL', true),
            'label' => env('GENERATE_VOUCHER_CAMPAIGN_LABEL', 'Campaign Template (Optional)'),
            'help_text' => env('GENERATE_VOUCHER_CAMPAIGN_HELP', 'Select a campaign to auto-fill the form with saved settings. You can still modify any field after selection.'),
        ],

        // Amount field
        'show_amount' => env('GENERATE_VOUCHER_SHOW_AMOUNT', true),
        'amount' => [
            'label' => env('GENERATE_VOUCHER_AMOUNT_LABEL', 'Amount (PHP)'),
            'default' => env('GENERATE_VOUCHER_AMOUNT_DEFAULT', 50),
            'min' => env('GENERATE_VOUCHER_AMOUNT_MIN', 0),
            'step' => env('GENERATE_VOUCHER_AMOUNT_STEP', '0.01'),
        ],

        // Quantity field
        'show_quantity' => env('GENERATE_VOUCHER_SHOW_QUANTITY', true),
        'quantity' => [
            'label' => env('GENERATE_VOUCHER_QUANTITY_LABEL', 'Quantity'),
            'default' => env('GENERATE_VOUCHER_QUANTITY_DEFAULT', 1),
            'min' => env('GENERATE_VOUCHER_QUANTITY_MIN', 1),
        ],

        // Prefix field
        'show_prefix' => env('GENERATE_VOUCHER_SHOW_PREFIX', true),
        'prefix' => [
            'label' => env('GENERATE_VOUCHER_PREFIX_LABEL', 'Code Prefix (Optional)'),
            'placeholder' => env('GENERATE_VOUCHER_PREFIX_PLACEHOLDER', 'e.g., PROMO'),
        ],

        // Mask field
        'show_mask' => env('GENERATE_VOUCHER_SHOW_MASK', true),
        'mask' => [
            'label' => env('GENERATE_VOUCHER_MASK_LABEL', 'Code Mask (Optional)'),
            'placeholder' => env('GENERATE_VOUCHER_MASK_PLACEHOLDER', 'e.g., ****-****'),
            'help_text' => env('GENERATE_VOUCHER_MASK_HELP', 'Use * for random chars, - for separators (4-6 asterisks)'),
        ],

        // TTL field
        'show_ttl' => env('GENERATE_VOUCHER_SHOW_TTL', true),
        'ttl' => [
            'label' => env('GENERATE_VOUCHER_TTL_LABEL', 'Expiry (Days)'),
            'default' => env('GENERATE_VOUCHER_TTL_DEFAULT', 30),
            'min' => env('GENERATE_VOUCHER_TTL_MIN', 1),
            'placeholder' => env('GENERATE_VOUCHER_TTL_PLACEHOLDER', '30'),
            'help_text' => env('GENERATE_VOUCHER_TTL_HELP', 'Leave empty for non-expiring vouchers'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Fields Card
    |--------------------------------------------------------------------------
    |
    | Configure the Required Input Fields section.
    |
    */

    'input_fields' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_INPUT_FIELDS_CARD', true),
        'show_header' => env('GENERATE_VOUCHER_INPUT_FIELDS_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_INPUT_FIELDS_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_INPUT_FIELDS_TITLE', 'Input Fields'),
        'show_description' => env('GENERATE_VOUCHER_INPUT_FIELDS_SHOW_DESCRIPTION', true),
        'description' => env('GENERATE_VOUCHER_INPUT_FIELDS_DESCRIPTION', 'Select fields users must provide during redemption'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules Card
    |--------------------------------------------------------------------------
    |
    | Configure the Validation Rules section.
    |
    */

    'validation_rules' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_VALIDATION_CARD', true),
        'show_header' => env('GENERATE_VOUCHER_VALIDATION_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_VALIDATION_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_VALIDATION_TITLE', 'Validation Rules'),
        'show_description' => env('GENERATE_VOUCHER_VALIDATION_SHOW_DESCRIPTION', true),
        'description' => env('GENERATE_VOUCHER_VALIDATION_DESCRIPTION', 'Add secret codes or location-based restrictions'),

        // Secret code field
        'show_secret' => env('GENERATE_VOUCHER_SHOW_SECRET', true),
        'secret' => [
            'label' => env('GENERATE_VOUCHER_SECRET_LABEL', 'Secret Code'),
            'placeholder' => env('GENERATE_VOUCHER_SECRET_PLACEHOLDER', 'e.g., SECRET2025'),
        ],

        // Mobile restriction field
        'show_mobile' => env('GENERATE_VOUCHER_SHOW_VALIDATION_MOBILE', true),
        'mobile' => [
            'label' => env('GENERATE_VOUCHER_VALIDATION_MOBILE_LABEL', 'Restrict to Mobile Number'),
            'placeholder' => env('GENERATE_VOUCHER_VALIDATION_MOBILE_PLACEHOLDER', 'e.g., +639171234567'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Location Validation Card
    |--------------------------------------------------------------------------
    |
    | Configure the Location Validation section for geo-fencing.
    |
    */

    'location_validation' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_LOCATION_CARD', true),
        'default_enabled' => env('GENERATE_VOUCHER_LOCATION_DEFAULT_ENABLED', false), // Auto-enable main checkbox
        'default_radius_km' => env('GENERATE_VOUCHER_LOCATION_DEFAULT_RADIUS', 1), // Default 1km
        'default_on_failure' => env('GENERATE_VOUCHER_LOCATION_DEFAULT_FAILURE', 'block'), // 'block' or 'warn'
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Validation Card
    |--------------------------------------------------------------------------
    |
    | Configure the Time Validation section for time windows and duration limits.
    |
    */

    'time_validation' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_TIME_CARD', true),
        'default_enabled' => env('GENERATE_VOUCHER_TIME_DEFAULT_ENABLED', false), // Auto-enable main checkbox
        'default_window_enabled' => env('GENERATE_VOUCHER_TIME_DEFAULT_WINDOW_ENABLED', false),
        'default_timezone' => env('GENERATE_VOUCHER_TIME_DEFAULT_TIMEZONE', 'Asia/Manila'),
        'default_start_time' => env('GENERATE_VOUCHER_TIME_DEFAULT_START', '09:00'),
        'default_end_time' => env('GENERATE_VOUCHER_TIME_DEFAULT_END', '17:00'),
        'default_duration_enabled' => env('GENERATE_VOUCHER_TIME_DEFAULT_DURATION_ENABLED', true),
        'default_limit_minutes' => env('GENERATE_VOUCHER_TIME_DEFAULT_LIMIT', 1440), // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Feedback Channels Card
    |--------------------------------------------------------------------------
    |
    | Configure the Feedback Channels section.
    |
    */

    'feedback_channels' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_FEEDBACK_CARD', true),
        'show_header' => env('GENERATE_VOUCHER_FEEDBACK_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_FEEDBACK_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_FEEDBACK_TITLE', 'Feedback Channels'),
        'show_description' => env('GENERATE_VOUCHER_FEEDBACK_SHOW_DESCRIPTION', true),
        'description' => env('GENERATE_VOUCHER_FEEDBACK_DESCRIPTION', 'Receive notifications when vouchers are redeemed'),

        // Email field
        'show_email' => env('GENERATE_VOUCHER_SHOW_FEEDBACK_EMAIL', true),
        'email' => [
            'label' => env('GENERATE_VOUCHER_FEEDBACK_EMAIL_LABEL', 'Email'),
            'placeholder' => env('GENERATE_VOUCHER_FEEDBACK_EMAIL_PLACEHOLDER', 'notifications@example.com'),
        ],

        // Mobile field
        'show_mobile' => env('GENERATE_VOUCHER_SHOW_FEEDBACK_MOBILE', true),
        'mobile' => [
            'label' => env('GENERATE_VOUCHER_FEEDBACK_MOBILE_LABEL', 'Mobile'),
            'placeholder' => env('GENERATE_VOUCHER_FEEDBACK_MOBILE_PLACEHOLDER', '+639171234567'),
        ],

        // Webhook field
        'show_webhook' => env('GENERATE_VOUCHER_SHOW_FEEDBACK_WEBHOOK', true),
        'webhook' => [
            'label' => env('GENERATE_VOUCHER_FEEDBACK_WEBHOOK_LABEL', 'Webhook URL'),
            'placeholder' => env('GENERATE_VOUCHER_FEEDBACK_WEBHOOK_PLACEHOLDER', 'https://example.com/webhook'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rider Card
    |--------------------------------------------------------------------------
    |
    | Configure the Rider section (custom message and redirect URL after redemption).
    |
    */

    'rider' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_RIDER_CARD', true),
        'show_header' => env('GENERATE_VOUCHER_RIDER_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_RIDER_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_RIDER_TITLE', 'Rider'),
        'show_description' => env('GENERATE_VOUCHER_RIDER_SHOW_DESCRIPTION', true),
        'description' => env('GENERATE_VOUCHER_RIDER_DESCRIPTION', 'Add custom message or redirect URL after redemption'),

        // Message field
        'show_message' => env('GENERATE_VOUCHER_SHOW_RIDER_MESSAGE', true),
        'message' => [
            'label' => env('GENERATE_VOUCHER_RIDER_MESSAGE_LABEL', 'Message'),
            'placeholder' => env('GENERATE_VOUCHER_RIDER_MESSAGE_PLACEHOLDER', 'Thank you for redeeming!'),
        ],

        // Redirect URL field
        'show_url' => env('GENERATE_VOUCHER_SHOW_RIDER_URL', true),
        'url' => [
            'label' => env('GENERATE_VOUCHER_RIDER_URL_LABEL', 'Redirect URL'),
            'placeholder' => env('GENERATE_VOUCHER_RIDER_URL_PLACEHOLDER', 'https://example.com/thank-you'),
            'default' => env('GENERATE_VOUCHER_RIDER_URL_DEFAULT', null),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON Preview Card
    |--------------------------------------------------------------------------
    |
    | Configure the JSON Preview section.
    |
    */

    'json_preview' => [
        'show_card' => env('GENERATE_VOUCHER_SHOW_JSON_PREVIEW', true),
        'show_header' => env('GENERATE_VOUCHER_JSON_PREVIEW_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_JSON_PREVIEW_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_JSON_PREVIEW_TITLE', 'Live JSON Preview'),
        'show_description' => env('GENERATE_VOUCHER_JSON_PREVIEW_SHOW_DESCRIPTION', true),
        'description' => env('GENERATE_VOUCHER_JSON_PREVIEW_DESCRIPTION', 'Real-time voucher instructions JSON'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Deduction Sidebar
    |--------------------------------------------------------------------------
    |
    | Configure the Wallet Deduction sidebar (face value + charges).
    |
    */

    'cost_breakdown' => [ // Keep key for backward compatibility
        'show_sidebar' => env('GENERATE_VOUCHER_SHOW_COST_SIDEBAR', true),
        'show_header' => env('GENERATE_VOUCHER_COST_SHOW_HEADER', true),
        'show_title' => env('GENERATE_VOUCHER_COST_SHOW_TITLE', true),
        'title' => env('GENERATE_VOUCHER_COST_TITLE', 'Wallet Deduction'),
        'total_label' => env('GENERATE_VOUCHER_TOTAL_LABEL', 'Total Deduction'),
        // Label for face value (used in UI breakdown AND deductionJson.face_value.label)
        'face_value_label' => env('GENERATE_VOUCHER_FACE_VALUE_LABEL', 'Voucher Amount (Escrowed)'),
        'calculating_message' => env('GENERATE_VOUCHER_COST_CALCULATING', 'Calculating charges...'),
        'error_message' => env('GENERATE_VOUCHER_COST_ERROR', 'Error calculating charges. Using fallback pricing.'),
        'wallet_balance_label' => env('GENERATE_VOUCHER_WALLET_BALANCE_LABEL', 'Wallet Balance'),
        'after_generation_label' => env('GENERATE_VOUCHER_AFTER_GENERATION_LABEL', 'After Generation'),
        'insufficient_funds_message' => env('GENERATE_VOUCHER_INSUFFICIENT_FUNDS', 'Please fund your wallet before generating vouchers'),
        'submit_button' => [
            'text' => env('GENERATE_VOUCHER_SUBMIT_TEXT', 'Generate Vouchers'),
            'processing_text' => env('GENERATE_VOUCHER_SUBMIT_PROCESSING', 'Generating...'),
            'insufficient_funds_text' => env('GENERATE_VOUCHER_INSUFFICIENT_FUNDS_TEXT', 'Insufficient Funds'),
        ],
    ],

];
