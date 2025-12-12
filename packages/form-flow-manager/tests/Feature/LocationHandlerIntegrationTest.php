<?php

use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormHandlerLocation\LocationHandler;

/**
 * ============================================================================
 * Location Handler Integration Tests
 * ============================================================================
 * 
 * Tests integration of the real LocationHandler with form-flow-manager.
 * Tests mixed flows with both form handler and location handler.
 */

/**
 * Test 1: Real location handler is available and registered
 */
test('real location handler is registered and available', function () {
    $handler = app(LocationHandler::class);
    
    expect($handler)->toBeInstanceOf(\LBHurtado\FormFlowManager\Contracts\FormHandlerInterface::class);
    expect($handler->getName())->toBe('location');
    expect(config('form-flow.handlers.location'))->toBe(LocationHandler::class);
});

/**
 * Test 2: Mixed flow with form + location steps
 */
test('flow can combine form and location steps', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-mixed-' . uniqid(),
        'steps' => [
            // Step 1: Basic form
            [
                'handler' => 'form',
                'config' => [
                    'title' => 'Personal Info',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                    ],
                ],
            ],
            // Step 2: Location capture
            [
                'handler' => 'location',
                'config' => [
                    'require_address' => true,
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    $response->assertJsonStructure(['success', 'reference_id', 'flow_url']);
    
    // Verify flow was created with 2 steps
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($response->json('reference_id'));
    
    expect($state)->not->toBeNull();
    expect($state['instructions']['steps'])->toHaveCount(2);
    expect($state['instructions']['steps'][0]['handler'])->toBe('form');
    expect($state['instructions']['steps'][1]['handler'])->toBe('location');
});

/**
 * Test 3: Form step submits successfully before location step
 */
test('form step can be submitted before location step', function () {
    $referenceId = 'ref-form-then-location-' . uniqid();
    
    // Create flow
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
            [
                'handler' => 'location',
                'config' => [],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Get flow_id
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit step 0 (form)
    $submitResponse = $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'name' => 'Juan Dela Cruz',
        ],
    ]);
    
    $submitResponse->assertSuccessful();
    
    // Verify we moved to step 1 (location)
    $updatedState = $service->getFlowState($flowId);
    expect($updatedState['current_step'])->toBe(1);
    expect($updatedState['collected_data'][0]['name'])->toBe('Juan Dela Cruz');
});

/**
 * Test 4: Location step configuration is preserved
 */
test('location handler config is preserved in flow state', function () {
    $referenceId = 'ref-location-config-' . uniqid();
    
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            [
                'handler' => 'location',
                'config' => [
                    'require_address' => true,
                    'capture_snapshot' => false,
                    'map_provider' => 'mapbox',
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    
    // Verify config is stored
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    
    $locationConfig = $state['instructions']['steps'][0]['config'];
    expect($locationConfig['require_address'])->toBe(true);
    expect($locationConfig['capture_snapshot'])->toBe(false);
    expect($locationConfig['map_provider'])->toBe('mapbox');
});

/**
 * Test 5: Multiple location steps in single flow
 */
test('flow can have multiple location steps', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-multi-location-' . uniqid(),
        'steps' => [
            [
                'handler' => 'location',
                'config' => ['title' => 'Home Address'],
            ],
            [
                'handler' => 'location',
                'config' => ['title' => 'Work Address'],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($response->json('reference_id'));
    
    expect($state['instructions']['steps'])->toHaveCount(2);
    expect($state['instructions']['steps'][0]['handler'])->toBe('location');
    expect($state['instructions']['steps'][1]['handler'])->toBe('location');
});

/**
 * Test 6: Complex mixed flow (form → location → form → location)
 */
test('flow supports complex step sequences', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-complex-' . uniqid(),
        'steps' => [
            ['handler' => 'form', 'config' => ['fields' => [['name' => 'name', 'type' => 'text', 'required' => true]]]],
            ['handler' => 'location', 'config' => []],
            ['handler' => 'form', 'config' => ['fields' => [['name' => 'notes', 'type' => 'textarea', 'required' => false]]]],
            ['handler' => 'location', 'config' => []],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($response->json('reference_id'));
    
    expect($state['instructions']['steps'])->toHaveCount(4);
    expect($state['instructions']['steps'][0]['handler'])->toBe('form');
    expect($state['instructions']['steps'][1]['handler'])->toBe('location');
    expect($state['instructions']['steps'][2]['handler'])->toBe('form');
    expect($state['instructions']['steps'][3]['handler'])->toBe('location');
});

/**
 * ============================================================================
 * End-to-End Tests with Real Location Handler
 * ============================================================================
 */

/**
 * Test 7: Submit real location data through location handler
 */
test('real location handler accepts complete location data submission', function () {
    $referenceId = 'ref-location-submit-' . uniqid();
    
    // Start flow with location step
    $startResponse = $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            [
                'handler' => 'location',
                'config' => [
                    'require_address' => true,
                    'capture_snapshot' => true,
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $startResponse->assertSuccessful();
    $flowUrl = $startResponse->json('flow_url');
    
    // Extract flow_id from URL
    preg_match('/form-flow\/(.+)$/', $flowUrl, $matches);
    $flowId = $matches[1] ?? null;
    
    expect($flowId)->not->toBeNull();
    
    // Submit location data
    $submitResponse = $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'formatted_address' => 'Makati City, Metro Manila, Philippines',
            'address_components' => [
                'city' => 'Makati City',
                'region' => 'Metro Manila',
                'country' => 'Philippines',
            ],
            'accuracy' => 10.5,
        ],
    ]);
    
    $submitResponse->assertSuccessful();
    
    // Verify data was stored correctly
    $service = app(FormFlowService::class);
    $state = $service->getFlowState($flowId);
    
    expect($state['collected_data'])->toHaveCount(1);
    expect($state['collected_data'][0]['latitude'])->toBe(14.5995);
    expect($state['collected_data'][0]['longitude'])->toBe(120.9842);
    expect($state['collected_data'][0]['formatted_address'])->toBe('Makati City, Metro Manila, Philippines');
    expect($state['collected_data'][0])->toHaveKey('timestamp');
});

/**
 * Test 8: Location handler validates coordinates correctly
 */
test('real location handler rejects invalid coordinates', function () {
    $referenceId = 'ref-invalid-coords-' . uniqid();
    
    $startResponse = $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $flowUrl = $startResponse->json('flow_url');
    preg_match('/form-flow\/(.+)$/', $flowUrl, $matches);
    $flowId = $matches[1];
    
    // Submit invalid latitude (must be between -90 and 90)
    $submitResponse = $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 91.0,
            'longitude' => 120.9842,
        ],
    ]);
    
    $submitResponse->assertStatus(422);
    $submitResponse->assertJsonValidationErrors('latitude');
});

