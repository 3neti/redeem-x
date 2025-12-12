<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canvas Width
    |--------------------------------------------------------------------------
    |
    | Default width for signature canvas in pixels.
    |
    */
    'width' => env('SIGNATURE_HANDLER_WIDTH', 600),
    
    /*
    |--------------------------------------------------------------------------
    | Canvas Height
    |--------------------------------------------------------------------------
    |
    | Default height for signature canvas in pixels.
    |
    */
    'height' => env('SIGNATURE_HANDLER_HEIGHT', 256),
    
    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | JPEG/WebP compression quality (0.0 to 1.0).
    | Higher values = better quality, larger file size.
    |
    */
    'quality' => env('SIGNATURE_HANDLER_QUALITY', 0.85),
    
    /*
    |--------------------------------------------------------------------------
    | Image Format
    |--------------------------------------------------------------------------
    |
    | MIME type for captured signature images.
    | Options: 'image/png', 'image/jpeg', 'image/webp'
    | Note: PNG is recommended for line art (signatures).
    |
    */
    'format' => env('SIGNATURE_HANDLER_FORMAT', 'image/png'),
    
    /*
    |--------------------------------------------------------------------------
    | Line Width
    |--------------------------------------------------------------------------
    |
    | Width of the signature line stroke in pixels.
    |
    */
    'line_width' => env('SIGNATURE_HANDLER_LINE_WIDTH', 2),
    
    /*
    |--------------------------------------------------------------------------
    | Line Color
    |--------------------------------------------------------------------------
    |
    | Color of the signature line (hex color code).
    |
    */
    'line_color' => env('SIGNATURE_HANDLER_LINE_COLOR', '#000000'),
    
    /*
    |--------------------------------------------------------------------------
    | Line Cap
    |--------------------------------------------------------------------------
    |
    | Style of line endings.
    | Options: 'butt', 'round', 'square'
    |
    */
    'line_cap' => env('SIGNATURE_HANDLER_LINE_CAP', 'round'),
    
    /*
    |--------------------------------------------------------------------------
    | Line Join
    |--------------------------------------------------------------------------
    |
    | Style of line joins.
    | Options: 'bevel', 'round', 'miter'
    |
    */
    'line_join' => env('SIGNATURE_HANDLER_LINE_JOIN', 'round'),
];
