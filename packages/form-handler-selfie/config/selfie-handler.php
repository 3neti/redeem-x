<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Width
    |--------------------------------------------------------------------------
    |
    | Default width for captured selfie images in pixels.
    |
    */
    'width' => env('SELFIE_HANDLER_WIDTH', 640),
    
    /*
    |--------------------------------------------------------------------------
    | Image Height
    |--------------------------------------------------------------------------
    |
    | Default height for captured selfie images in pixels.
    |
    */
    'height' => env('SELFIE_HANDLER_HEIGHT', 480),
    
    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | JPEG/WebP compression quality (0.0 to 1.0).
    | Higher values = better quality, larger file size.
    |
    */
    'quality' => env('SELFIE_HANDLER_QUALITY', 0.85),
    
    /*
    |--------------------------------------------------------------------------
    | Image Format
    |--------------------------------------------------------------------------
    |
    | MIME type for captured images.
    | Options: 'image/jpeg', 'image/png', 'image/webp'
    |
    */
    'format' => env('SELFIE_HANDLER_FORMAT', 'image/jpeg'),
    
    /*
    |--------------------------------------------------------------------------
    | Camera Facing Mode
    |--------------------------------------------------------------------------
    |
    | Which camera to use by default.
    | Options: 'user' (front camera), 'environment' (back camera)
    |
    */
    'facing_mode' => env('SELFIE_HANDLER_FACING_MODE', 'user'),
    
    /*
    |--------------------------------------------------------------------------
    | Show Face Guide
    |--------------------------------------------------------------------------
    |
    | Whether to show the oval face guide overlay during capture.
    |
    */
    'show_guide' => env('SELFIE_HANDLER_SHOW_GUIDE', true),
];
