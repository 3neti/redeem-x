<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\MessagingBot\Http\Controllers\TelegramWebhookController;

/*
|--------------------------------------------------------------------------
| Messaging Bot Routes
|--------------------------------------------------------------------------
|
| Webhook endpoints for each messaging platform. These routes receive
| incoming messages from Telegram, WhatsApp, Viber, etc.
|
*/

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->name('messaging.telegram.webhook');

// Future drivers
// Route::post('/whatsapp/webhook', WhatsAppWebhookController::class)
//     ->name('messaging.whatsapp.webhook');

// Route::post('/viber/webhook', ViberWebhookController::class)
//     ->name('messaging.viber.webhook');
