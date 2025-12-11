<?php

use LBHurtado\FormFlowManager\Services\DriverRegistry;
use LBHurtado\FormFlowManager\Data\DriverConfigData;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->registry = new DriverRegistry();
});

it('registers and retrieves a driver', function () {
    $driver = DriverConfigData::from([
        'name' => 'test-driver',
        'version' => '1.0',
        'source' => 'App\\Source',
        'target' => 'App\\Target',
        'mappings' => ['field' => 'value'],
    ]);
    
    $this->registry->register('test-driver', $driver);
    
    expect($this->registry->has('test-driver'))->toBeTrue();
    expect($this->registry->get('test-driver'))->toBe($driver);
});

it('returns null for non-existent driver', function () {
    expect($this->registry->get('non-existent'))->toBeNull();
    expect($this->registry->has('non-existent'))->toBeFalse();
});

it('loads driver from YAML file', function () {
    $yamlPath = __DIR__ . '/../Fixtures/drivers/test-driver.yaml';
    
    $driver = $this->registry->loadFromFile($yamlPath);
    
    expect($driver)->toBeInstanceOf(DriverConfigData::class);
    expect($driver->name)->toBe('test-driver');
    expect($driver->version)->toBe('1.0');
    expect($driver->source)->toBe('App\\Source');
    expect($driver->target)->toBe('App\\Target');
    expect($driver->mappings)->toHaveKey('field');
    expect($driver->mappings['field'])->toBe('value');
    expect($driver->constants)->toHaveKey('test_constant');
    expect($driver->constants['test_constant'])->toBe('test_value');
});

it('validates driver structure', function () {
    $yamlPath = __DIR__ . '/../Fixtures/drivers/invalid-driver.yaml';
    
    $this->registry->loadFromFile($yamlPath);
})->throws(Exception::class);

it('returns all driver names', function () {
    $driver1 = DriverConfigData::from([
        'name' => 'driver1',
        'version' => '1.0',
        'source' => 'App\\Source1',
        'target' => 'App\\Target1',
        'mappings' => [],
    ]);
    
    $driver2 = DriverConfigData::from([
        'name' => 'driver2',
        'version' => '1.0',
        'source' => 'App\\Source2',
        'target' => 'App\\Target2',
        'mappings' => [],
    ]);
    
    $this->registry->register('driver1', $driver1);
    $this->registry->register('driver2', $driver2);
    
    $names = $this->registry->names();
    
    expect($names)->toContain('driver1');
    expect($names)->toContain('driver2');
    expect($names)->toHaveCount(2);
});

it('filters drivers by source class', function () {
    $driver1 = DriverConfigData::from([
        'name' => 'driver1',
        'version' => '1.0',
        'source' => 'App\\SourceA',
        'target' => 'App\\Target',
        'mappings' => [],
    ]);
    
    $driver2 = DriverConfigData::from([
        'name' => 'driver2',
        'version' => '1.0',
        'source' => 'App\\SourceB',
        'target' => 'App\\Target',
        'mappings' => [],
    ]);
    
    $this->registry->register('driver1', $driver1);
    $this->registry->register('driver2', $driver2);
    
    $results = $this->registry->getBySource('App\\SourceA');
    
    expect($results)->toHaveCount(1);
    expect($results[0]->name)->toBe('driver1');
});

it('provides driver statistics', function () {
    $driver = DriverConfigData::from([
        'name' => 'test',
        'version' => '1.0',
        'source' => 'App\\Source',
        'target' => 'App\\Target',
        'mappings' => [],
    ]);
    
    $this->registry->register('test', $driver);
    
    $stats = $this->registry->stats();
    
    expect($stats)->toHaveKey('total_drivers');
    expect($stats)->toHaveKey('driver_names');
    expect($stats)->toHaveKey('source_classes');
    expect($stats)->toHaveKey('target_classes');
    expect($stats['total_drivers'])->toBe(1);
});
