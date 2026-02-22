<?php

use App\Http\Controllers\Bot\SelfieCaptureController;
use App\Http\Controllers\Bot\SignatureCaptureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bot Routes
|--------------------------------------------------------------------------
|
| Routes for Telegram Mini App functionality. These routes are used by
| the bot to serve WebApp pages that open inside Telegram's WebView.
|
*/

// Selfie capture Mini App
Route::get('/bot/selfie-capture', [SelfieCaptureController::class, 'show'])
    ->name('bot.selfie.show');

// Selfie upload API endpoint (CSRF exempt - called from Mini App)
Route::post('/api/bot/selfie-upload', [SelfieCaptureController::class, 'store'])
    ->name('bot.selfie.store');

// Signature capture Mini App
Route::get('/bot/signature-capture', [SignatureCaptureController::class, 'show'])
    ->name('bot.signature.show');

// Signature upload API endpoint (CSRF exempt - called from Mini App)
Route::post('/api/bot/signature-upload', [SignatureCaptureController::class, 'store'])
    ->name('bot.signature.store');
