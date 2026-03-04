<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model to use for user relationships. Defaults to App\Models\User.
    |
    */
    'user_model' => env('MERCHANT_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Alias Validation Rules
    |--------------------------------------------------------------------------
    |
    | Validation rules for vendor aliases.
    |
    */
    'alias' => [
        'min_length' => 3,
        'max_length' => 8,
        'pattern' => '^[A-Z][A-Z0-9]{2,7}$', // Starts with letter, 3-8 chars, uppercase letters/digits
    ],
];
