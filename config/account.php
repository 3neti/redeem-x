<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System User Configuration
    |--------------------------------------------------------------------------
    |
    | The system user acts as the master wallet holder for all disbursements.
    | User wallets receive funds via transfers from the system wallet.
    |
    */
    'system_user' => [
        'model' => \App\Models\User::class,
        'identifier' => env('SYSTEM_USER_ID', 'admin@disburse.cash'),
        'identifier_column' => 'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Revenue User Configuration
    |--------------------------------------------------------------------------
    |
    | The revenue user is the default destination for InstructionItem fees.
    | Each InstructionItem can override this by setting its own revenueDestination.
    |
    | Leave identifier null to fallback to system user.
    |
    */
    'revenue_user' => [
        'model' => \App\Models\User::class,
        'identifier' => env('REVENUE_USER_ID', null), // null = use system user
        'identifier_column' => 'email',
    ],
];
