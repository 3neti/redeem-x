<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Balance Viewing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure who can view balance information and default account settings.
    |
    */

    /**
     * Enable or disable balance viewing globally.
     * 
     * Set to false to completely disable the balance page.
     */
    'view_enabled' => env('BALANCE_VIEW_ENABLED', true),

    /**
     * Required role to view balance information.
     * 
     * Users must have this role to access the balance page.
     * Change via BALANCE_VIEW_ROLE in .env
     */
    'view_role' => env('BALANCE_VIEW_ROLE', 'admin'),

    /**
     * Default account number for balance checking.
     * 
     * Falls back to payment-gateway, omnipay, or disbursement config.
     */
    'default_account' => env('BALANCE_DEFAULT_ACCOUNT'),

    /**
     * Alert configuration defaults.
     */
    'alerts' => [
        /**
         * Default low balance threshold (in centavos).
         * Example: 1000000 = ₱10,000.00
         */
        'default_threshold' => env('BALANCE_ALERT_THRESHOLD', 1000000),

        /**
         * Default alert recipients (comma-separated emails).
         */
        'default_recipients' => env('BALANCE_ALERT_RECIPIENTS', ''),
    ],

    /**
     * Balance check scheduling configuration.
     */
    'schedule' => [
        /**
         * Enable or disable automatic balance checks.
         */
        'enabled' => env('BALANCE_SCHEDULE_ENABLED', true),

        /**
         * Cron expression for balance checks.
         * Default: hourly
         */
        'cron' => env('BALANCE_SCHEDULE_CRON', '0 * * * *'),
    ],

];
