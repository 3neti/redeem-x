<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Http\Controllers\FormFlowController;

/*
|--------------------------------------------------------------------------
| Form Flow Routes
|--------------------------------------------------------------------------
|
| Routes for managing multi-step form flows.
| Prefix: /form-flow (configurable via config)
|
*/

// Start a new flow
Route::post('/start', [FormFlowController::class, 'start'])
    ->name('form-flow.start');

// Get flow state
Route::get('/{flow_id}', [FormFlowController::class, 'show'])
    ->name('form-flow.show');

// Update step data
Route::post('/{flow_id}/step/{step}', [FormFlowController::class, 'updateStep'])
    ->name('form-flow.update-step');

// Complete flow
Route::post('/{flow_id}/complete', [FormFlowController::class, 'complete'])
    ->name('form-flow.complete');

// Cancel flow
Route::post('/{flow_id}/cancel', [FormFlowController::class, 'cancel'])
    ->name('form-flow.cancel');

// Clear flow state
Route::delete('/{flow_id}', [FormFlowController::class, 'destroy'])
    ->name('form-flow.destroy');
