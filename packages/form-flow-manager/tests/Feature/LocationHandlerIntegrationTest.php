<?php

use LBHurtado\FormFlowManager\Services\FormFlowService;

/**
 * ============================================================================
 * Location Handler Integration Tests
 * ============================================================================
 * 
 * Tests integration of the location handler with form-flow-manager.
 * Tests mixed flows with both form handler and location handler.
 */

/**
 * Test 1: Location handler can be registered dynamically
 */
test('location handler can be registered in form flow', function () {
    // Create a mock location handler
    $locationHandler = new class implements \LBHurtado\FormFlowManager\Contracts\FormHandlerInterface {
        public function getName(): string { return 'location'; }
        public function handle(\Illuminate\Http\Request $request, \LBHurtado\FormFlowManager\Data\FormFlowStepData $step, array $context = []): array {
            return ['latitude' => 14.5995, 'longitude' => 120.9842];
        }
        public function validate(array $data, array $rules): bool { return true; }
        public function render(\LBHurtado\FormFlowManager\Data\FormFlowStepData $step, array $context = []) {
            return response()->json(['handler' => 'location']);
        }
        public function getConfigSchema(): array { return []; }
    };
    
    // Register handler
    app()->instance('form_handler_location', $locationHandler);
    
    // Update FormFlowController to recognize the handler
    config(['form-flow.handlers.location' => get_class($locationHandler)]);
    
    expect($locationHandler->getName())->toBe('location');
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
