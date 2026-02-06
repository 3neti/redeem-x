<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voucher Generation Gate Configuration
    |--------------------------------------------------------------------------
    |
    | Control whether to validate user balance before voucher generation.
    | When enabled, checks if user has sufficient funds to cover voucher costs.
    | Disable if balance validation is causing issues.
    |
    */

    'voucher_generation_gate_enabled' => env('VOUCHER_GENERATION_GATE_ENABLED', true),

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
    | Pricing Configuration - Market-Rational Pricing Model
    |--------------------------------------------------------------------------
    |
    | This pricing schedule is designed to be competitive, cost-based, and
    | value-driven. All prices are in centavos (100 = ₱1.00).
    |
    | PRICING PHILOSOPHY:
    | ===================
    |
    | 1. COST RECOVERY (Break-even or minimal margin)
    |    - Transaction Fee: ₱15.00 (NetBank InstaPay cost)
    |    - KYC Verification: ₱18.00 (HyperVerge ₱15 + processing ₱3)
    |    - OTP: ₱2.00 (SMS ₱1 + processing ₱1)
    |    - Email/SMS Notifications: ₱1.20-₱1.50 (delivery + storage)
    |
    | 2. MINIMAL PRICING (Single digits for competitive advantage)
    |    - Standard text inputs: ₱0.30-₱0.50
    |    - Simple validations: ₱0.50-₱0.80
    |    - Storage-light features: ₱0.50-₱1.00
    |
    | 3. STORAGE-BASED PRICING
    |    - Location: ₱1.00 (geocoding + coordinates storage)
    |    - Signature: ₱1.50 (medium image storage)
    |    - Selfie: ₱3.00 (large image storage + processing)
    |
    | 4. PREMIUM FEATURES (Value-based - Marketing ROI)
    |    - Rider Message: ₱2.00 (basic messaging)
    |    - Rider Splash: ₱20.00 (advertising real estate - 10x multiplier)
    |    - Rider URL: ₱50.00 (digital marketing conversion tool - 25x multiplier)
    |
    | 5. ENTERPRISE FEATURES (Accessible pricing)
    |    - Payable Voucher: ₱5.00 (multi-payment capability)
    |    - Settlement Voucher: ₱8.00 (complex enterprise workflows)
    |
    | COMPETITIVE POSITIONING:
    | =======================
    |
    | Base Transaction: ₱15.00 (cost pass-through, profit from features)
    | Basic Voucher (no extras): ₱15.00
    | Marketing Voucher (email + name + rider URL): ₱66.80
    | KYC Voucher (KYC + selfie + location + signature): ₱38.50
    | Full-Featured: ~₱120.00
    |
    | COST BREAKDOWN BY CATEGORY:
    | ===========================
    |
    | Base Charges:          ₱15.00 - ₱8.00
    | Feedback Channels:     ₱0.50 - ₱1.50
    | Input Fields (Text):   ₱0.30 - ₱0.50
    | Input Fields (Media):  ₱1.00 - ₱3.00
    | Input Fields (KYC):    ₱18.00
    | Input Fields (OTP):    ₱2.00
    | Validation Rules:      ₱0.50 - ₱2.00
    | Rider Features:        ₱2.00 - ₱50.00
    |
    | RATIONALE:
    | ==========
    |
    | 1. Transaction Fee = NetBank cost (no markup) to stay competitive
    | 2. Text inputs minimal (₱0.30-₱0.50) - just database storage
    | 3. Media inputs priced by storage size (location < signature < selfie)
    | 4. Third-party APIs cost-plus (KYC ₱15+₱3, OTP ₱1+₱1)
    | 5. Rider URL premium (₱50) justified by marketing conversion value
    | 6. Everything kept single-digit except cost-recovery and premium features
    |
    */

    'pricelist' => [
        /*
        |--------------------------------------------------------------------------
        | BASE CHARGES - Cost Recovery
        |--------------------------------------------------------------------------
        |
        | Transaction Fee: Pass-through of NetBank InstaPay cost (₱15.00)
        | Enterprise Vouchers: Accessible pricing for business features
        |
        */
        'cash.amount' => [
            'price' => 1500, // ₱15.00 (NetBank InstaPay fee - break-even)
            'label' => 'Transaction Fee',
            'description' => 'InstaPay fund transfer cost (NetBank)',
            'category' => 'base',
        ],
        'voucher_type.payable' => [
            'price' => 500, // ₱5.00 (multi-payment capability)
            'label' => 'Payable Voucher',
            'description' => 'Multi-payment voucher accepting payments until target amount reached',
            'category' => 'base',
        ],
        'voucher_type.settlement' => [
            'price' => 800, // ₱8.00 (complex enterprise workflows)
            'label' => 'Settlement Voucher',
            'description' => 'Enterprise settlement instrument for complex multi-payment scenarios',
            'category' => 'base',
        ],

        /*
        |--------------------------------------------------------------------------
        | HIGH-COST FEATURES - Third-Party API Costs
        |--------------------------------------------------------------------------
        |
        | KYC: HyperVerge API (₱15) + processing/storage (₱3)
        | OTP: SMS gateway (₱1) + system processing (₱1)
        |
        */
        'inputs.fields.kyc' => [
            'price' => 1800, // ₱18.00 (HyperVerge ₱15 + processing ₱3)
            'label' => 'KYC Verification',
            'description' => 'Identity verification via HyperVerge (ID + selfie biometric)',
            'category' => 'input_fields',
        ],
        'inputs.fields.otp' => [
            'price' => 200, // ₱2.00 (SMS ₱1 + processing ₱1)
            'label' => 'OTP Verification',
            'description' => 'One-time password via SMS',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | FEEDBACK CHANNELS - Notification Costs
        |--------------------------------------------------------------------------
        |
        | Email: Service cost + storage for mail/attachments (₱1.50)
        | SMS: Delivery cost + margin (₱1.20)
        | Webhook: Minimal HTTP request + logging (₱0.50)
        |
        */
        'feedback.email' => [
            'price' => 150, // ₱1.50 (email service + storage for attachments)
            'label' => 'Email Notification',
            'description' => 'Email notification on redemption with attachments',
            'category' => 'feedback',
        ],
        'feedback.mobile' => [
            'price' => 120, // ₱1.20 (SMS delivery ₱1.00 + margin ₱0.20)
            'label' => 'SMS Notification',
            'description' => 'SMS notification on redemption',
            'category' => 'feedback',
        ],
        'feedback.webhook' => [
            'price' => 50, // ₱0.50 (HTTP request + logging)
            'label' => 'Webhook Notification',
            'description' => 'Real-time webhook notification to your endpoint',
            'category' => 'feedback',
        ],

        /*
        |--------------------------------------------------------------------------
        | STORAGE-INTENSIVE INPUT FIELDS
        |--------------------------------------------------------------------------
        |
        | Selfie: Large image storage + processing (₱3.00)
        | Signature: Medium image storage (₱1.50)
        | Location: Geocoding API + coordinates storage (₱1.00)
        |
        */
        'inputs.fields.selfie' => [
            'price' => 300, // ₱3.00 (large image storage + processing)
            'label' => 'Selfie Photo',
            'description' => 'Camera capture for selfie verification',
            'category' => 'input_fields',
        ],
        'inputs.fields.signature' => [
            'price' => 150, // ₱1.50 (medium image storage)
            'label' => 'Digital Signature',
            'description' => 'Digital signature capture',
            'category' => 'input_fields',
        ],
        'inputs.fields.location' => [
            'price' => 100, // ₱1.00 (geocoding API + storage)
            'label' => 'GPS Location',
            'description' => 'GPS coordinates capture with reverse geocoding',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | STANDARD INPUT FIELDS - Text/Data Only
        |--------------------------------------------------------------------------
        |
        | Minimal cost - just database storage for text data (₱0.30 - ₱0.50)
        | Email/Mobile collection slightly higher due to validation overhead
        |
        */
        'inputs.fields.email' => [
            'price' => 50, // ₱0.50 (text storage + validation)
            'label' => 'Email Address',
            'description' => 'Collect email address from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.mobile' => [
            'price' => 50, // ₱0.50 (text storage + validation)
            'label' => 'Mobile Number',
            'description' => 'Collect mobile number from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.name' => [
            'price' => 30, // ₱0.30 (text storage only)
            'label' => 'Full Name',
            'description' => 'Collect full name from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.address' => [
            'price' => 50, // ₱0.50 (text storage)
            'label' => 'Full Address',
            'description' => 'Collect complete address from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.birth_date' => [
            'price' => 30, // ₱0.30 (date storage)
            'label' => 'Birth Date',
            'description' => 'Collect birth date for age verification',
            'category' => 'input_fields',
        ],
        'inputs.fields.gross_monthly_income' => [
            'price' => 30, // ₱0.30 (numeric storage)
            'label' => 'Monthly Income',
            'description' => 'Collect gross monthly income data',
            'category' => 'input_fields',
        ],
        'inputs.fields.reference_code' => [
            'price' => 30, // ₱0.30 (text storage)
            'label' => 'Reference Code',
            'description' => 'Collect custom reference code',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | VALIDATION RULES - Security & Compliance
        |--------------------------------------------------------------------------
        |
        | Secret Code: Simple validation logic (₱0.50)
        | Mobile Restriction: Phone number validation (₱0.50)
        | Time Validation: Complex scheduling logic (₱0.80)
        | Location Validation: GPS licensing + geo-fencing (₱1.20)
        | Vendor Alias: Enterprise B2B feature (₱2.00)
        |
        */
        'cash.validation.secret' => [
            'price' => 50, // ₱0.50 (validation logic only)
            'label' => 'Secret Code',
            'description' => 'Require secret code for redemption security',
            'category' => 'validation',
        ],
        'cash.validation.mobile' => [
            'price' => 50, // ₱0.50 (phone validation)
            'label' => 'Mobile Restriction',
            'description' => 'Restrict redemption to specific mobile number',
            'category' => 'validation',
        ],
        'validation.time' => [
            'price' => 80, // ₱0.80 (complex scheduling logic)
            'label' => 'Time Window Validation',
            'description' => 'Restrict redemption to specific time windows and duration limits',
            'category' => 'validation',
        ],
        'validation.location' => [
            'price' => 120, // ₱1.20 (GPS licensing + geo-fencing computation)
            'label' => 'Location Validation',
            'description' => 'Geo-fencing with coordinates and radius restrictions',
            'category' => 'validation',
        ],
        'cash.validation.payable' => [
            'price' => 200, // ₱2.00 (enterprise B2B feature)
            'label' => 'Vendor Alias (B2B)',
            'description' => 'Restrict redemption to specific merchant vendor alias',
            'category' => 'validation',
        ],

        /*
        |--------------------------------------------------------------------------
        | PREMIUM: RIDER FEATURES - Value-Based Marketing Tools
        |--------------------------------------------------------------------------
        |
        | These are PREMIUM features with high marketing/conversion value:
        |
        | Rider Message (₱2.00):
        |   - Basic post-redemption messaging
        |   - Custom instructions or thank-you notes
        |   - Modest value, accessible pricing
        |
        | Rider Splash (₱20.00):
        |   - ADVERTISING REAL ESTATE - 10x multiplier
        |   - Full-screen branded splash page
        |   - Logo, images, custom branding
        |   - High visibility, perfect for brand awareness
        |
        | Rider URL (₱50.00):
        |   - DIGITAL MARKETING CONVERSION TOOL - 25x multiplier
        |   - Redirect to landing page, signup form, app download
        |   - Lead generation and customer onboarding
        |   - Highest ROI for marketers
        |   - Conversion tracking capability
        |
        | PRICING RATIONALE:
        | Rider features enable monetization of the "attention moment" right
        | after successful redemption. Users are engaged and ready to take
        | action - perfect for marketing, onboarding, and conversions.
        |
        */
        'rider.message' => [
            'price' => 200, // ₱2.00 (basic messaging)
            'label' => 'Rider Message',
            'description' => 'Custom message shown after successful redemption',
            'category' => 'rider',
        ],
        'rider.splash' => [
            'price' => 2000, // ₱20.00 (advertising real estate - 10x value)
            'label' => 'Rider Splash Screen',
            'description' => 'Full-screen branded splash page with logo and custom content (advertising space)',
            'category' => 'rider',
        ],
        'rider.url' => [
            'price' => 5000, // ₱50.00 (digital marketing tool - 25x value)
            'label' => 'Rider Redirect URL',
            'description' => 'Redirect to landing page for onboarding, lead generation, or app download (conversion tool)',
            'category' => 'rider',
        ],

        /*
        |--------------------------------------------------------------------------
        | DEPRECATED FEATURES
        |--------------------------------------------------------------------------
        |
        | Legacy validation fields replaced by modern implementations.
        | Kept for backward compatibility but priced at ₱0.00.
        |
        */
        'cash.validation.location' => [
            'price' => 0, // DEPRECATED
            'label' => 'Location String (Legacy)',
            'description' => '[DEPRECATED] Use validation.location with lat/lng coordinates instead',
            'category' => 'validation',
            'deprecated' => true,
            'deprecated_reason' => 'Use validation.location with coordinates and radius_meters for accurate geo-fencing',
        ],
        'cash.validation.radius' => [
            'price' => 0, // DEPRECATED
            'label' => 'Radius String (Legacy)',
            'description' => '[DEPRECATED] Use validation.location.radius_meters instead',
            'category' => 'validation',
            'deprecated' => true,
            'deprecated_reason' => 'Use validation.location.radius_meters for precise radius validation',
        ],
    ],

];
