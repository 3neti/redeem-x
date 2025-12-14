<?php

use LBHurtado\FormFlowManager\Services\FormFlowService;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new FormFlowService();
});

it('starts a new flow', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-flow-123',
        'flow_id' => 'test-flow-123',
        'steps' => [
            ['handler' => 'location', 'config' => [], 'priority' => 10],
            ['handler' => 'selfie', 'config' => [], 'priority' => 20],
        ],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $state = $this->service->startFlow($instructions);
    
    expect($state)->toHaveKey('flow_id');
    expect($state['flow_id'])->toBe('test-flow-123');
    expect($state['status'])->toBe('active');
    expect($state['current_step'])->toBe(0);
    expect($state['completed_steps'])->toBeArray()->toBeEmpty();
});

it('retrieves flow state', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-retrieve',
        'flow_id' => 'test-retrieve',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $this->service->startFlow($instructions);
    $state = $this->service->getFlowState('test-retrieve');
    
    expect($state)->not->toBeNull();
    expect($state['flow_id'])->toBe('test-retrieve');
});

it('updates step data', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-update',
        'flow_id' => 'test-update',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $this->service->startFlow($instructions);
    $state = $this->service->updateStepData('test-update', 0, ['lat' => 14.5995, 'lng' => 120.9842]);
    
    expect($state['collected_data'][0])->toHaveKey('lat');
    expect($state['collected_data'][0]['lat'])->toBe(14.5995);
    expect($state['completed_steps'])->toContain(0);
    expect($state['current_step'])->toBe(1);
});

it('completes a flow', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-complete',
        'flow_id' => 'test-complete',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $this->service->startFlow($instructions);
    $state = $this->service->completeFlow('test-complete');
    
    expect($state['status'])->toBe('completed');
    expect($state)->toHaveKey('completed_at');
});

it('cancels a flow', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-cancel',
        'flow_id' => 'test-cancel',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $this->service->startFlow($instructions);
    $state = $this->service->cancelFlow('test-cancel');
    
    expect($state['status'])->toBe('cancelled');
    expect($state)->toHaveKey('cancelled_at');
});

it('checks if flow exists', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-exists',
        'flow_id' => 'test-exists',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    expect($this->service->flowExists('test-exists'))->toBeFalse();
    $this->service->startFlow($instructions);
    expect($this->service->flowExists('test-exists'))->toBeTrue();
});

it('clears flow state', function () {
    $instructions = FormFlowInstructionsData::from([
        'reference_id' => 'ref-test-clear',
        'flow_id' => 'test-clear',
        'steps' => [['handler' => 'location', 'config' => []]],
        'callbacks' => ['on_complete' => 'http://example.com/callback'],
    ]);
    
    $this->service->startFlow($instructions);
    expect($this->service->flowExists('test-clear'))->toBeTrue();
    
    $this->service->clearFlow('test-clear');
    expect($this->service->flowExists('test-clear'))->toBeFalse();
});
