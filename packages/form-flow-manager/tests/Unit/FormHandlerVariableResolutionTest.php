<?php

use LBHurtado\FormFlowManager\Handlers\FormHandler;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

/**
 * ============================================================================
 * Unit Tests for Variable Resolution in FormHandler
 * ============================================================================
 * 
 * Tests the resolveVariables() method for:
 * - Simple variable substitution
 * - Nested variable references
 * - Mixed literal and variable values
 * - Type preservation (string, int, float, bool)
 * - Collected data auto-population (Phase 2)
 */

beforeEach(function () {
    $this->handler = new FormHandler();
    
    // Create reflection method to access protected resolveVariables()
    $reflection = new \ReflectionClass($this->handler);
    $this->resolveVariables = $reflection->getMethod('resolveVariables');
    $this->resolveVariables->setAccessible(true);
});

/**
 * Test 1: Simple variable resolution
 */
test('resolves simple variable references', function () {
    $config = [
        'variables' => [
            '$country' => 'PH',
            '$amount' => 100,
        ],
        'fields' => [
            ['name' => 'country', 'default' => '$country'],
            ['name' => 'amount', 'default' => '$amount'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
    expect($resolved['fields'][1]['default'])->toBe(100);
    expect($resolved)->not->toHaveKey('variables'); // Variables block should be removed
});

/**
 * Test 2: Nested variable references
 */
test('resolves nested variable references', function () {
    $config = [
        'variables' => [
            '$finalValue' => 'PH',
            '$intermediate' => '$finalValue',
            '$country' => '$intermediate',
        ],
        'fields' => [
            ['name' => 'country', 'default' => '$country'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
});

/**
 * Test 3: Multiple levels of nested references
 */
test('resolves multiple levels of nested references', function () {
    $config = [
        'variables' => [
            '$level4' => 'final',
            '$level3' => '$level4',
            '$level2' => '$level3',
            '$level1' => '$level2',
        ],
        'fields' => [
            ['name' => 'test', 'default' => '$level1'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('final');
});

/**
 * Test 4: Mixed literal and variable values
 */
test('handles mixed literal and variable values', function () {
    $config = [
        'variables' => [
            '$country' => 'PH',
        ],
        'fields' => [
            ['name' => 'country', 'default' => '$country'],
            ['name' => 'amount', 'default' => 100],  // Literal value
            ['name' => 'name', 'default' => 'John'], // Literal string
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
    expect($resolved['fields'][1]['default'])->toBe(100);
    expect($resolved['fields'][2]['default'])->toBe('John');
});

/**
 * Test 5: Type preservation for numeric values
 */
test('preserves type for numeric values', function () {
    $config = [
        'variables' => [
            '$intValue' => 100,
            '$floatValue' => 99.99,
            '$stringValue' => '100',
        ],
        'fields' => [
            ['name' => 'int', 'default' => '$intValue'],
            ['name' => 'float', 'default' => '$floatValue'],
            ['name' => 'string', 'default' => '$stringValue'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe(100);
    expect($resolved['fields'][0]['default'])->toBeInt();
    expect($resolved['fields'][1]['default'])->toBe(99.99);
    expect($resolved['fields'][1]['default'])->toBeFloat();
    expect($resolved['fields'][2]['default'])->toBe('100');
    expect($resolved['fields'][2]['default'])->toBeString();
});

/**
 * Test 6: Type preservation for boolean values
 */
test('preserves type for boolean values', function () {
    $config = [
        'variables' => [
            '$trueValue' => true,
            '$falseValue' => false,
        ],
        'fields' => [
            ['name' => 'checkbox1', 'default' => '$trueValue'],
            ['name' => 'checkbox2', 'default' => '$falseValue'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBeTrue();
    expect($resolved['fields'][1]['default'])->toBeFalse();
});

/**
 * Test 7: Resolves variables in multiple field properties
 */
test('resolves variables in min, max, step properties', function () {
    $config = [
        'variables' => [
            '$minAmount' => 50,
            '$maxAmount' => 50000,
            '$step' => 10,
        ],
        'fields' => [
            [
                'name' => 'amount',
                'min' => '$minAmount',
                'max' => '$maxAmount',
                'step' => '$step',
            ],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['min'])->toBe(50);
    expect($resolved['fields'][0]['max'])->toBe(50000);
    expect($resolved['fields'][0]['step'])->toBe(10);
});

/**
 * Test 8: Handles unresolved variables gracefully
 */
test('leaves unresolved variables as-is', function () {
    $config = [
        'variables' => [
            '$defined' => 'value',
        ],
        'fields' => [
            ['name' => 'field1', 'default' => '$defined'],
            ['name' => 'field2', 'default' => '$undefined'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('value');
    expect($resolved['fields'][1]['default'])->toBe('$undefined'); // Unchanged
});

/**
 * Test 9: Handles empty variables block
 */
test('handles empty variables block', function () {
    $config = [
        'variables' => [],
        'fields' => [
            ['name' => 'country', 'default' => 'PH'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
});

/**
 * Test 10: Handles missing variables block
 */
test('handles missing variables block', function () {
    $config = [
        'fields' => [
            ['name' => 'country', 'default' => 'PH'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
});

/**
 * Test 11: Phase 2 - Auto-populate variables from collected data
 */
test('auto-populates variables from collected data', function () {
    $config = [
        'fields' => [
            ['name' => 'country', 'default' => '$step0_selected_country'],
            ['name' => 'amount', 'default' => '$step0_entered_amount'],
        ],
    ];
    
    $collectedData = [
        0 => [
            'selected_country' => 'PH',
            'entered_amount' => 500,
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config, $collectedData);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
    expect($resolved['fields'][1]['default'])->toBe(500);
});

/**
 * Test 12: Phase 2 - Multiple steps in collected data
 */
test('auto-populates variables from multiple previous steps', function () {
    $config = [
        'fields' => [
            ['name' => 'name', 'default' => '$step0_name'],
            ['name' => 'email', 'default' => '$step1_email'],
        ],
    ];
    
    $collectedData = [
        0 => ['name' => 'Juan'],
        1 => ['email' => 'juan@example.com'],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config, $collectedData);
    
    expect($resolved['fields'][0]['default'])->toBe('Juan');
    expect($resolved['fields'][1]['default'])->toBe('juan@example.com');
});

/**
 * Test 13: Phase 2 - Combined explicit variables and collected data
 */
test('combines explicit variables with collected data', function () {
    $config = [
        'variables' => [
            '$defaultCountry' => 'PH',
            '$inheritedAmount' => '$step0_amount', // References collected data
        ],
        'fields' => [
            ['name' => 'country', 'default' => '$defaultCountry'],
            ['name' => 'amount', 'default' => '$inheritedAmount'],
        ],
    ];
    
    $collectedData = [
        0 => ['amount' => 1000],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config, $collectedData);
    
    expect($resolved['fields'][0]['default'])->toBe('PH');
    expect($resolved['fields'][1]['default'])->toBe(1000);
});

/**
 * Test 14: Resolves placeholder variable references
 */
test('resolves placeholder variable references', function () {
    $config = [
        'variables' => [
            '$placeholderText' => 'Enter your amount',
        ],
        'fields' => [
            ['name' => 'amount', 'placeholder' => '$placeholderText'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['placeholder'])->toBe('Enter your amount');
});

/**
 * Test 15: Handles circular reference prevention (max depth)
 */
test('prevents infinite loops with circular references', function () {
    $config = [
        'variables' => [
            '$var1' => '$var2',
            '$var2' => '$var1', // Circular reference
        ],
        'fields' => [
            ['name' => 'test', 'default' => '$var1'],
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    // Should stop after max depth (10) and leave as reference
    expect($resolved['fields'][0]['default'])->toBeString();
    expect($resolved['fields'][0]['default'])->toStartWith('$');
});

/**
 * Test 16: Non-string variable names are ignored
 */
test('only resolves string values starting with dollar sign', function () {
    $config = [
        'variables' => [
            '$country' => 'PH',
        ],
        'fields' => [
            ['name' => 'amount', 'default' => 100], // Number, not a variable
            ['name' => 'country', 'default' => '$country'], // Variable
            ['name' => 'active', 'default' => true], // Boolean, not a variable
        ],
    ];
    
    $resolved = $this->resolveVariables->invoke($this->handler, $config);
    
    expect($resolved['fields'][0]['default'])->toBe(100);
    expect($resolved['fields'][1]['default'])->toBe('PH');
    expect($resolved['fields'][2]['default'])->toBeTrue();
});
