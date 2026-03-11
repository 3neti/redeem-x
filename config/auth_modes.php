<?php

return [
    'default_auth_mode' => env('AUTH_DEFAULT_MODE', 'local'),
    'enable_workos' => env('AUTH_ENABLE_WORKOS', false),
    'enable_local_login' => env('AUTH_ENABLE_LOCAL_LOGIN', true),
    'enable_registration' => env('AUTH_ENABLE_REGISTRATION', true),
    'enable_password_login' => env('AUTH_ENABLE_PASSWORD_LOGIN', true),
    'enable_mobile_login' => env('AUTH_ENABLE_MOBILE_LOGIN', true),
];
