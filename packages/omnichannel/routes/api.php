<?php

use LBHurtado\OmniChannel\Http\Controllers\SMSController;
use Illuminate\Support\Facades\Route;

// Authenticated SMS endpoint (requires Bearer token)
// Handles: GENERATE, PAYABLE, SETTLEMENT, BALANCE commands
// Also allows authenticated users to redeem vouchers
Route::middleware('auth:sanctum')->post('sms', SMSController::class)->name('sms');

// Public SMS endpoint (no authentication required)
// Handles: Voucher redemption by code, REGISTER, etc.
Route::post('sms/public', SMSController::class)->name('sms.public');
