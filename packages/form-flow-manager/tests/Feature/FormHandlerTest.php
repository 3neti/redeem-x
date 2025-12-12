<?php

use LBHurtado\FormFlowManager\Services\FormFlowService;

/**
 * ============================================================================
 * Test Built-in Form Handler for Basic Inputs
 * ============================================================================
 * 
 * When no specialized plugin exists (location, selfie, signature, kyc),
 * the form-flow-manager should use its built-in FormHandler to collect
 * basic inputs like name, address, birthdate, etc.
 */

/**
 * Test 1: Form handler accepts basic text inputs
 */
test('form handler accepts basic text inputs', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-basic-' . uniqid(),
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                        ['name' => 'address', 'type' => 'textarea', 'label' => 'Address', 'required' => true],
                    ],
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    $response->assertJson(['success' => true]);
});

/**
 * Test 2: Form handler accepts various input types
 */
test('form handler supports multiple input types', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-types-' . uniqid(),
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                        ['name' => 'birthdate', 'type' => 'date', 'required' => true],
                        ['name' => 'gmi', 'type' => 'number', 'label' => 'Gross Monthly Income', 'required' => false],
                        ['name' => 'gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Other'], 'required' => true],
                        ['name' => 'terms', 'type' => 'checkbox', 'label' => 'I agree to terms', 'required' => true],
                    ],
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
});

/**
 * Test 3: Mixed handlers - form + location plugin
 */
test('flow can mix form handler with specialized handlers', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-mixed-' . uniqid(),
        'steps' => [
            // Step 1: Basic form inputs
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'address', 'type' => 'textarea', 'required' => true],
                    ],
                ],
            ],
            // Step 2: Location plugin (if exists)
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
});

/**
 * Test 4: Form handler renders a generic form page
 */
test('form handler renders generic form UI', function () {
    $referenceId = 'ref-render-' . uniqid();
    
    // Create flow with form handler
    $createResponse = $this->postJson('/form-flow/start', [
        'reference_id' => $referenceId,
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'title' => 'Personal Information',
                    'description' => 'Please provide your details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                    ],
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $createResponse->assertSuccessful();
    $flowUrl = $createResponse->json('flow_url');
    
    // Access the flow URL
    $accessResponse = $this->get($flowUrl);
    
    // Should render successfully
    expect($accessResponse->status())->not->toBe(404);
    expect($accessResponse->headers->get('content-type'))->toContain('text/html');
});

/**
 * Test 5: Form handler accepts and stores submitted data
 */
test('form handler accepts form submission', function () {
    $referenceId = 'ref-submit-' . uniqid();
    
    // Create flow
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
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Get flow_id from reference
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit form data
    $submitResponse = $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
        ],
    ]);
    
    $submitResponse->assertSuccessful();
    
    // Verify data is stored
    $finalState = $service->getFlowStateByReference($referenceId);
    expect($finalState['collected_data'][0])->toHaveKeys(['name', 'email']);
    expect($finalState['collected_data'][0]['name'])->toBe('Juan Dela Cruz');
});

/**
 * Test 6: Form handler validates required fields
 */
test('form handler validates required fields', function () {
    $referenceId = 'ref-validate-' . uniqid();
    
    // Create flow
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
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    // Get flow_id
    $service = app(FormFlowService::class);
    $state = $service->getFlowStateByReference($referenceId);
    $flowId = $state['flow_id'];
    
    // Submit incomplete data
    $submitResponse = $this->postJson("/form-flow/{$flowId}/step/0", [
        'data' => [
            'name' => 'Juan Dela Cruz',
            // Missing required 'email' field
        ],
    ]);
    
    // Should fail validation
    $submitResponse->assertStatus(422);
    $submitResponse->assertJsonValidationErrors(['data.email']);
});

/**
 * Test 7: Fallback for missing plugin - selfie as file upload
 */
test('fallback to form handler when plugin does not exist', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-fallback-' . uniqid(),
        'steps' => [
            // Step 1: Name and address (basic form)
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
            // Step 2: Selfie plugin doesn't exist → should fall back to file upload
            [
                'handler' => 'selfie',
                'config' => [],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
    
    // When accessing the flow, if selfie handler doesn't exist,
    // it should render a generic form with file upload instruction
    // This will be handled by FormFlowController's handler resolution
});

/**
 * Test 8: Form handler with validation rules
 */
test('form handler supports validation rules', function () {
    $response = $this->postJson('/form-flow/start', [
        'reference_id' => 'ref-rules-' . uniqid(),
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'fields' => [
                        [
                            'name' => 'email',
                            'type' => 'email',
                            'required' => true,
                            'validation' => ['email', 'max:255'],
                        ],
                        [
                            'name' => 'age',
                            'type' => 'number',
                            'required' => true,
                            'validation' => ['integer', 'min:18', 'max:120'],
                        ],
                    ],
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'https://example.com/callback',
        ],
    ]);
    
    $response->assertSuccessful();
});
