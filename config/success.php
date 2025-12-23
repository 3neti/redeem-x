<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Success Content
    |--------------------------------------------------------------------------
    |
    | The default HTML content to display on the success page when no custom
    | message is provided. Supports template variables:
    | - {app_name}: Application name
    | - {voucher_code}: Redeemed voucher code
    | - {amount}: Formatted amount
    | - {mobile}: Redeemer mobile number
    | - {app_author}: Application author
    | - {copyright_year}: Current year
    |
    */

    'default_content' => env('SUCCESS_DEFAULT_CONTENT', null),

    /*
    |--------------------------------------------------------------------------
    | Button Labels
    |--------------------------------------------------------------------------
    |
    | Customize button text on the success page.
    |
    */

    'button_label' => env('SUCCESS_BUTTON_LABEL', 'Continue Now'),
    'dashboard_button_label' => env('SUCCESS_DASHBOARD_BUTTON_LABEL', 'Go to Dashboard'),
    'redeem_another_label' => env('SUCCESS_REDEEM_ANOTHER_LABEL', 'Redeem Another'),

];
