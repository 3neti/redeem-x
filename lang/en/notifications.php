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
            'subject' => 'Voucher Code Redeemed',
            'greeting' => 'Hello,',
            'body' => 'The voucher code **{{ code }}** with the amount of **{{ formatted_amount }}** has been successfully redeemed. It was claimed by **{{ mobile }}** from {{ formatted_address }}.',
            'warning' => 'If you did not authorize this transaction, please contact support immediately.',
            'salutation' => 'Thank you for using our service!',
        ],
        'sms' => [
            'message' => 'Voucher {{ code }} with amount {{ formatted_amount }} was redeemed by {{ mobile }}.',
            'message_with_address' => 'Voucher {{ code }} with amount {{ formatted_amount }} was redeemed by {{ mobile }} from {{ formatted_address }}.',
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
    | {{ <custom_field> }}    - Any custom input field by name
    |
    */

];
