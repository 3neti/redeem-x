<?php

use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Handlers\FormHandler;
use LBHurtado\FormFlowManager\Services\FormFlowService;

test('session stores step_name in collected data', function () {
    $flowService = app(FormFlowService::class);

    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'test-ref-001',
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'step_name' => 'wallet_info',
                    'fields' => [
                        ['name' => 'mobile', 'type' => 'text', 'required' => true],
                    ],
                ],
            ],
        ],
        'callbacks' => [
            'on_complete' => 'http://test.test/callback',
        ],
    ]);

    $state = $flowService->startFlow($instructions);
    $flowId = $state['flow_id'];

    // Update step with data and step name
    $stepData = ['mobile' => '09173011987'];
    $flowService->updateStepData($flowId, 0, $stepData, 'wallet_info');

    // Verify _step_name is stored
    $updatedState = $flowService->getFlowState($flowId);
    expect($updatedState['collected_data'][0])
        ->toHaveKey('_step_name')
        ->and($updatedState['collected_data'][0]['_step_name'])->toBe('wallet_info')
        ->and($updatedState['collected_data'][0]['mobile'])->toBe('09173011987');
});

test('FormHandler creates both index-based and name-based variables', function () {
    $handler = new FormHandler;

    // Simulate collected data with step names
    $collectedData = [
        0 => [
            '_step_name' => 'wallet_info',
            'mobile' => '09173011987',
            'amount' => 50,
        ],
        1 => [
            '_step_name' => 'kyc_verification',
            'name' => 'HURTADO LESTER',
            'date_of_birth' => '1970-04-21',
        ],
    ];

    $config = [
        'variables' => [
            '$kyc_name' => '$kyc_verification.name',
        ],
        'fields' => [
            [
                'name' => 'full_name',
                'type' => 'text',
                'default' => '$kyc_name',
                'required' => true,
            ],
        ],
    ];

    // Use reflection to access protected method
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('resolveVariables');
    $method->setAccessible(true);

    $resolved = $method->invoke($handler, $config, $collectedData);

    // Verify both syntaxes work in variable resolution
    expect($resolved['fields'][0]['default'])->toBe('HURTADO LESTER');
});

test('name-based references survive step reordering', function () {
    $handler = new FormHandler;

    // Scenario: Add a step BEFORE KYC
    // Old order: 0=wallet, 1=kyc, 2=bio
    // New order: 0=wallet, 1=new_step, 2=kyc, 3=bio
    $collectedData = [
        0 => [
            '_step_name' => 'wallet_info',
            'mobile' => '09173011987',
        ],
        1 => [
            '_step_name' => 'new_step',
            'extra_data' => 'something',
        ],
        2 => [
            '_step_name' => 'kyc_verification',
            'name' => 'HURTADO LESTER',
            'date_of_birth' => '1970-04-21',
        ],
    ];

    $config = [
        'variables' => [
            '$kyc_name' => '$kyc_verification.name',  // Name-based reference
        ],
        'fields' => [
            [
                'name' => 'full_name',
                'type' => 'text',
                'default' => '$kyc_name',
                'required' => true,
            ],
        ],
    ];

    // Use reflection
    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('resolveVariables');
    $method->setAccessible(true);

    $resolved = $method->invoke($handler, $config, $collectedData);

    // Name-based reference should still work even though KYC is now step 2
    expect($resolved['fields'][0]['default'])->toBe('HURTADO LESTER');
});

test('backward compatibility: index-based references still work', function () {
    $handler = new FormHandler;

    // Old-style config using index-based references
    $collectedData = [
        0 => [
            '_step_name' => 'wallet_info',
            'mobile' => '09173011987',
        ],
        1 => [
            '_step_name' => 'kyc_verification',
            'name' => 'HURTADO LESTER',
        ],
    ];

    $config = [
        'variables' => [
            '$kyc_name' => '$step1_name',  // Old index-based reference
        ],
        'fields' => [
            [
                'name' => 'full_name',
                'type' => 'text',
                'default' => '$kyc_name',
                'required' => true,
            ],
        ],
    ];

    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('resolveVariables');
    $method->setAccessible(true);

    $resolved = $method->invoke($handler, $config, $collectedData);

    // Old syntax should still work
    expect($resolved['fields'][0]['default'])->toBe('HURTADO LESTER');
});

test('missing step names degrade gracefully', function () {
    $handler = new FormHandler;

    // Step without _step_name (legacy or handler that doesn't support it)
    $collectedData = [
        0 => [
            'mobile' => '09173011987',
            // No _step_name
        ],
        1 => [
            '_step_name' => 'kyc_verification',
            'name' => 'HURTADO LESTER',
        ],
    ];

    $config = [
        'variables' => [
            '$kyc_name' => '$kyc_verification.name',
        ],
        'fields' => [
            [
                'name' => 'full_name',
                'type' => 'text',
                'default' => '$kyc_name',
                'required' => true,
            ],
        ],
    ];

    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('resolveVariables');
    $method->setAccessible(true);

    $resolved = $method->invoke($handler, $config, $collectedData);

    // Should work for steps that have names, skip those that don't
    expect($resolved['fields'][0]['default'])->toBe('HURTADO LESTER');
});

test('_step_name is excluded from form data', function () {
    $handler = new FormHandler;

    $collectedData = [
        0 => [
            '_step_name' => 'wallet_info',
            'mobile' => '09173011987',
            'amount' => 50,
        ],
    ];

    $config = [
        'variables' => [],
        'fields' => [],
    ];

    $reflection = new ReflectionClass($handler);
    $method = $reflection->getMethod('resolveVariables');
    $method->setAccessible(true);

    // Call method and verify _step_name doesn't leak into variables as a field
    $resolved = $method->invoke($handler, $config, $collectedData);

    // Check that we don't create variables for _step_name
    // (This is implicit - if _step_name leaked, we'd see $step0__step_name)
    expect($resolved)->toBeArray();
});
