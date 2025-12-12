<?php

use LBHurtado\FormFlowManager\Services\FormFlowService;

/**
 * ============================================================================
 * Step 1: Test the existence of /form-flow/start endpoint
 * ============================================================================
 */
test('form-flow start endpoint exists', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-' . uniqid(),
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Should not be 404
    expect($response->status())->not->toBe(404);
});

/**
 * ============================================================================
 * Step 2: Test validation rules
 * ============================================================================
 */

test('form-flow start requires reference_id', function () {
    $response = test()->postJson('/form-flow/start', [
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reference_id']);
});

test('form-flow start requires steps array', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-123',
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['steps']);
});

test('form-flow start requires on_complete callback', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-123',
        'steps' => [
            ['handler' => 'location'],
        ],
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['callbacks.on_complete']);
});

test('form-flow start validates on_complete callback is a valid URL', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-123',
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'not-a-valid-url',
        ],
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['callbacks.on_complete']);
});

test('form-flow start requires handler for each step', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-123',
        'steps' => [
            ['config' => ['some' => 'value']],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['steps.0.handler']);
});

test('form-flow start accepts optional on_cancel callback', function () {
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => 'ref-123',
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
            'on_cancel' => 'https://example.com/cancel',
        ],
    ]);
    
    $response->assertSuccessful();
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference('ref-123');
    
    expect($state['instructions']['callbacks']['on_cancel'])
        ->toBe('https://example.com/cancel');
});

/**
 * ============================================================================
 * Step 3: Test response structure and generated URL
 * ============================================================================
 */

test('form-flow start returns generated URL for UI access', function () {
    $referenceId = 'ref-' . uniqid();
    
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'reference_id',
        'flow_url',  // The generated URL (like HyperVerge's startKycUrl)
    ]);
    
    expect($response->json('success'))->toBe(true);
    expect($response->json('reference_id'))->toBe($referenceId);
    expect($response->json('flow_url'))->toBeString();
    expect($response->json('flow_url'))->toContain('/form-flow/');
});

/**
 * ============================================================================
 * Step 4: Test the generated URL can be accessed independently
 * ============================================================================
 */

test('generated URL can be accessed in a separate session', function () {
    $referenceId = 'ref-' . uniqid();
    
    // Step 1: Create flow via POST (server-to-server, secure)
    $createResponse = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'title' => 'Test Flow',
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $createResponse->assertSuccessful();
    $generatedUrl = $createResponse->json('flow_url');
    
    // Step 2: Access the generated URL via GET (separate session)
    // This simulates: user opens link in browser
    $accessResponse = test()->get($generatedUrl);
    
    // Should render successfully (not 404)
    expect($accessResponse->status())->not->toBe(404);
    
    // Should render HTML page with Inertia (not JSON)
    expect($accessResponse->headers->get('content-type'))->toContain('text/html');
});

/**
 * ============================================================================
 * Step 5: Test results can be fetched by reference_id
 * ============================================================================
 */

test('results can be fetched using reference_id', function () {
    $referenceId = 'ref-' . uniqid();
    
    // Create flow
    $createResponse = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $createResponse->assertSuccessful();
    
    // Fetch by reference_id
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    
    expect($state)->not->toBeNull();
    expect($state['reference_id'])->toBe($referenceId);
    expect($state['status'])->toBe('active');
});

test('collected data can be retrieved by reference_id after completion', function () {
    $referenceId = 'ref-' . uniqid();
    
    // Create flow
    test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Simulate completing a step
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    $service->updateStepData($flowId, 0, [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'timestamp' => now()->toIso8601String(),
    ]);
    
    // Complete the flow
    $service->completeFlow($flowId);
    
    // Fetch results by reference_id
    $finalState = $service->getFlowStateByReference($referenceId);
    
    expect($finalState['status'])->toBe('completed');
    expect($finalState['collected_data'][0])->toHaveKeys(['latitude', 'longitude']);
    expect($finalState['collected_data'][0]['latitude'])->toBe(14.5995);
});

/**
 * ============================================================================
 * Step 6: Test reference_id must be unique
 * ============================================================================
 */

test('reference_id must be unique', function () {
    $referenceId = 'ref-duplicate-test';
    
    // Create first flow
    $firstResponse = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $firstResponse->assertSuccessful();
    
    // Try to create second flow with same reference_id
    $secondResponse = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Should fail validation
    $secondResponse->assertStatus(422);
    $secondResponse->assertJsonValidationErrors(['reference_id']);
});

/**
 * ============================================================================
 * Step 7: Test optional metadata storage
 * ============================================================================
 */

test('form-flow start accepts optional metadata linked to reference_id', function () {
    $referenceId = 'ref-' . uniqid();
    
    $response = test()->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location'],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
        'metadata' => [
            'voucher_code' => 'ABC123',
            'user_id' => 999,
            'source' => 'mobile_app',
        ],
    ]);
    
    $response->assertSuccessful();
    
    // Verify metadata is stored and retrievable by reference_id
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    
    expect($state['instructions']['metadata'])->toBe([
        'voucher_code' => 'ABC123',
        'user_id' => 999,
        'source' => 'mobile_app',
    ]);
});
