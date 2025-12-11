<?php

declare(strict_types=1);

namespace LBHurtado\FormFlowManager\Services;

use LBHurtado\FormFlowManager\Data\DriverConfigData;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Driver Registry
 * 
 * Manages driver configurations by loading from YAML/JSON files
 * and providing auto-discovery capabilities.
 */
class DriverRegistry
{
    /**
     * Registered drivers
     * 
     * @var array<string, DriverConfigData>
     */
    protected array $drivers = [];
    
    /**
     * Whether drivers have been discovered
     */
    protected bool $discovered = false;
    
    /**
     * Register a driver
     * 
     * @param string $name Driver name
     * @param DriverConfigData $driver Driver configuration
     * @return void
     */
    public function register(string $name, DriverConfigData $driver): void
    {
        $this->drivers[$name] = $driver;
    }
    
    /**
     * Get a driver by name
     * 
     * @param string $name Driver name
     * @return DriverConfigData|null Driver configuration or null if not found
     */
    public function get(string $name): ?DriverConfigData
    {
        // Auto-discover if not yet done
        if (!$this->discovered) {
            $this->discover();
        }
        
        return $this->drivers[$name] ?? null;
    }
    
    /**
     * Check if a driver exists
     * 
     * @param string $name Driver name
     * @return bool Whether driver exists
     */
    public function has(string $name): bool
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return isset($this->drivers[$name]);
    }
    
    /**
     * Get all registered drivers
     * 
     * @return array<string, DriverConfigData> All drivers
     */
    public function all(): array
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return $this->drivers;
    }
    
    /**
     * Get driver names
     * 
     * @return array<string> Driver names
     */
    public function names(): array
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return array_keys($this->drivers);
    }
    
    /**
     * Load driver from file
     * 
     * @param string $path Path to driver file
     * @return DriverConfigData Driver configuration
     * @throws \Exception If file cannot be loaded or parsed
     */
    public function loadFromFile(string $path): DriverConfigData
    {
        if (!File::exists($path)) {
            throw new \Exception("Driver file not found: {$path}");
        }
        
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        try {
            $config = match($extension) {
                'yaml', 'yml' => $this->loadYaml($path),
                'json' => $this->loadJson($path),
                default => throw new \Exception("Unsupported file format: {$extension}"),
            };
        } catch (\Throwable $e) {
            throw new \Exception("Failed to parse driver file {$path}: {$e->getMessage()}", 0, $e);
        }
        
        // Validate driver structure
        $this->validateDriverConfig($config, $path);
        
        // Flatten driver and mappings at root level
        $driverData = array_merge(
            $config['driver'] ?? [],
            ['mappings' => $config['mappings'] ?? []],
            ['constants' => $config['constants'] ?? null],
            ['filters' => $config['filters'] ?? null]
        );
        
        return DriverConfigData::from($driverData);
    }
    
    /**
     * Load YAML file
     * 
     * @param string $path File path
     * @return array Parsed configuration
     */
    protected function loadYaml(string $path): array
    {
        return Yaml::parseFile($path);
    }
    
    /**
     * Load JSON file
     * 
     * @param string $path File path
     * @return array Parsed configuration
     */
    protected function loadJson(string $path): array
    {
        $contents = File::get($path);
        $config = json_decode($contents, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $config;
    }
    
    /**
     * Validate driver configuration structure
     * 
     * @param array $config Configuration array
     * @param string $path File path (for error messages)
     * @return void
     * @throws \Exception If validation fails
     */
    protected function validateDriverConfig(array $config, string $path): void
    {
        // Check for required top-level keys
        if (!isset($config['driver'])) {
            throw new \Exception("Driver configuration missing 'driver' section in {$path}");
        }
        
        if (!isset($config['mappings'])) {
            throw new \Exception("Driver configuration missing 'mappings' section in {$path}");
        }
        
        $driver = $config['driver'];
        
        // Check required driver fields
        $requiredFields = ['name', 'version', 'source', 'target'];
        foreach ($requiredFields as $field) {
            if (!isset($driver[$field]) || empty($driver[$field])) {
                throw new \Exception("Driver configuration missing required field 'driver.{$field}' in {$path}");
            }
        }
        
        // Validate version format (basic semantic version check)
        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $driver['version'])) {
            throw new \Exception("Invalid version format '{$driver['version']}' in {$path}. Expected format: X.Y or X.Y.Z");
        }
        
        // Validate source and target are valid class names (basic check)
        foreach (['source', 'target'] as $classField) {
            $className = $driver[$classField];
            if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*$/', $className)) {
                throw new \Exception("Invalid class name for 'driver.{$classField}': {$className} in {$path}");
            }
        }
    }
    
    /**
     * Auto-discover driver files
     * 
     * Scans config/form-flow-drivers/ for *.yaml, *.yml, *.json files
     * and registers all discovered drivers.
     * 
     * @return void
     */
    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        
        $this->discovered = true;
        
        // Try to get Laravel config path, fallback to null if not in Laravel context
        $driverPath = function_exists('config_path') ? config_path('form-flow-drivers') : null;
        
        // If not in Laravel context, skip discovery
        if ($driverPath === null) {
            return;
        }
        
        // Create directory if it doesn't exist
        if (!File::exists($driverPath)) {
            File::makeDirectory($driverPath, 0755, true);
            return;
        }
        
        // Find all driver files
        $patterns = [
            "{$driverPath}/*.yaml",
            "{$driverPath}/*.yml",
            "{$driverPath}/*.json",
        ];
        
        $driverFiles = [];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                $driverFiles = array_merge($driverFiles, $files);
            }
        }
        
        // Load and register each driver
        foreach ($driverFiles as $file) {
            try {
                $driver = $this->loadFromFile($file);
                $this->register($driver->name, $driver);
            } catch (\Throwable $e) {
                // Log error but don't stop discovery (only if logger available)
                if (function_exists('logger')) {
                    logger()->error("Failed to load driver from {$file}: {$e->getMessage()}");
                }
            }
        }
    }
    
    /**
     * Reload all drivers (clear cache and rediscover)
     * 
     * @return void
     */
    public function reload(): void
    {
        $this->drivers = [];
        $this->discovered = false;
        $this->discover();
    }
    
    /**
     * Get drivers by source class
     * 
     * @param string $sourceClass Source class FQCN
     * @return array<DriverConfigData> Matching drivers
     */
    public function getBySource(string $sourceClass): array
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return collect($this->drivers)
            ->filter(fn(DriverConfigData $driver) => $driver->source === $sourceClass)
            ->values()
            ->all();
    }
    
    /**
     * Get drivers by target class
     * 
     * @param string $targetClass Target class FQCN
     * @return array<DriverConfigData> Matching drivers
     */
    public function getByTarget(string $targetClass): array
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return collect($this->drivers)
            ->filter(fn(DriverConfigData $driver) => $driver->target === $targetClass)
            ->values()
            ->all();
    }
    
    /**
     * Get driver statistics
     * 
     * @return array Statistics
     */
    public function stats(): array
    {
        if (!$this->discovered) {
            $this->discover();
        }
        
        return [
            'total_drivers' => count($this->drivers),
            'driver_names' => array_keys($this->drivers),
            'source_classes' => collect($this->drivers)->pluck('source')->unique()->values()->all(),
            'target_classes' => collect($this->drivers)->pluck('target')->unique()->values()->all(),
        ];
    }
}
