<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Voucher Metadata Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for voucher metadata that gets embedded in each voucher.
    | This allows "x-ray" inspection of vouchers before redemption.
    |
    */

    'metadata' => [
        // Voucher schema version
        'version' => env('VOUCHER_VERSION', '1.0.0'),

        // System/application name
        'system_name' => env('APP_NAME', 'Redeem-X'),

        // Copyright holder
        'copyright' => env('VOUCHER_COPYRIGHT', '3neti R&D OPC'),

        // Regulatory licenses and registrations
        // Null values are filtered out at runtime
        'licenses' => [
            'BSP' => env('LICENSE_BSP'),
            'SEC' => env('LICENSE_SEC'),
            'NTC' => env('LICENSE_NTC'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption Endpoints
    |--------------------------------------------------------------------------
    |
    | Configure available redemption endpoints for vouchers.
    |
    */

    'redemption' => [
        // Widget/iframe redemption URL (optional)
        'widget_url' => env('VOUCHER_WIDGET_URL'),

        // Additional custom endpoints (can be added as needed)
        'custom_endpoints' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Optional digital signature support for voucher verification.
    |
    */

    'security' => [
        // Enable digital signatures (requires public/private key pair)
        'enable_signatures' => env('VOUCHER_ENABLE_SIGNATURES', false),

        // Public key for signature verification (optional)
        'public_key' => env('VOUCHER_PUBLIC_KEY'),

        // Private key for signing (never expose, keep secure)
        'private_key' => env('VOUCHER_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher Attachments
    |--------------------------------------------------------------------------
    |
    | Configuration for voucher file attachments (invoices, receipts, photos).
    | Attachments are stored using Spatie Media Library.
    |
    */

    'attachments' => [
        // Maximum file size in kilobytes (default: 2MB)
        'max_file_size_kb' => env('VOUCHER_ATTACHMENT_MAX_SIZE_KB', 2048),

        // Allowed MIME types for attachments
        'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],

        // Storage disk (default: public)
        'disk' => env('VOUCHER_ATTACHMENT_DISK', 'public'),
    ],
];
