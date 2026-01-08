<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pay/Settlement Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Enable or disable the pay/settlement voucher feature globally.
    | When disabled, /pay routes return 404.
    |
    */

    'enabled' => env('PAY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Pay Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the pay widget.
    | These settings control what elements are visible in the widget.
    |
    */

    'widget' => [
        'show_logo' => env('PAY_WIDGET_SHOW_LOGO', true),
        'show_app_name' => env('PAY_WIDGET_SHOW_APP_NAME', false),
        'show_label' => env('PAY_WIDGET_SHOW_LABEL', true),
        'show_title' => env('PAY_WIDGET_SHOW_TITLE', false),
        'show_description' => env('PAY_WIDGET_SHOW_DESCRIPTION', true),

        // Custom text overrides
        'title' => env('PAY_WIDGET_TITLE', 'Pay Voucher'),
        'description' => env('PAY_WIDGET_DESCRIPTION', null),
        'label' => env('PAY_WIDGET_LABEL', 'code'),
        'placeholder' => env('PAY_WIDGET_PLACEHOLDER', 'x x x x'),
        'button_text' => env('PAY_WIDGET_BUTTON_TEXT', 'pay'),
        'button_processing_text' => env('PAY_WIDGET_BUTTON_PROCESSING_TEXT', 'Checking...'),
    ],

];
