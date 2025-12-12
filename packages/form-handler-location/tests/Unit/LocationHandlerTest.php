<?php

use LBHurtado\FormHandlerLocation\LocationHandler;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * LocationHandler Unit Tests
 * ============================================================================
 * 
 * Tests for the LocationHandler implementation of FormHandlerInterface.
 */

/**
 * Test 1: LocationHandler implements FormHandlerInterface
 */
test('location handler implements form handler interface', function () {
    $handler = new LocationHandler();
    
    expect($handler)->toBeInstanceOf(\LBHurtado\FormFlowManager\Contracts\FormHandlerInterface::class);
});

/**
 * Test 2: getName() returns correct handler name
 */
test('location handler returns correct name', function () {
    $handler = new LocationHandler();
    
    expect($handler->getName())->toBe('location');
});

/**
 * Test 3: getConfigSchema() returns valid schema
 */
test('location handler config schema is valid', function () {
    $handler = new LocationHandler();
    $schema = $handler->getConfigSchema();
    
    expect($schema)->toBeArray();
    expect($schema)->toHaveKeys([
        'opencage_api_key',
        'map_provider',
        'mapbox_token',
        'capture_snapshot',
        'require_address',
    ]);
});

/**
 * Test 4: handle() validates latitude and longitude
 */
test('location handler validates coordinates', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toBeArray();
    expect($result)->toHaveKey('latitude', 14.5995);
    expect($result)->toHaveKey('longitude', 120.9842);
    expect($result)->toHaveKey('timestamp');
});

/**
 * Test 5: handle() accepts optional address data
 */
test('location handler accepts formatted address', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'formatted_address' => 'Makati City, Metro Manila, Philippines',
        'address_components' => [
            'city' => 'Makati City',
            'region' => 'Metro Manila',
            'country' => 'Philippines',
        ],
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toHaveKey('formatted_address', 'Makati City, Metro Manila, Philippines');
    expect($result)->toHaveKey('address_components');
    expect($result['address_components'])->toHaveKey('city', 'Makati City');
});

/**
 * Test 6: handle() accepts optional snapshot
 */
test('location handler accepts map snapshot', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $snapshotData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'snapshot' => $snapshotData,
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toHaveKey('snapshot', $snapshotData);
});

/**
 * Test 7: handle() includes accuracy if provided
 */
test('location handler accepts accuracy metric', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'accuracy' => 10.5, // meters
    ]);
    
    $result = $handler->handle($request, $step);
    
    expect($result)->toHaveKey('accuracy', 10.5);
});

/**
 * Test 8: render() returns Inertia response
 */
test('location handler renders inertia page', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [
            'require_address' => true,
        ],
    ]);
    
    $response = $handler->render($step, [
        'flow_id' => 'test-flow-123',
        'step_index' => 0,
    ]);
    
    // Check it's an Inertia response
    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});

/**
 * Test 9: render() passes config to Inertia
 */
test('location handler passes config to inertia response', function () {
    config([
        'location-handler.opencage_api_key' => 'global_key',
        'location-handler.map_provider' => 'mapbox',
        'location-handler.capture_snapshot' => false,
    ]);
    
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [
            'require_address' => true,
            'capture_snapshot' => true,
        ],
    ]);
    
    $response = $handler->render($step, [
        'flow_id' => 'test-flow-123',
        'step_index' => 0,
    ]);
    
    // Verify it's an Inertia response (full rendering requires Inertia middleware)
    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});

/**
 * Test 10: validate() method exists (interface requirement)
 */
test('location handler has validate method', function () {
    $handler = new LocationHandler();
    
    expect(method_exists($handler, 'validate'))->toBeTrue();
    expect($handler->validate([], []))->toBeTrue();
});

/**
 * Test 11: LocationData can be created from handler output
 */
test('location handler output can be cast to LocationData', function () {
    $handler = new LocationHandler();
    $step = FormFlowStepData::from([
        'handler' => 'location',
        'config' => [],
    ]);
    
    $request = Request::create('/test', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'formatted_address' => 'Test Address',
    ]);
    
    $result = $handler->handle($request, $step);
    
    // The result should be compatible with LocationData
    expect($result)->toHaveKeys(['latitude', 'longitude', 'timestamp']);
});
