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
     * Set to empty string or null to allow all authenticated users.
     * Change via BALANCE_VIEW_ROLE in .env
     *
     * Examples:
     * - 'admin' - Only admins can view (default)
     * - 'manager' - Only managers can view
     * - null or '' - Any authenticated user can view
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

    /**
     * Balance reconciliation configuration.
     * Ensures system balance never exceeds bank balance.
     */
    'reconciliation' => [
        /**
         * Enable or disable reconciliation checks.
         */
        'enabled' => env('BALANCE_RECONCILIATION_ENABLED', true),

        /**
         * Safety buffer percentage (e.g., 10 = keep 10% reserve).
         */
        'buffer' => env('BALANCE_RECONCILIATION_BUFFER', 10),

        /**
         * Custom buffer amount in centavos (overrides percentage if set).
         * Example: 5000000 = ₱50,000.00
         */
        'buffer_amount' => env('BALANCE_RECONCILIATION_BUFFER_AMOUNT'),

        /**
         * Warning threshold percentage (e.g., 90 = warn at 90% usage).
         */
        'warning_threshold' => env('BALANCE_RECONCILIATION_WARNING_THRESHOLD', 90),

        /**
         * Block voucher generation if would exceed bank balance.
         */
        'block_generation' => env('BALANCE_RECONCILIATION_BLOCK_GENERATION', true),

        /**
         * Email addresses to alert on critical discrepancies (comma-separated).
         */
        'alert_emails' => env('BALANCE_RECONCILIATION_ALERT_EMAILS', ''),

        /**
         * EMERGENCY OVERRIDE: Disable all reconciliation checks.
         * ⚠️ USE WITH EXTREME CAUTION!
         */
        'override' => env('BALANCE_RECONCILIATION_OVERRIDE', false),

        /**
         * Allow voucher generation even if would exceed bank balance.
         * ⚠️ NOT RECOMMENDED FOR PRODUCTION!
         */
        'allow_overgeneration' => env('BALANCE_RECONCILIATION_ALLOW_OVERGENERATION', false),

        /**
         * Suppress warning messages in UI (still logs).
         */
        'suppress_warnings' => env('BALANCE_RECONCILIATION_SUPPRESS_WARNINGS', false),
    ],

];
