<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Splash Page Display
    |--------------------------------------------------------------------------
    |
    | Determines whether the splash page is shown during voucher redemption.
    | Set to false to disable the splash page entirely.
    |
    */

    'enabled' => env('SPLASH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Splash Timeout
    |--------------------------------------------------------------------------
    |
    | The default number of seconds to display the splash page before
    | automatically proceeding to the next step. Set to 0 to disable
    | auto-advance (user must click "Continue").
    |
    */

    'default_timeout' => env('SPLASH_DEFAULT_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Default Splash Content
    |--------------------------------------------------------------------------
    |
    | The default HTML content to display when no custom splash content
    | is provided. Available variables:
    | - {app_name}: Application name from APP_NAME
    | - {app_version}: Application version from composer.json
    | - {app_author}: Author/company name
    | - {copyright_year}: Current year for copyright notice
    | - {copyright_holder}: Copyright holder name
    | - {voucher_code}: The voucher code being redeemed (if available)
    |
    */

    'default_content' => env('SPLASH_DEFAULT_CONTENT', null), // Use built-in if null

    /*
    |--------------------------------------------------------------------------
    | Application Metadata
    |--------------------------------------------------------------------------
    |
    | Metadata displayed in the default splash screen.
    |
    */

    'app_author' => env('SPLASH_APP_AUTHOR', '3neti R&D OPC'),
    'copyright_holder' => env('SPLASH_COPYRIGHT_HOLDER', '3neti R&D OPC'),
    'copyright_year' => env('SPLASH_COPYRIGHT_YEAR', date('Y')),

    /*
    |--------------------------------------------------------------------------
    | Button Label
    |--------------------------------------------------------------------------
    |
    | The text displayed on the continue button.
    |
    */

    'button_label' => env('SPLASH_BUTTON_LABEL', 'Continue Now'),

];
