<?php

return [
    'models' => [
        'user' => class_exists(App\Models\User::class)
            ? App\Models\User::class
            : LBHurtado\PaymentGateway\Tests\Models\User::class,
    ],
    'default' => env('PAYMENT_GATEWAY', 'netbank'),

    'drivers' => [
        'netbank' => [
            'base_url' => env('NETBANK_API_URL'),
            'api_key' => env('NETBANK_API_KEY'),
        ],
        'icash' => [
            'base_url' => env('ICASH_API_URL'),
            'api_key' => env('ICASH_API_KEY'),
        ],
    ],

    'gateway' => LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway::class,

    /*
    |--------------------------------------------------------------------------
    | QR Code Caching
    |--------------------------------------------------------------------------
    |
    | Configure how long QR codes should be cached (in seconds).
    | Set to 0 to disable caching. Default is 1 hour (3600 seconds).
    | This reduces API calls to the payment gateway for QR generation.
    |
    */
    'qr_cache_ttl' => env('PAYMENT_GATEWAY_QR_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | QR Merchant Name Template
    |--------------------------------------------------------------------------
    |
    | Configure how the merchant name appears in QR codes.
    | Available variables: {name}, {city}, {app_name}
    |
    | Examples:
    |   "{name} • {city}"     => "3neti R&D OPC • Manila"
    |   "{name}"              => "3neti R&D OPC"
    |   "{app_name}"          => "Redeem-X"
    |   "{city} - {name}"     => "Manila - 3neti R&D OPC"
    |
    */
    'qr_merchant_name' => [
        'template' => env('QR_MERCHANT_NAME_TEMPLATE', '{name} - {city}'),
        'uppercase' => env('QR_MERCHANT_NAME_UPPERCASE', false),
        'fallback' => env('QR_MERCHANT_NAME_FALLBACK', config('app.name')),
    ],

    'routes' => [
        'enabled' => env('PAYMENT_GATEWAY_ROUTES_ENABLED', true),

        'prefix' => env('PAYMENT_GATEWAY_ROUTE_PREFIX', 'api'),

        'middleware' => ['api'],

        'name_prefix' => env('PAYMENT_GATEWAY_ROUTE_NAME_PREFIX', ), // e.g., 'pg.'

        'version' => env('PAYMENT_GATEWAY_ROUTE_VERSION',), // e.g., 'v1'

        'domain' => env('PAYMENT_GATEWAY_DOMAIN'), // optional
    ],
];
