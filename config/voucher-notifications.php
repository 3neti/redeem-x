<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voucher Notification Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which notification channels should be used for different
    | voucher-related notifications. This allows you to enable/disable
    | SMS, email, etc. without changing code.
    |
    */

    'vouchers_generated' => [
        /*
        | Channels to use when notifying users about voucher generation.
        |
        | Available channels:
        | - 'engage_spark' : SMS via EngageSpark
        | - 'mail'         : Email
        | - 'database'     : Always included for User models (audit trail)
        |
        | Production recommendation:
        | - Start with ['engage_spark'] (SMS only)
        | - Add 'mail' once email templates are tested
        | - 'database' is automatically added for User models
        |
        | Environment variable: VOUCHERS_GENERATED_CHANNELS
        | Default: engage_spark
        | Example: VOUCHERS_GENERATED_CHANNELS=engage_spark,mail
        */
        'channels' => explode(',', env('VOUCHERS_GENERATED_CHANNELS', 'engage_spark')),

        /*
        | Voucher instructions format in notifications.
        |
        | Options:
        | - 'none'  : Don't include instructions (default, current behavior)
        | - 'json'  : Pretty-printed JSON format
        | - 'human' : Human-readable text format
        |
        | Environment variable: VOUCHER_INSTRUCTIONS_FORMAT
        | Default: human
        */
        'instructions_format' => env('VOUCHER_INSTRUCTIONS_FORMAT', 'human'),

        /*
        | Include shareable links in voucher generation notifications.
        |
        | When enabled, notifications will include shareable links based on voucher type:
        | - REDEEMABLE: /disburse?code=ABCD
        | - PAYABLE: /pay?code=EFGH
        | - SETTLEMENT: Both links
        |
        | Environment variable: VOUCHER_INCLUDE_SHARE_LINKS
        | Default: true
        */
        'include_share_links' => env('VOUCHER_INCLUDE_SHARE_LINKS', true),
    ],

    'balance' => [
        /*
        | Channels to use when notifying users about balance queries.
        |
        | Available channels:
        | - 'engage_spark' : SMS via EngageSpark
        | - 'mail'         : Email
        | - 'database'     : Always included for User models (audit trail)
        |
        | Production recommendation:
        | - Start with ['engage_spark'] (SMS only)
        | - Add 'mail' for admin system balance notifications
        |
        | Environment variable: BALANCE_NOTIFICATION_CHANNELS
        | Default: engage_spark
        | Example: BALANCE_NOTIFICATION_CHANNELS=engage_spark,mail
        */
        'channels' => explode(',', env('BALANCE_NOTIFICATION_CHANNELS', 'engage_spark')),
    ],

];
