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

    /*
    |--------------------------------------------------------------------------
    | Available Variables
    |--------------------------------------------------------------------------
    |
    | You can use these variables in your templates:
    |
    | {{ code }}               - Voucher code (e.g., TEST-123)
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
