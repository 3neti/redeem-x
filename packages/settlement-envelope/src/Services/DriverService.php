<?php

namespace LBHurtado\SettlementEnvelope\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use LBHurtado\SettlementEnvelope\Data\DriverData;
use LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException;
use LBHurtado\SettlementEnvelope\Exceptions\InvalidDriverException;
use Symfony\Component\Yaml\Yaml;

class DriverService
{
    protected array $loadedDrivers = [];

    public function __construct(
        protected ?string $driverDirectory = null
    ) {
        $this->driverDirectory = $driverDirectory ?? config('settlement-envelope.driver_directory');
    }

    /**
     * Load a driver by ID and optional version
     */
    public function load(string $driverId, ?string $version = null): DriverData
    {
        $cacheKey = "envelope_driver:{$driverId}:{$version}";

        // Check memory cache first
        if (isset($this->loadedDrivers[$cacheKey])) {
            return $this->loadedDrivers[$cacheKey];
        }

        // Check application cache
        $driver = Cache::remember($cacheKey, 3600, function () use ($driverId, $version) {
            return $this->loadFromFile($driverId, $version);
        });

        $this->loadedDrivers[$cacheKey] = $driver;

        return $driver;
    }

    /**
     * Load driver from YAML file
     */
    protected function loadFromFile(string $driverId, ?string $version = null): DriverData
    {
        $path = $this->resolveDriverPath($driverId, $version);

        if (!File::exists($path)) {
            throw new DriverNotFoundException("Driver not found: {$driverId}" . ($version ? "@{$version}" : ''));
        }

        $content = File::get($path);
        $data = Yaml::parse($content);

        return $this->parseDriver($data, $driverId);
    }

    /**
     * Resolve driver file path
     */
    protected function resolveDriverPath(string $driverId, ?string $version = null): string
    {
        $basePath = $this->driverDirectory;

        // Try versioned path first: drivers/{driverId}/v{version}.yaml
        if ($version) {
            $versionedPath = "{$basePath}/{$driverId}/v{$version}.yaml";
            if (File::exists($versionedPath)) {
                return $versionedPath;
            }
        }

        // Try latest version in directory
        $driverDir = "{$basePath}/{$driverId}";
        if (File::isDirectory($driverDir)) {
            $files = File::glob("{$driverDir}/v*.yaml");
            if (!empty($files)) {
                // Sort and get latest version
                usort($files, 'version_compare');
                return end($files);
            }
        }

        // Try flat file: drivers/{driverId}.yaml
        $flatPath = "{$basePath}/{$driverId}.yaml";
        if (File::exists($flatPath)) {
            return $flatPath;
        }

        throw new DriverNotFoundException("Driver not found: {$driverId}");
    }

    /**
     * Parse raw YAML data into DriverData
     */
    protected function parseDriver(array $data, string $driverId): DriverData
    {
        if (!isset($data['driver'])) {
            throw new InvalidDriverException("Invalid driver format: missing 'driver' key");
        }

        $driver = $data['driver'];

        return DriverData::from([
            'id' => $driver['id'] ?? $driverId,
            'version' => $driver['version'] ?? '1.0.0',
            'title' => $driver['title'] ?? $driverId,
            'description' => $driver['description'] ?? null,
            'domain' => $driver['domain'] ?? null,
            'issuer_type' => $driver['issuer_type'] ?? null,
            'payload' => $this->parsePayloadConfig($data['payload'] ?? []),
            'documents' => $this->parseDocuments($data['documents']['registry'] ?? []),
            'checklist' => $data['checklist']['template'] ?? [],
            'signals' => $data['signals']['definitions'] ?? [],
            'gates' => $data['gates']['definitions'] ?? [],
            'permissions' => $data['permissions'] ?? null,
            'audit' => $data['audit'] ?? null,
            'manifest' => $data['manifest'] ?? null,
            'ui' => $data['ui'] ?? null,
        ]);
    }

    protected function parsePayloadConfig(array $config): array
    {
        return [
            'schema' => [
                'id' => $config['schema']['id'] ?? 'default',
                'format' => $config['schema']['format'] ?? 'json_schema',
                'uri' => $config['schema']['uri'] ?? null,
                'inline' => $config['schema']['inline'] ?? null,
            ],
            'storage' => [
                'mode' => $config['storage']['mode'] ?? 'versioned',
                'patch_strategy' => $config['storage']['patch_strategy'] ?? 'merge',
            ],
        ];
    }

    protected function parseDocuments(array $registry): array
    {
        return array_map(function ($doc) {
            return [
                'type' => $doc['type'],
                'title' => $doc['title'] ?? $doc['type'],
                'allowed_mimes' => $doc['allowed_mimes'] ?? ['application/pdf', 'image/jpeg', 'image/png'],
                'max_size_mb' => $doc['max_size_mb'] ?? 10,
                'multiple' => $doc['multiple'] ?? false,
            ];
        }, $registry);
    }

    /**
     * Get schema content for a driver
     */
    public function getSchema(DriverData $driver): ?array
    {
        $schemaConfig = $driver->payload->schema;

        // Inline schema
        if ($schemaConfig->inline) {
            return $schemaConfig->inline;
        }

        // External schema file
        if ($schemaConfig->uri) {
            $schemaPath = $this->driverDirectory . '/' . $driver->id . '/' . $schemaConfig->uri;
            if (File::exists($schemaPath)) {
                return json_decode(File::get($schemaPath), true);
            }
        }

        return null;
    }

    /**
     * List available drivers
     */
    public function list(): array
    {
        $drivers = [];

        if (!File::isDirectory($this->driverDirectory)) {
            return $drivers;
        }

        // Find all driver directories
        $directories = File::directories($this->driverDirectory);
        foreach ($directories as $dir) {
            $driverId = basename($dir);
            $files = File::glob("{$dir}/v*.yaml");
            foreach ($files as $file) {
                preg_match('/v(.+)\.yaml$/', basename($file), $matches);
                $version = $matches[1] ?? '1.0.0';
                $drivers[] = [
                    'id' => $driverId,
                    'version' => $version,
                    'path' => $file,
                ];
            }
        }

        // Find flat driver files
        $files = File::glob("{$this->driverDirectory}/*.yaml");
        foreach ($files as $file) {
            $driverId = basename($file, '.yaml');
            $drivers[] = [
                'id' => $driverId,
                'version' => '1.0.0',
                'path' => $file,
            ];
        }

        return $drivers;
    }

    /**
     * Clear driver cache
     */
    public function clearCache(?string $driverId = null): void
    {
        if ($driverId) {
            Cache::forget("envelope_driver:{$driverId}:*");
        }

        $this->loadedDrivers = [];
    }
}
