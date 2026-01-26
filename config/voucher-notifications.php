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
    ],

];
