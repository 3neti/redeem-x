<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Messaging Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default messaging driver that will be used
    | when processing incoming messages. You may set this to any of the
    | drivers defined in the "drivers" array below.
    |
    */

    'default' => env('MESSAGING_BOT_DRIVER', 'telegram'),

    /*
    |--------------------------------------------------------------------------
    | Conversation TTL
    |--------------------------------------------------------------------------
    |
    | The number of seconds a conversation state should be kept in cache
    | before expiring. After this time, users will need to restart their
    | multi-step flow from the beginning.
    |
    */

    'conversation_ttl' => env('MESSAGING_BOT_CONVERSATION_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | Messaging Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the messaging drivers for your application.
    | Each driver has its own configuration options specific to the
    | messaging platform it integrates with.
    |
    */

    'drivers' => [

        'telegram' => [
            'enabled' => env('TELEGRAM_BOT_ENABLED', true),
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
            'admin_chat_ids' => array_filter(
                explode(',', env('TELEGRAM_ADMIN_CHAT_IDS', ''))
            ),
        ],

        'whatsapp' => [
            'enabled' => env('WHATSAPP_BOT_ENABLED', false),
            // WhatsApp Business API configuration (Phase 2)
        ],

        'viber' => [
            'enabled' => env('VIBER_BOT_ENABLED', false),
            // Viber API configuration (Phase 2)
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Command Handlers
    |--------------------------------------------------------------------------
    |
    | Single-step command handlers that respond immediately without
    | requiring conversation state. These are mapped by intent name.
    |
    */

    'handlers' => [
        'start' => \LBHurtado\MessagingBot\Handlers\StartHandler::class,
        'help' => \LBHurtado\MessagingBot\Handlers\HelpHandler::class,
        'link' => \LBHurtado\MessagingBot\Handlers\LinkHandler::class,
        'balance' => \LBHurtado\MessagingBot\Handlers\BalanceHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Step Flows
    |--------------------------------------------------------------------------
    |
    | Flows that require multiple messages to complete. Each flow manages
    | its own state and step progression through the conversation.
    |
    */

    'flows' => [
        'redeem' => \LBHurtado\MessagingBot\Flows\RedeemFlow::class,
        'generate' => \LBHurtado\MessagingBot\Flows\GenerateFlow::class,
        'disburse' => \LBHurtado\MessagingBot\Flows\DisburseFlow::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for webhook endpoints.
    |
    */

    'routes' => [
        'prefix' => env('MESSAGING_BOT_ROUTE_PREFIX', 'messaging'),
        'middleware' => ['api', 'throttle:60,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mini App Configuration
    |--------------------------------------------------------------------------
    |
    | URLs for Telegram Mini Apps that open in WebView for enhanced UX.
    | These must be HTTPS URLs in production.
    |
    */

    'mini_app' => [
        // URL for selfie capture Mini App (opens camera for iOS, file picker for Android)
        'selfie_url' => env('TELEGRAM_MINI_APP_SELFIE_URL'),
    ],

];
