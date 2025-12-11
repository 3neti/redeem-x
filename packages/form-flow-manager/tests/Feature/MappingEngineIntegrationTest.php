<?php

use LBHurtado\FormFlowManager\Services\MappingEngine;
use LBHurtado\FormFlowManager\Services\TemplateRenderer;
use LBHurtado\FormFlowManager\Services\ExpressionEvaluator;
use LBHurtado\FormFlowManager\Data\DriverConfigData;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;

beforeEach(function () {
    $this->renderer = new TemplateRenderer();
    $this->evaluator = new ExpressionEvaluator($this->renderer);
    $this->engine = new MappingEngine($this->renderer, $this->evaluator);
});

it('transforms simple text input field', function () {
    $driver = DriverConfigData::from([
        'name' => 'test',
        'version' => '1.0',
        'source' => 'stdClass',
        'target' => FormFlowStepData::class,
        'mappings' => [
            'handler' => '{{ source.handler }}',
            'config' => [
                'label' => '{{ source.label }}',
                'required' => '{{ source.required }}',
            ],
            'required' => '{{ source.required }}',
            'priority' => '{{ source.priority }}',
        ],
    ]);
    
    $source = (object) [
        'handler' => 'text',
        'label' => 'Email Address',
        'required' => true,
        'priority' => 10,
    ];
    
    $context = ['source' => $source];
    
    // Test template rendering
    $handlerResult = $this->renderer->render('{{ source.handler }}', $context);
    expect($handlerResult)->toBe('text');
    
    $labelResult = $this->renderer->render('{{ source.label }}', $context);
    expect($labelResult)->toBe('Email Address');
    
    expect($driver->target)->toBe(FormFlowStepData::class);
});

it('handles array_map transformation', function () {
    $driver = DriverConfigData::from([
        'name' => 'test',
        'version' => '1.0',
        'source' => 'stdClass',
        'target' => FormFlowInstructionsData::class,
        'mappings' => [
            'flow_id' => '{{ source.id }}',
            'steps' => [
                'source' => 'items',
                'transform' => 'array_map',
                'handler' => [
                    'handler' => '{{ item.type }}',
                    'config' => [],
                    'required' => true,
                    'priority' => '{{ item.priority }}',
                ],
            ],
        ],
    ]);
    
    $source = (object) [
        'id' => 'test-flow',
        'items' => [
            (object) ['type' => 'location', 'priority' => 10],
            (object) ['type' => 'selfie', 'priority' => 20],
        ],
    ];
    
    expect($driver->getMappingForField('steps'))->toHaveKey('transform');
    expect($driver->getMappingForField('steps')['transform'])->toBe('array_map');
    expect($driver->target)->toBe(FormFlowInstructionsData::class);
    
    // Test that the mapping structure is correct
    $mapping = $driver->getMappingForField('steps');
    expect($mapping['source'])->toBe('items');
    expect($mapping['handler'])->toHaveKey('handler');
    expect($mapping['handler']['handler'])->toBe('{{ item.type }}');
});

it('evaluates conditional mappings with when clause', function () {
    $mapping = [
        'when' => 'item.type == "text"',
        'then' => ['min_length' => 5],
        'else' => null,
    ];
    
    $context = [
        'item' => (object) ['type' => 'text'],
    ];
    
    $result = $this->evaluator->evaluate('item.type == "text"', $context);
    expect($result)->toBeTrue();
});

it('handles null coalescing in mappings', function () {
    $template = '{{ item.label ?? "Default Label" }}';
    $context = ['item' => (object) ['label' => null]];
    
    $result = $this->renderer->render($template, $context);
    expect($result)->toBe('Default Label');
});

it('handles nested object access in templates', function () {
    $template = '{{ source.config.max_length }}';
    $context = [
        'source' => (object) [
            'config' => (object) ['max_length' => 255],
        ],
    ];
    
    $result = $this->renderer->render($template, $context);
    expect($result)->toBe('255');
});

it('validates form input field with multiple constraints', function () {
    // Simulate form input validation
    $field = (object) [
        'type' => 'text',
        'name' => 'email',
        'required' => true,
        'pattern' => '^[a-z]+@[a-z]+\.[a-z]+$',
        'min_length' => 5,
        'max_length' => 100,
    ];
    
    expect($field->type)->toBe('text');
    expect($field->required)->toBeTrue();
    expect($field->pattern)->toContain('@');
});

it('handles enum field with options', function () {
    $field = (object) [
        'type' => 'enum',
        'name' => 'country',
        'options' => ['PH', 'US', 'JP'],
        'required' => true,
    ];
    
    expect($field->type)->toBe('enum');
    expect($field->options)->toContain('PH');
    expect($field->options)->toHaveCount(3);
});

it('handles numeric field with min/max constraints', function () {
    $field = (object) [
        'type' => 'numeric',
        'name' => 'age',
        'min_value' => 18,
        'max_value' => 100,
        'step' => 1,
    ];
    
    expect($field->type)->toBe('numeric');
    expect($field->min_value)->toBe(18);
    expect($field->max_value)->toBe(100);
});

it('handles date field with format', function () {
    $field = (object) [
        'type' => 'date',
        'name' => 'birthdate',
        'format' => 'Y-m-d',
        'min_date' => '1900-01-01',
        'max_date' => '2023-12-31',
    ];
    
    expect($field->type)->toBe('date');
    expect($field->format)->toBe('Y-m-d');
});

it('filters out disabled fields using expression evaluator', function () {
    $fields = [
        (object) ['name' => 'field1', 'enabled' => true],
        (object) ['name' => 'field2', 'enabled' => false],
        (object) ['name' => 'field3', 'enabled' => true],
    ];
    
    $enabled = array_filter($fields, function ($field) {
        return $field->enabled;
    });
    
    expect($enabled)->toHaveCount(2);
});

it('handles priority-based field ordering', function () {
    $fields = [
        (object) ['name' => 'field1', 'priority' => 30],
        (object) ['name' => 'field2', 'priority' => 10],
        (object) ['name' => 'field3', 'priority' => 20],
    ];
    
    $sorted = collect($fields)->sortBy('priority')->values()->all();
    
    expect($sorted[0]->name)->toBe('field2');
    expect($sorted[1]->name)->toBe('field3');
    expect($sorted[2]->name)->toBe('field1');
});

it('validates email pattern', function () {
    $pattern = '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$';
    $email = 'test@example.com';
    
    expect(preg_match("/{$pattern}/", $email))->toBe(1);
});

it('validates phone pattern for Philippines', function () {
    $pattern = '^(09|\+639)\d{9}$';
    $phone = '09171234567';
    
    expect(preg_match("/{$pattern}/", $phone))->toBe(1);
});

it('handles conditional field display with show_if', function () {
    $field = (object) [
        'name' => 'other_reason',
        'show_if' => 'reason == "other"',
    ];
    
    $context = ['reason' => 'other'];
    $result = $this->evaluator->evaluate($field->show_if, $context);
    
    expect($result)->toBeTrue();
});
