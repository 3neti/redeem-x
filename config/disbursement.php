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
    ],
];
