<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

/*
|--------------------------------------------------------------------------
| Location Handler Test Routes
|--------------------------------------------------------------------------
|
| Routes for testing the location handler as a standalone service.
| Simulates the microservice architecture where:
| 1. Main app sends instructions + callback URL
| 2. Form flow service handles the wizard
| 3. Callback triggered when complete
|
*/

/**
 * Test Route: Start location capture flow
 * 
 * Simulates main app (redeem-x) sending instructions to form-flow service
 */
Route::get('/test/location/start', function (FormFlowService $flowService) {
    // Simulate what redeem-x would send to the form-flow microservice
    $instructions = FormFlowInstructionsData::from([
        'flow_id' => 'test-location-' . uniqid(),
        'title' => 'Location Capture Test',
        'description' => 'Testing standalone location handler',
        'steps' => [
            [
                'handler' => 'location',
                'config' => [
                    'opencage_api_key' => config('location-handler.opencage_api_key'),
                    'map_provider' => config('location-handler.map_provider'),
                    'mapbox_token' => config('location-handler.mapbox_token'),
                    'capture_snapshot' => true,
                    'require_address' => false,
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => url('/test/location/callback'),
            'on_cancel' => url('/test/location/cancelled'),
        ],
    ]);
    
    // Start the flow
    $state = $flowService->startFlow($instructions);
    
    // Redirect to the flow (browser follows)
    return redirect()->route('form-flow.show', ['flow_id' => $state['flow_id']]);
})->name('test.location.start');

/**
 * Callback Route: Handle completion
 * 
 * This is where main app (redeem-x) would receive the collected data
 */
Route::post('/test/location/callback', function () {
    $data = request()->all();
    
    return response()->json([
        'success' => true,
        'message' => 'Location data received by main app!',
        'received_data' => $data,
        'note' => 'In production, redeem-x would process this data (save to DB, trigger redemption, etc.)',
    ]);
})->name('test.location.callback');

/**
 * Callback Route: Handle cancellation
 */
Route::post('/test/location/cancelled', function () {
    $data = request()->all();
    
    return response()->json([
        'success' => true,
        'message' => 'Flow cancelled, main app notified',
        'received_data' => $data,
    ]);
})->name('test.location.cancelled');

/**
 * Demo Route: Show instructions
 */
Route::get('/test/location', function () {
    $instructions = [
        'Test Flow' => url('/test/location/start'),
        'How It Works' => [
            '1. Main app (redeem-x) sends JSON instructions to form-flow service',
            '2. Form-flow service creates wizard from instructions',
            '3. User completes location capture',
            '4. Form-flow service POSTs collected data to callback URL',
            '5. Main app receives data and processes it',
        ],
        'Microservice Architecture' => [
            'Form-Flow Service: Handles all UI/wizard logic',
            'Main App (redeem-x): Sends instructions, receives results',
            'Benefits: Separation of concerns, reusable, scalable',
        ],
    ];
    
    return response()->json($instructions);
})->name('test.location.index');
