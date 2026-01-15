<?php

return [
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
