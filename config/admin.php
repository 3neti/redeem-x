<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Override Emails
    |--------------------------------------------------------------------------
    |
    | List of email addresses that should bypass admin permission checks.
    | These users will have access to all admin pages regardless of their
    | assigned roles or permissions.
    |
    | Can be set via ADMIN_OVERRIDE_EMAILS env variable (comma-separated).
    |
    */
    'override_emails' => array_filter(
        explode(',', env('ADMIN_OVERRIDE_EMAILS', ''))
    ),
];
