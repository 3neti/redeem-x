<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for form flow routes.
    | Default: 'form-flow'
    | Routes will be: /form-flow/start, /form-flow/{flow_id}, etc.
    |
    */
    'route_prefix' => env('FORM_FLOW_ROUTE_PREFIX', 'form-flow'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to form flow routes.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Driver Directory
    |--------------------------------------------------------------------------
    |
    | Path to directory containing driver configuration files.
    | Supports both absolute and relative paths.
    |
    */
    'driver_directory' => config_path('form-flow-drivers'),

    /*
    |--------------------------------------------------------------------------
    | Session Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for session keys used by form flows.
    | Session keys will be: form_flow.{flow_id}
    |
    */
    'session_prefix' => 'form_flow',

    /*
    |--------------------------------------------------------------------------
    | Handlers
    |--------------------------------------------------------------------------
    |
    | Registered form handlers.
    | Key: handler name, Value: handler class (must implement FormHandlerInterface)
    |
    */
    'handlers' => [
        'missing' => \LBHurtado\FormFlowManager\Handlers\MissingHandler::class,
        // Plugin handlers register themselves via service providers
    ],
];
