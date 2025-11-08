<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook API Routes
|--------------------------------------------------------------------------
|
| Public webhook endpoints for external service callbacks.
| Requires signature verification for security.
|
*/

// Payment gateway webhooks
// POST /api/v1/webhooks/payment
Route::post('/payment', [\App\Actions\Api\Webhooks\HandlePaymentWebhook::class, 'asController'])
    ->name('api.webhooks.payment');

// SMS delivery status webhooks
// POST /api/v1/webhooks/sms
Route::post('/sms', [\App\Actions\Api\Webhooks\HandleSmsWebhook::class, 'asController'])
    ->name('api.webhooks.sms');
