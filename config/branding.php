<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logo Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the logo theme for the application. Supports multiple themes
    | with separate assets for light and dark modes.
    |
    | Themes:
    | - gray: Professional slate/silver (default)
    | - orange: Orange brand color (special occasions)
    | - custom: User-defined paths via environment variables
    |
    */

    'logo' => [
        'theme' => env('LOGO_THEME', 'gray'),

        'themes' => [
            'gray' => [
                'light' => '/images/logo-slate.png',
                'dark' => '/images/logo-silver.png',
            ],
            'orange' => [
                'light' => '/images/logo-orange.png',
                'dark' => '/images/logo-orange.png',
            ],
            'custom' => [
                'light' => env('LOGO_CUSTOM_LIGHT'),
                'dark' => env('LOGO_CUSTOM_DARK'),
            ],
        ],

        // Fallback logo if theme not found or config missing
        'fallback' => '/images/logo.png',
    ],

];
