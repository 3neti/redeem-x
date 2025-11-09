<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redeem Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the redeem widget.
    | These settings control what elements are visible in the widget.
    |
    */

    'widget' => [
        'show_logo' => env('REDEEM_WIDGET_SHOW_LOGO', true),
        'show_app_name' => env('REDEEM_WIDGET_SHOW_APP_NAME', false),
        'show_label' => env('REDEEM_WIDGET_SHOW_LABEL', true),
        'show_title' => env('REDEEM_WIDGET_SHOW_TITLE', false),
        'show_description' => env('REDEEM_WIDGET_SHOW_DESCRIPTION', true),

        // Custom text overrides
        'title' => env('REDEEM_WIDGET_TITLE', 'Redeem Voucher'),
        'description' => env('REDEEM_WIDGET_DESCRIPTION', null),
        'label' => env('REDEEM_WIDGET_LABEL', 'code'),
        'placeholder' => env('REDEEM_WIDGET_PLACEHOLDER', 'x x x x'),
        'button_text' => env('REDEEM_WIDGET_BUTTON_TEXT', 'redeem'),
        'button_processing_text' => env('REDEEM_WIDGET_BUTTON_PROCESSING_TEXT', 'Checking...'),
    ],

];
