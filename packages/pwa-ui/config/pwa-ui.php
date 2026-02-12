<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PWA Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the PWA routes. Useful for gradual rollout.
    |
    */
    'enabled' => env('PWA_UI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | PWA Version
    |--------------------------------------------------------------------------
    |
    | Version identifier for the PWA. Update this to bust service worker cache.
    |
    */
    'version' => env('PWA_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | PWA Name & Theme
    |--------------------------------------------------------------------------
    |
    | App name and theme color for manifest and meta tags.
    |
    */
    'name' => env('PWA_NAME', 'Redeem-X'),
    'short_name' => env('PWA_SHORT_NAME', 'Redeem-X'),
    'theme_color' => env('PWA_THEME_COLOR', '#3b82f6'),
    'background_color' => env('PWA_BACKGROUND_COLOR', '#ffffff'),

    /*
    |--------------------------------------------------------------------------
    | Start URL
    |--------------------------------------------------------------------------
    |
    | The URL the PWA should open when launched from home screen.
    |
    */
    'start_url' => env('PWA_START_URL', '/pwa/portal'),
];