/**
 * Test 9: Mixed flow with real handlers - form then location
 */
test('real location handler works after form step in mixed flow', function () {
    $referenceId = 'ref-mixed-real-' . uniqid();
    
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                    ],
                ],
            ],
            [
                'handler' => 'location',
                'config' => [],
            ],
        ],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit form step
    $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
        ],
    ])->assertSuccessful();
    
    // Submit location step
    $this->postJson("/form-flow/{$flowId}/step/1", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'formatted_address' => 'Makati City',
        ],
    ])->assertSuccessful();
    
    // Verify both steps' data are stored
    $finalState = $service->getFlowState($flowId);
    expect($finalState['collected_data'])->toHaveCount(2);
    expect($finalState['collected_data'][0]['name'])->toBe('Juan Dela Cruz');
    expect($finalState['collected_data'][1]['latitude'])->toBe(14.5995);
});

/**
 * Test 10: Location with map snapshot (base64 image)
 */
test('real location handler accepts base64 map snapshot', function () {
    $referenceId = 'ref-snapshot-' . uniqid();
    
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [['handler' => 'location', 'config' => ['capture_snapshot' => true]]],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    $snapshotData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    
    $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'snapshot' => $snapshotData,
        ],
    ])->assertSuccessful();
    
    $finalState = $service->getFlowState($flowId);
    expect($finalState['collected_data'][0]['snapshot'])->toBe($snapshotData);
});

/**
 * Test 11: Multiple location captures in single flow
 */
test('flow can capture multiple locations with real handler', function () {
    $referenceId = 'ref-multi-location-' . uniqid();
    
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            ['handler' => 'location', 'config' => ['title' => 'Home Address']],
            ['handler' => 'location', 'config' => ['title' => 'Work Address']],
        ],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit home location
    $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'formatted_address' => 'Home - Makati City',
        ],
    ])->assertSuccessful();
    
    // Submit work location
    $this->postJson("/form-flow/{$flowId}/step/1", [
        'data' => [
            'latitude' => 14.6091,
            'longitude' => 121.0223,
            'formatted_address' => 'Work - Ortigas Center',
        ],
    ])->assertSuccessful();
    
    // Verify both locations stored
    $finalState = $service->getFlowState($flowId);
    expect($finalState['collected_data'])->toHaveCount(2);
    expect($finalState['collected_data'][0]['formatted_address'])->toBe('Home - Makati City');
    expect($finalState['collected_data'][1]['formatted_address'])->toBe('Work - Ortigas Center');
});

/**
 * Test 12: Location handler preserves structured address components
 */
test('real location handler preserves address components structure', function () {
    $referenceId = 'ref-address-components-' . uniqid();
    
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    $addressComponents = [
        'street' => 'Ayala Avenue',
        'barangay' => 'Poblacion',
        'city' => 'Makati City',
        'region' => 'Metro Manila',
        'country' => 'Philippines',
        'postal_code' => '1200',
    ];
    
    $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'formatted_address' => 'Ayala Avenue, Poblacion, Makati City',
            'address_components' => $addressComponents,
        ],
    ])->assertSuccessful();
    
    $finalState = $service->getFlowState($flowId);
    expect($finalState['collected_data'][0]['address_components'])->toBe($addressComponents);
    expect($finalState['collected_data'][0]['address_components']['postal_code'])->toBe('1200');
});

/**
 * Test 13: Location flow can be marked complete with real handler
 */
test('real location handler flow completion works correctly', function () {
    $referenceId = 'ref-complete-' . uniqid();
    
    $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'https://example.com/callback'],
    ]);
    
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit location data
    $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ],
    ])->assertSuccessful();
    
    // Mark as complete
    $completeResponse = $this->postJson("/form-flow/{$flowId}/complete");
    $completeResponse->assertSuccessful();
    
    // Verify status changed
    $finalState = $service->getFlowState($flowId);
    expect($finalState['status'])->toBe('completed');
});
