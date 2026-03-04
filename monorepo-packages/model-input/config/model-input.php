<?php

return [
    'rules' => [
        'email' => ['required', 'email'],
        'mobile' => ['required', 'phone:PH,mobile'], // String format instead of object for serialization
        'signature' => ['required', 'string', 'min:8'],
        'bank_account' => ['required', 'string', 'min:8'], // TODO: increase the min
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'address' => ['required', 'string', 'min:10', 'max:255'],
        'birth_date' => ['required', 'date', 'before_or_equal:today'],
        'gross_monthly_income' => ['required', 'numeric', 'min:10000', 'max:1000000'],
        'location' => ['required', 'string'],
        'reference_code' => ['required', 'string'],
        'otp' => ['required', 'string', 'min:4', 'max:6'],
        'selfie' => ['required', 'string', 'min:8'], // base64 image data
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Quality Settings
    |--------------------------------------------------------------------------
    |
    | Configure image capture quality for selfie and signature.
    | Lower values = smaller file size but lower quality.
    |
    */

    'image_quality' => [
        'selfie' => [
            'width' => 640,          // Video/capture width in pixels
            'height' => 480,         // Video/capture height in pixels
            'quality' => 0.8,        // JPEG quality (0.0 to 1.0)
            'format' => 'image/jpeg', // image/jpeg or image/png
        ],
        'signature' => [
            'quality' => 0.8,        // PNG quality (0.0 to 1.0)
            'format' => 'image/png', // image/png (better for line art)
        ],
    ],
];
