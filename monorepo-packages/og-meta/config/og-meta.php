<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Renderer
    |--------------------------------------------------------------------------
    | 'screenshot' — Cloudflare Browser Rendering via spatie/laravel-screenshot
    | 'gd'         — GD library (no external service, limited HTML support)
    */
    'renderer' => 'gd',

    /*
    |--------------------------------------------------------------------------
    | Image Dimensions
    |--------------------------------------------------------------------------
    */
    'dimensions' => [
        'width' => 1200,
        'height' => 630,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    | Set to null to use the package-bundled Inter fonts (SIL OFL licensed).
    | Override with absolute paths to use custom fonts.
    */
    'fonts' => [
        'bold' => null,
        'regular' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | App Name
    |--------------------------------------------------------------------------
    | Displayed on the OG image card. Defaults to config('app.name').
    */
    'app_name' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache_disk' => 'public',
    'cache_prefix' => 'og',

    /*
    |--------------------------------------------------------------------------
    | Resolvers
    |--------------------------------------------------------------------------
    | Map route name patterns to resolver classes. The resolver key is also
    | used as the image route segment: GET /og/{resolverKey}/{identifier}.
    |
    | Example:
    |   'disburse.*' => \App\OgResolvers\VoucherOgResolver::class,
    |   'pay.*'      => \App\OgResolvers\PaymentOgResolver::class,
    */
    'resolvers' => [
        // Register your resolvers here
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Color Map
    |--------------------------------------------------------------------------
    | Maps status strings to background and badge RGB colors.
    | Unknown statuses fall back to neutral gray.
    */
    'statuses' => [
        'active' => ['bg' => [220, 252, 231], 'badge' => [22, 163, 74]],
        'redeemed' => ['bg' => [229, 231, 235], 'badge' => [107, 114, 128]],
        'expired' => ['bg' => [254, 226, 226], 'badge' => [220, 38, 38]],
        'pending' => ['bg' => [254, 243, 199], 'badge' => [202, 138, 4]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Status Colors
    |--------------------------------------------------------------------------
    | Used when a resolver returns a status string not listed above.
    */
    'fallback_status' => [
        'bg' => [243, 244, 246],    // gray-100
        'badge' => [107, 114, 128], // gray-500
    ],

];
