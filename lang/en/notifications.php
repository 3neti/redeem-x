<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    |
    | These templates are used for email and SMS notifications.
    | Use {{ variable }} syntax for dynamic values (NOT :variable).
    | Templates are processed by TemplateProcessor service.
    |
    */

    'voucher_redeemed' => [
        'email' => [
            'subject' => 'Voucher {{ code }} Redeemed',
            'greeting' => 'Hello,',
            'body' => 'Voucher **{{ code }}** with the amount of **{{ formatted_amount }}** has been successfully redeemed.',
            
            // Redemption Details section
            'details' => [
                'header' => '**Redemption Details:**',
                'redeemed_by' => '**Redeemed By:** {{ contact_name_or_mobile }}',
                'location' => '**Location:** {{ formatted_address }}',
                'date' => '**Date/Time:** {{ redeemed_at }}',
            ],
            
            // Custom inputs section (conditional)
            'custom_inputs_header' => '**Additional Information:**',
            
            'warning' => 'If you did not authorize this transaction, please contact support immediately.',
            'salutation' => 'Thank you for using our service!',
        ],
        'sms' => [
            // Tier 1: Basic (no images, no custom inputs)
            'basic' => 'Voucher {{ code }} ({{ formatted_amount }}) redeemed by {{ contact_name_or_mobile }}',
            
            // Tier 2: With images (signature/selfie/location)
            // Lists available media types without URLs to stay under SMS character limit
            'with_images' => "Voucher {{ code }} ({{ formatted_amount }}) by {{ contact_name_or_mobile }}.\nMedia: {{ image_links }}\n(See email for full details)",
            
            // Tier 3: With custom inputs + images
            // Lists custom inputs and media types - full details in email
            'with_inputs' => "Voucher {{ code }} ({{ formatted_amount }}) by {{ contact_name_or_mobile }}.\n{{ custom_inputs_formatted }}\nMedia: {{ image_links }} (see email)",
        ],
    ],

    'vouchers_generated' => [
        'sms' => [
            'single' => '✅ {{ count }} voucher generated ({{ formatted_amount }}) - {{ code }}{{ share_links }}',
            'single_with_instructions' => "✅ {{ count }} voucher ({{ formatted_amount }}) - {{ code }}\n{{ instructions_formatted_sms }}{{ share_links }}",
            'multiple' => '✅ {{ count }} vouchers generated ({{ formatted_amount }} each) - {{ codes }}{{ share_links }}',
            'multiple_with_instructions' => "✅ {{ count }} vouchers ({{ formatted_amount }} each) - {{ codes }}\n{{ instructions_formatted_sms }}{{ share_links }}",
            'many' => '✅ {{ count }} vouchers generated ({{ formatted_amount }} each) - {{ first_codes }}, +{{ remaining }} more{{ share_links }}',
            'many_with_instructions' => "✅ {{ count }} vouchers ({{ formatted_amount }} each) - {{ first_codes }}, +{{ remaining }} more\n{{ instructions_formatted_sms }}{{ share_links }}",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Balance Notification Templates
    |--------------------------------------------------------------------------
    */

    'balance' => [
        'user' => [
            'sms' => 'Balance: {{ formatted_balance }}',
            'email' => [
                'subject' => 'Your Balance',
                'greeting' => 'Hello,',
                'body' => 'Your current wallet balance is **{{ formatted_balance }}**.',
                'salutation' => 'Thank you for using our service!',
            ],
        ],
        'system' => [
            'sms' => "Wallet: {{ wallet }} | Products: {{ products }}{{ bank_line }}",
            'email' => [
                'subject' => 'System Balance Report',
                'greeting' => 'System Balance Summary',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Help Notification Templates
    |--------------------------------------------------------------------------
    */

    'help' => [
        // Help messages are complex multi-line strings, kept in notification class
        'general' => 'For general help, text HELP. For command-specific help, text HELP {command}.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Disbursement Failed Notification Templates
    |--------------------------------------------------------------------------
    */

    'disbursement_failed' => [
        'email' => [
            'subject' => '🚨 Disbursement Failed: {{ voucher_code }}',
            'greeting' => 'Disbursement Failure Alert',
            'body' => 'A disbursement has failed and requires immediate attention.',
            'details' => [
                'voucher_code' => '**Voucher Code:** {{ voucher_code }}',
                'amount' => '**Amount:** {{ formatted_amount }}',
                'mobile' => '**Redeemer Mobile:** {{ mobile }}',
                'error' => '**Error:** {{ error_message }}',
                'time' => '**Time:** {{ occurred_at }}',
            ],
            'action' => 'View Voucher Details',
            'footer' => 'Please investigate and take appropriate action for customer support.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Low Balance Alert Templates
    |--------------------------------------------------------------------------
    */

    'low_balance_alert' => [
        'email' => [
            'subject' => '⚠️ Low Balance Alert: {{ account_number }}',
            'greeting' => 'Low Balance Alert',
            'body' => 'Your account balance has fallen below the configured threshold.',
            'details' => [
                'account' => '**Account:** {{ account_number }}',
                'gateway' => '**Gateway:** {{ gateway }}',
                'balance' => '**Current Balance:** {{ formatted_balance }}',
                'available' => '**Available Balance:** {{ formatted_available_balance }}',
                'threshold' => '**Threshold:** {{ formatted_threshold }}',
                'checked_at' => '**Checked At:** {{ checked_at }}',
            ],
            'footer' => 'Please take appropriate action to ensure sufficient funds are available.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Confirmation Templates
    |--------------------------------------------------------------------------
    */

    'payment_confirmation' => [
        'sms' => 'Payment received! ₱{{ amount }} for voucher {{ voucher_code }}. Confirm here: {{ confirmation_url }}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Variables
    |--------------------------------------------------------------------------
    |
    | You can use these variables in your templates:
    |
    | {{ code }}               - Voucher code (e.g., TEST-123)
    | {{ codes }}              - Comma-separated voucher codes (generation only)
    | {{ first_codes }}        - First 3 voucher codes (generation only)
    | {{ count }}              - Number of vouchers (generation only)
    | {{ remaining }}          - Count of remaining codes (generation only)
    | {{ status }}             - Voucher status (active, redeemed, expired)
    | {{ amount }}             - Raw amount (e.g., 50.00)
    | {{ formatted_amount }}   - Formatted amount (e.g., ₱50.00)
    | {{ currency }}           - Currency code (e.g., PHP)
    | {{ mobile }}             - Contact mobile number
    | {{ contact_name }}       - Contact name
    | {{ bank_account }}       - Bank account identifier
    | {{ bank_code }}          - Bank code (e.g., GXCHPHM2XXX)
    | {{ account_number }}     - Account number
    | {{ formatted_address }}  - Formatted address from location input
    | {{ owner_name }}         - Voucher owner name
    | {{ owner_email }}        - Voucher owner email
    | {{ owner_mobile }}       - Voucher owner mobile
    | {{ created_at }}         - Creation timestamp
    | {{ redeemed_at }}        - Redemption timestamp
    | {{ signature }}          - Signature data URL (if captured)
    | {{ location }}           - Raw location JSON (if captured)
    | {{ redemption_endpoint }} - Redemption endpoint path (e.g., /disburse)
    | {{ formatted_balance }}  - Formatted balance amount
    | {{ formatted_threshold }} - Formatted threshold amount
    | {{ error_message }}      - Error message
    | {{ occurred_at }}        - Error occurrence timestamp
    | {{ confirmation_url }}   - Payment confirmation URL
    | {{ <custom_field> }}    - Any custom input field by name
    |
    */

];
