<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redeem Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the redeem widget.
    | These settings control what elements are visible in the widget.
    |
    */

    'widget' => [
        'show_logo' => env('REDEEM_WIDGET_SHOW_LOGO', true),
        'show_app_name' => env('REDEEM_WIDGET_SHOW_APP_NAME', false),
        'show_label' => env('REDEEM_WIDGET_SHOW_LABEL', true),
        'show_title' => env('REDEEM_WIDGET_SHOW_TITLE', false),
        'show_description' => env('REDEEM_WIDGET_SHOW_DESCRIPTION', true),

        // Custom text overrides
        'title' => env('REDEEM_WIDGET_TITLE', 'Redeem Voucher'),
        'description' => env('REDEEM_WIDGET_DESCRIPTION', null),
        'label' => env('REDEEM_WIDGET_LABEL', 'code'),
        'placeholder' => env('REDEEM_WIDGET_PLACEHOLDER', 'x x x x'),
        'button_text' => env('REDEEM_WIDGET_BUTTON_TEXT', 'redeem'),
        'button_processing_text' => env('REDEEM_WIDGET_BUTTON_PROCESSING_TEXT', 'Checking...'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the wallet (voucher details) page.
    | Control what cards/sections are displayed and customize all labels/text.
    |
    */

    'wallet' => [
        // Voucher Details Card
        'show_voucher_details_card' => env('REDEEM_WALLET_SHOW_VOUCHER_CARD', true),
        'voucher_details' => [
            'show_header' => env('REDEEM_WALLET_VOUCHER_SHOW_HEADER', true),
            'show_title' => env('REDEEM_WALLET_VOUCHER_SHOW_TITLE', true),
            'title' => env('REDEEM_WALLET_VOUCHER_TITLE', 'Voucher Details'),
            'show_description' => env('REDEEM_WALLET_VOUCHER_SHOW_DESCRIPTION', false),
            'description' => env('REDEEM_WALLET_VOUCHER_DESCRIPTION', null),

            'show_code' => env('REDEEM_WALLET_SHOW_CODE', true),
            'code_label' => env('REDEEM_WALLET_CODE_LABEL', 'Code'),

            'show_amount' => env('REDEEM_WALLET_SHOW_AMOUNT', true),
            'amount_label' => env('REDEEM_WALLET_AMOUNT_LABEL', 'Amount'),

            'show_owner' => env('REDEEM_WALLET_SHOW_OWNER', true),
            'owner_label' => env('REDEEM_WALLET_OWNER_LABEL', 'Issued by'),

            'show_generated_at' => env('REDEEM_WALLET_SHOW_GENERATED_AT', true),
            'generated_at_label' => env('REDEEM_WALLET_GENERATED_AT_LABEL', 'Generated'),

            'show_count' => env('REDEEM_WALLET_SHOW_COUNT', true),
            'count_label' => env('REDEEM_WALLET_COUNT_LABEL', 'Batch size'),

            'show_expires_at' => env('REDEEM_WALLET_SHOW_EXPIRES_AT', true),
            'expires_at_label' => env('REDEEM_WALLET_EXPIRES_AT_LABEL', 'Expires'),
        ],

        // Instruction Details Card (What to prepare)
        'show_instruction_details_card' => env('REDEEM_WALLET_SHOW_INSTRUCTION_CARD', true),
        'instruction_details' => [
            'show_header' => env('REDEEM_WALLET_INSTRUCTION_SHOW_HEADER', true),
            'show_title' => env('REDEEM_WALLET_INSTRUCTION_SHOW_TITLE', true),
            'title' => env('REDEEM_WALLET_INSTRUCTION_TITLE', 'What You\'ll Need'),
            'show_description' => env('REDEEM_WALLET_INSTRUCTION_SHOW_DESCRIPTION', true),
            'description' => env('REDEEM_WALLET_INSTRUCTION_DESCRIPTION', 'Please prepare the following before proceeding'),

            'show_required_inputs' => env('REDEEM_WALLET_SHOW_REQUIRED_INPUTS', true),
            'required_inputs_label' => env('REDEEM_WALLET_REQUIRED_INPUTS_LABEL', 'Required Information'),

            'show_validation_requirements' => env('REDEEM_WALLET_SHOW_VALIDATION', true),
            'validation_label' => env('REDEEM_WALLET_VALIDATION_LABEL', 'Validation Required'),

            'show_capture_requirements' => env('REDEEM_WALLET_SHOW_CAPTURE', true),
            'capture_label' => env('REDEEM_WALLET_CAPTURE_LABEL', 'You will need to provide'),

            // Custom messages for specific requirements
            'selfie_hint' => env('REDEEM_WALLET_SELFIE_HINT', 'A clear selfie photo - please ensure good lighting'),
            'signature_hint' => env('REDEEM_WALLET_SIGNATURE_HINT', 'Your digital signature'),
            'location_hint' => env('REDEEM_WALLET_LOCATION_HINT', 'Your current location - please enable location services'),
            'secret_hint' => env('REDEEM_WALLET_SECRET_REQUIREMENT_HINT', 'A secret code is required for this voucher'),
        ],

        // Contact & Payment Details Card
        'show_contact_payment_card' => env('REDEEM_WALLET_SHOW_CONTACT_CARD', true),
        'contact_payment' => [
            'show_header' => env('REDEEM_WALLET_CONTACT_SHOW_HEADER', true),
            'show_title' => env('REDEEM_WALLET_CONTACT_SHOW_TITLE', true),
            'title' => env('REDEEM_WALLET_CONTACT_TITLE', 'Contact & Payment Details'),
            'show_description' => env('REDEEM_WALLET_CONTACT_SHOW_DESCRIPTION', true),
            'description' => env('REDEEM_WALLET_CONTACT_DESCRIPTION', 'Provide your mobile number and bank account to receive the cash'),

            'mobile_label' => env('REDEEM_WALLET_MOBILE_LABEL', 'Mobile Number'),
            'mobile_placeholder' => env('REDEEM_WALLET_MOBILE_PLACEHOLDER', '09171234567'),
            'mobile_hint' => env('REDEEM_WALLET_MOBILE_HINT', 'Philippine mobile number format: 09XXXXXXXXX'),
            'mobile_default' => env('REDEEM_WALLET_MOBILE_DEFAULT', ''),

            'country_default' => env('REDEEM_WALLET_COUNTRY_DEFAULT', 'PH'),

            'secret_label' => env('REDEEM_WALLET_SECRET_LABEL', 'Secret Code'),
            'secret_placeholder' => env('REDEEM_WALLET_SECRET_PLACEHOLDER', 'Enter secret code'),
            'secret_hint' => env('REDEEM_WALLET_SECRET_HINT', 'This voucher requires a secret code to redeem'),

            'show_bank_fields' => env('REDEEM_WALLET_SHOW_BANK_FIELDS', true),
            'bank_label' => env('REDEEM_WALLET_BANK_LABEL', 'Wallet | Bank'),
            'bank_placeholder' => env('REDEEM_WALLET_BANK_PLACEHOLDER', 'Select a bank'),
            'bank_default' => env('REDEEM_WALLET_BANK_DEFAULT', 'GXCHPHM2XXX'),

            'account_number_label' => env('REDEEM_WALLET_ACCOUNT_NUMBER_LABEL', 'Account Number'),
            'account_number_placeholder' => env('REDEEM_WALLET_ACCOUNT_NUMBER_PLACEHOLDER', 'Enter account number'),
            'account_number_default' => env('REDEEM_WALLET_ACCOUNT_NUMBER_DEFAULT', ''),

            'cancel_button_text' => env('REDEEM_WALLET_CANCEL_BUTTON', 'Cancel'),

            // Submit button with voucher code
            'show_code_in_submit_button' => env('REDEEM_WALLET_SHOW_CODE_IN_BUTTON', false),
            'submit_button_action' => env('REDEEM_WALLET_SUBMIT_ACTION', 'Redeem'), // "Redeem", "Claim", etc.
            'submit_button_text' => env('REDEEM_WALLET_SUBMIT_BUTTON', 'Redeem Voucher'), // Used when show_code_in_submit_button is false
            'submit_button_processing_text' => env('REDEEM_WALLET_SUBMIT_PROCESSING', 'Processing...'),

            // Account number auto-sync settings
            'auto_sync_account_number' => env('REDEEM_WALLET_AUTO_SYNC_ACCOUNT', true),
            'auto_sync_bank_codes' => array_filter(array_map('trim', explode(',', env('REDEEM_WALLET_AUTO_SYNC_BANK_CODES', 'GXCHPHM2XXX,PAPHPHM1XXX')))), // Comma-separated bank codes to trigger auto-sync (e.g., "GXCHPHM2XXX,MAYBPHM2XXX")
            'auto_sync_delay' => env('REDEEM_WALLET_AUTO_SYNC_DELAY', 1500), // Delay in milliseconds before syncing
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Select Component Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the bank selection component.
    | Control visibility of bank codes, text formatting, and search behavior.
    |
    */

    'bank_select' => [
        'show_bank_code' => env('REDEEM_BANK_SELECT_SHOW_CODE', false),
        'bank_code_position' => env('REDEEM_BANK_SELECT_CODE_POSITION', 'left'), // 'left', 'right', 'none'

        // Text formatting for bank names
        'name_format' => env('REDEEM_BANK_SELECT_NAME_FORMAT', 'title-case'), // 'uppercase', 'lowercase', 'title-case', 'as-is'

        // Selected bank display format
        'selected_format' => env('REDEEM_BANK_SELECT_SELECTED_FORMAT', 'name'), // 'name', 'code', 'name-code', 'code-name'

        // Dropdown list item format
        'list_item_format' => env('REDEEM_BANK_SELECT_LIST_FORMAT', 'name-code'), // 'name', 'code', 'name-code', 'code-name'

        // Search behavior
        'search_placeholder' => env('REDEEM_BANK_SELECT_SEARCH_PLACEHOLDER', 'Search banks...'),
        'empty_text' => env('REDEEM_BANK_SELECT_EMPTY_TEXT', 'No bank found'),
        'show_clear_button' => env('REDEEM_BANK_SELECT_SHOW_CLEAR', true),

        // Display options
        'show_search' => env('REDEEM_BANK_SELECT_SHOW_SEARCH', true),
        'max_items_shown' => env('REDEEM_BANK_SELECT_MAX_ITEMS', 10), // Number of items visible before scrolling (0 = unlimited)
        'max_dropdown_height' => env('REDEEM_BANK_SELECT_MAX_HEIGHT', '300px'), // Explicit height override (takes precedence over max_items_shown)

        // Settlement rail filtering
        'allowed_settlement_rails' => array_filter(array_map('trim', explode(',', env('REDEEM_BANK_SELECT_ALLOWED_RAILS', 'INSTAPAY')))), // Filter banks by settlement rail (e.g., "INSTAPAY,PESONET")
    ],

    /*
    |--------------------------------------------------------------------------
    | Success Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the redemption success page.
    | Control visual hierarchy: instruction message is primary, ads secondary,
    | voucher details factual, countdown subtle.
    |
    */

    'success' => [
        // Logo and branding
        'show_logo' => env('REDEEM_SUCCESS_SHOW_LOGO', true),
        'show_app_name' => env('REDEEM_SUCCESS_SHOW_APP_NAME', false),
        'app_name' => env('REDEEM_SUCCESS_APP_NAME', 'Redeem'),

        // Success confirmation (icon + title)
        'show_success_confirmation' => env('REDEEM_SUCCESS_SHOW_CONFIRMATION', true),
        'confirmation' => [
            'show_icon' => env('REDEEM_SUCCESS_SHOW_ICON', false),
            'show_title' => env('REDEEM_SUCCESS_SHOW_TITLE', false),
            'title' => env('REDEEM_SUCCESS_TITLE', 'Redemption successful!'),
            'show_subtitle' => env('REDEEM_SUCCESS_SHOW_SUBTITLE', false),
            'subtitle' => env('REDEEM_SUCCESS_SUBTITLE', 'Cash amount has been transferred'),
        ],

        // Instruction message (PROMINENT - primary focus)
        // Supports template variables: {{ voucher.contact.name }}, {{ name }}, {{ code }}, etc.
        'show_instruction_message' => env('REDEEM_SUCCESS_SHOW_INSTRUCTION', true),
        'instruction' => [
            'default_message' => env('REDEEM_SUCCESS_DEFAULT_MESSAGE', 'Congratulations!'),
            'show_as_card' => env('REDEEM_SUCCESS_INSTRUCTION_AS_CARD', true),
            'style' => env('REDEEM_SUCCESS_INSTRUCTION_STYLE', 'prominent'), // 'prominent', 'highlighted', 'normal'
        ],

        // Advertisement area
        'show_advertisement' => env('REDEEM_SUCCESS_SHOW_AD', false),
        'advertisement' => [
            'position' => env('REDEEM_SUCCESS_AD_POSITION', 'after-instruction'), // 'before-instruction', 'after-instruction', 'after-details', 'bottom'
            'content' => env('REDEEM_SUCCESS_AD_CONTENT', null), // HTML content or null
            'show_as_card' => env('REDEEM_SUCCESS_AD_AS_CARD', true),
        ],

        // Voucher details (factual, secondary)
        'show_voucher_details' => env('REDEEM_SUCCESS_SHOW_DETAILS', false),
        'voucher_details' => [
            'style' => env('REDEEM_SUCCESS_DETAILS_STYLE', 'compact'), // 'compact', 'normal'
            'show_as_card' => env('REDEEM_SUCCESS_DETAILS_AS_CARD', true),

            'show_code' => env('REDEEM_SUCCESS_SHOW_CODE', true),
            'code_label' => env('REDEEM_SUCCESS_CODE_LABEL', 'Voucher Code'),

            'show_amount' => env('REDEEM_SUCCESS_SHOW_AMOUNT', true),
            'amount_label' => env('REDEEM_SUCCESS_AMOUNT_LABEL', 'Amount Received'),

            'show_mobile' => env('REDEEM_SUCCESS_SHOW_MOBILE', true),
            'mobile_label' => env('REDEEM_SUCCESS_MOBILE_LABEL', 'Mobile Number'),
        ],

        // Redirect/countdown (subtle, low priority)
        'show_redirect' => env('REDEEM_SUCCESS_SHOW_REDIRECT', true),
        'redirect' => [
            'timeout' => env('REDEEM_SUCCESS_REDIRECT_TIMEOUT', 10), // seconds (0 = manual only, no auto-redirect)
            'style' => env('REDEEM_SUCCESS_REDIRECT_STYLE', 'subtle'), // 'subtle', 'normal', 'prominent'

            'show_countdown' => env('REDEEM_SUCCESS_SHOW_COUNTDOWN', true),
            'countdown_message' => env('REDEEM_SUCCESS_COUNTDOWN_MESSAGE', 'You will be redirected in {seconds} seconds...'),

            'show_manual_button' => env('REDEEM_SUCCESS_SHOW_MANUAL_BUTTON', true),
            'button_text' => env('REDEEM_SUCCESS_BUTTON_TEXT', 'Continue'),

            'redirecting_message' => env('REDEEM_SUCCESS_REDIRECTING_MESSAGE', 'Redirecting...'),
        ],

        // Action buttons (when no redirect)
        'show_action_buttons' => env('REDEEM_SUCCESS_SHOW_ACTIONS', true),
        'actions' => [
            'show_redeem_another' => env('REDEEM_SUCCESS_SHOW_REDEEM_ANOTHER', true),
            'redeem_another_text' => env('REDEEM_SUCCESS_REDEEM_ANOTHER_TEXT', 'Redeem Another Voucher'),
        ],

        // Footer note (supports template variables with dot-notation or auto-search)
        // Dot-notation: {{ voucher.contact.mobile }}, {{ voucher.code }}
        // Auto-search: {{ mobile }}, {{ bank_account }}, {{ code }} (searches recursively)
        // Special: {{ amount }} (formatted with currency)
        'show_footer_note' => env('REDEEM_SUCCESS_SHOW_FOOTER', true),
        'footer_note' => env('REDEEM_SUCCESS_FOOTER_NOTE', "{{ voucher.cash.currency }} {{ voucher.cash.amount }} has been transferred to {{ bank_account }} by redeeming {{ code }}."),
        
        // Metadata display (transparency information)
        'show_metadata' => env('REDEEM_SUCCESS_SHOW_METADATA', true),
        'metadata' => [
            'position' => env('REDEEM_SUCCESS_METADATA_POSITION', 'after-details'), // 'after-details', 'after-instruction', 'bottom'
            'show_as_card' => env('REDEEM_SUCCESS_METADATA_AS_CARD', true),
            'compact' => env('REDEEM_SUCCESS_METADATA_COMPACT', true),
            'show_issuer' => env('REDEEM_SUCCESS_METADATA_SHOW_ISSUER', false),
            'show_copyright' => env('REDEEM_SUCCESS_METADATA_SHOW_COPYRIGHT', true),
            'show_licenses' => env('REDEEM_SUCCESS_METADATA_SHOW_LICENSES', true),
            'title' => env('REDEEM_SUCCESS_METADATA_TITLE', 'Voucher Information'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Finalize Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the finalize (review) page.
    | Control what elements are shown in the summary table and customize all labels.
    |
    */

    'finalize' => [
        // Header section
        'show_header' => env('REDEEM_FINALIZE_SHOW_HEADER', false),
        'header' => [
            'show_title' => env('REDEEM_FINALIZE_SHOW_TITLE', true),
            'title' => env('REDEEM_FINALIZE_TITLE', 'Review'),
            'show_description' => env('REDEEM_FINALIZE_SHOW_DESCRIPTION', true),
            'description' => env('REDEEM_FINALIZE_DESCRIPTION', 'Please verify all details before confirming'),
        ],

        // Summary table
        'show_summary_table' => env('REDEEM_FINALIZE_SHOW_SUMMARY_TABLE', true),
        'summary_table' => [
            'show_header' => env('REDEEM_FINALIZE_SUMMARY_SHOW_HEADER', false),
            'show_title' => env('REDEEM_FINALIZE_SUMMARY_SHOW_TITLE', false),
            'title' => env('REDEEM_FINALIZE_SUMMARY_TITLE', 'Redemption Summary'),
            'show_description' => env('REDEEM_FINALIZE_SUMMARY_SHOW_DESCRIPTION', false),
            'description' => env('REDEEM_FINALIZE_SUMMARY_DESCRIPTION', 'Review the details below'),

            // Table row controls
            'show_voucher_code' => env('REDEEM_FINALIZE_SHOW_VOUCHER_CODE', true),
            'voucher_code_label' => env('REDEEM_FINALIZE_VOUCHER_CODE_LABEL', 'Voucher Code'),

            'show_amount' => env('REDEEM_FINALIZE_SHOW_AMOUNT', true),
            'amount_label' => env('REDEEM_FINALIZE_AMOUNT_LABEL', 'Amount'),

            'show_mobile' => env('REDEEM_FINALIZE_SHOW_MOBILE', true),
            'mobile_label' => env('REDEEM_FINALIZE_MOBILE_LABEL', 'Mobile Number'),

            'show_bank_account' => env('REDEEM_FINALIZE_SHOW_BANK_ACCOUNT', true),
            'bank_account_label' => env('REDEEM_FINALIZE_BANK_ACCOUNT_LABEL', 'Bank Account'),

            'show_collected_inputs' => env('REDEEM_FINALIZE_SHOW_COLLECTED_INPUTS', true),

            'show_captured_items' => env('REDEEM_FINALIZE_SHOW_CAPTURED_ITEMS', true),
            'captured_items_label' => env('REDEEM_FINALIZE_CAPTURED_ITEMS_LABEL', 'Captured Items'),

            // Copy buttons for voucher code and mobile
            'show_copy_buttons' => env('REDEEM_FINALIZE_SHOW_COPY_BUTTONS', false),
        ],

        // Confirmation notice
        'show_confirmation_notice' => env('REDEEM_FINALIZE_SHOW_CONFIRMATION_NOTICE', true),
        'confirmation_notice' => [
            'show_title' => env('REDEEM_FINALIZE_CONFIRMATION_SHOW_TITLE', true),
            'title' => env('REDEEM_FINALIZE_CONFIRMATION_TITLE', 'Important:'),
            'show_message' => env('REDEEM_FINALIZE_CONFIRMATION_SHOW_MESSAGE', true),
            'message' => env('REDEEM_FINALIZE_CONFIRMATION_MESSAGE', 'By confirming, you agree that the information provided is accurate. The cash will be transferred to the provided bank account.'),
        ],

        // Action buttons
        'show_action_buttons' => env('REDEEM_FINALIZE_SHOW_ACTION_BUTTONS', true),
        'action_buttons' => [
            'show_back_button' => env('REDEEM_FINALIZE_SHOW_BACK_BUTTON', true),
            'back_button_text' => env('REDEEM_FINALIZE_BACK_BUTTON_TEXT', 'Back'),

            'show_confirm_button' => env('REDEEM_FINALIZE_SHOW_CONFIRM_BUTTON', true),
            'confirm_button_text' => env('REDEEM_FINALIZE_CONFIRM_BUTTON_TEXT', 'Confirm Redemption'),
            'confirm_button_processing_text' => env('REDEEM_FINALIZE_CONFIRM_BUTTON_PROCESSING_TEXT', 'Processing...'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Breakdown Display Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how costs are displayed in the breakdown UI across the app.
    |
    */

    'cost_breakdown' => [
        // Label for the voucher face value (the cash to be transferred to redeemer)
        'cash_amount_label' => env('REDEEM_COST_CASH_AMOUNT_LABEL', 'Cash Amount'),

        // Show per-unit prices with quantity multiplier (e.g., "₱500 × 10 = ₱5,000")
        // If false, shows only the total (e.g., "₱5,000")
        'show_per_unit_prices' => env('REDEEM_COST_SHOW_PER_UNIT_PRICES', true),

        // Show quantity indicator in breakdown items when count > 1
        'show_quantity_indicator' => env('REDEEM_COST_SHOW_QUANTITY_INDICATOR', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Define pricing for each customizable instruction item.
    | Prices are in centavos (100 = ₱1.00).
    |
    */

    'pricelist' => [
        'cash.amount' => [
            'price' => 2000, // ₱20.00 base fee
            'label' => 'Transaction Fee',
            'description' => 'Cash voucher generation base fee',
            'category' => 'base',
        ],
        'feedback.email' => [
            'price' => 100, // ₱1.00
            'label' => 'Email Address',
            'description' => 'Email notification on redemption',
            'category' => 'feedback',
        ],
        'feedback.mobile' => [
            'price' => 180, // ₱1.80
            'label' => 'Mobile Number',
            'description' => 'SMS notification',
            'category' => 'feedback',
        ],
        'feedback.webhook' => [
            'price' => 190, // ₱1.90
            'label' => 'Webhook URL',
            'description' => 'Webhook notification',
            'category' => 'feedback',
        ],
        'cash.validation.secret' => [
            'price' => 120, // ₱1.20
            'description' => 'Secret code validation',
            'category' => 'validation',
        ],
        'cash.validation.mobile' => [
            'price' => 130, // ₱1.30
            'description' => 'Mobile number validation',
            'category' => 'validation',
        ],
        'cash.validation.location' => [
            'price' => 150, // ₱1.50
            'description' => 'GPS location validation',
            'category' => 'validation',
        ],
        'cash.validation.radius' => [
            'price' => 160, // ₱1.60
            'description' => 'Radius validation',
            'category' => 'validation',
        ],
        
        // New validation features
        'validation.location' => [
            'price' => 300, // ₱3.00
            'label' => 'Location Validation',
            'description' => 'Geo-fencing with coordinates and radius',
            'category' => 'validation',
        ],
        'validation.time' => [
            'price' => 250, // ₱2.50
            'label' => 'Time Validation',
            'description' => 'Time window and duration limit validation',
            'category' => 'validation',
        ],
        'inputs.fields.email' => [
            'price' => 220, // ₱2.20
            'description' => 'Email input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.mobile' => [
            'price' => 230, // ₱2.30
            'description' => 'Mobile number input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.name' => [
            'price' => 240, // ₱2.40
            'description' => 'Name input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.address' => [
            'price' => 250, // ₱2.50
            'label' => 'Full Address',
            'description' => 'Address input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.birth_date' => [
            'price' => 260, // ₱2.60
            'description' => 'Birth date input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.gross_monthly_income' => [
            'price' => 270, // ₱2.70
            'description' => 'Gross monthly income input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.signature' => [
            'price' => 280, // ₱2.80
            'description' => 'Signature capture field',
            'category' => 'input_fields',
        ],
        'inputs.fields.location' => [
            'price' => 300, // ₱3.00
            'description' => 'GPS location capture field',
            'category' => 'input_fields',
        ],
        'inputs.fields.reference_code' => [
            'price' => 250, // ₱2.50
            'description' => 'Reference code input field',
            'category' => 'input_fields',
        ],
        'inputs.fields.otp' => [
            'price' => 400, // ₱4.00
            'description' => 'OTP verification field',
            'category' => 'input_fields',
        ],
        'inputs.fields.selfie' => [
            'price' => 400, // ₱4.00
            'description' => 'Selfie',
            'category' => 'input_fields',
        ],
        'rider.message' => [
            'price' => 200, // ₱2.00
            'label' => 'Rider Message',
            'description' => 'Custom message shown after redemption',
            'category' => 'rider',
        ],
        'rider.url' => [
            'price' => 210, // ₱2.10
            'label' => 'Rider URL',
            'description' => 'Redirect URL after redemption',
            'category' => 'rider',
        ],
        'rider.splash' => [
            'price' => 220, // ₱2.20
            'label' => 'Rider Splash Screen',
            'description' => 'Custom splash screen shown after redemption',
            'category' => 'rider',
        ],
    ],

];
