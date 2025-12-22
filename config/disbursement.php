<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disbursement Failure Alerts
    |--------------------------------------------------------------------------
    |
    | Configure notifications when disbursements fail. Alerts can be sent to
    | admin users or specific email addresses for immediate escalation.
    |
    */

    'alerts' => [
        'enabled' => env('DISBURSEMENT_ALERT_ENABLED', true),
        
        'emails' => array_filter(explode(',', env('DISBURSEMENT_ALERT_EMAILS', ''))),
        
        /*
        |--------------------------------------------------------------------------
        | Alert Throttling
        |--------------------------------------------------------------------------
        |
        | Prevent alert spam during outages by throttling duplicate alerts.
        | After the first alert for an error type, subsequent alerts of the same
        | type are suppressed for this many minutes.
        |
        | Examples:
        | - 30 minutes: First timeout alert sent, next 100 timeouts suppressed for 30min
        | - 0: Disable throttling (send every alert)
        |
        */
        'throttle_minutes' => env('DISBURSEMENT_ALERT_THROTTLE_MINUTES', 30),
    ],
];
