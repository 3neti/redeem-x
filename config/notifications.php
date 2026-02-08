<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which channels should be used for each notification type.
    | This allows centralized control over notification delivery without
    | modifying code.
    |
    | Available channels:
    | - 'engage_spark' : SMS via EngageSpark
    | - 'mail'         : Email
    | - 'database'     : Database storage (automatically added for User models)
    | - WebhookChannel : Custom webhook delivery
    |
    */

    'channels' => [
        'balance' => explode(',', env('BALANCE_NOTIFICATION_CHANNELS', 'engage_spark')),
        'disbursement_failed' => explode(',', env('DISBURSEMENT_FAILED_CHANNELS', 'mail')),
        'help' => explode(',', env('HELP_NOTIFICATION_CHANNELS', 'engage_spark')),
        'low_balance_alert' => explode(',', env('LOW_BALANCE_ALERT_CHANNELS', 'mail')),
        'payment_confirmation' => explode(',', env('PAYMENT_CONFIRMATION_CHANNELS', 'engage_spark')),
        'voucher_redeemed' => explode(',', env('VOUCHER_REDEEMED_CHANNELS', 'mail,engage_spark')),
        'vouchers_generated' => explode(',', env('VOUCHERS_GENERATED_CHANNELS', 'engage_spark')),
        'test' => explode(',', env('TEST_NOTIFICATION_CHANNELS', 'engage_spark,mail,database')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue priorities for different notification types.
    | Notifications are assigned to queues based on criticality.
    |
    | Queue worker should process all queues:
    | php artisan queue:work --queue=high,normal,low
    |
    */

    'queue' => [
        'default_connection' => env('NOTIFICATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'default_queue' => env('NOTIFICATION_QUEUE', 'default'),

        // Priority queues for different notification types
        'queues' => [
            // High priority: Critical alerts that require immediate attention
            'high' => [
                'disbursement_failed',
                'low_balance_alert',
            ],

            // Normal priority: User-facing notifications
            'normal' => [
                'payment_confirmation',
                'voucher_redeemed',
            ],

            // Low priority: Informational notifications
            'low' => [
                'vouchers_generated',
                'balance',
                'help',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which notifiable types should have notifications logged
    | to the database for audit trail purposes.
    |
    */

    'database_logging' => [
        'enabled' => env('NOTIFICATION_DATABASE_LOGGING', true),

        // Always log notifications sent to these model types
        'always_log_for' => [
            'App\\Models\\User',
            'User',
            'App\\Models\\PaymentRequest',
            'PaymentRequest',
        ],

        // Never log notifications sent to these types
        'never_log_for' => [
            'Illuminate\\Notifications\\AnonymousNotifiable',
            'AnonymousNotifiable',
        ],
    ],

];
