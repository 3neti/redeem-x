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

        if (! File::exists($path)) {
            throw new DriverNotFoundException("Driver not found: {$driverId}".($version ? "@{$version}" : ''));
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
            if (! empty($files)) {
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
        if (! isset($data['driver'])) {
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
            $schemaPath = $this->driverDirectory.'/'.$driver->id.'/'.$schemaConfig->uri;
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

        if (! File::isDirectory($this->driverDirectory)) {
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

    /**
     * Write a driver to YAML file
     */
    public function write(string $driverId, string $version, array $data): string
    {
        $dirPath = "{$this->driverDirectory}/{$driverId}";
        $filePath = "{$dirPath}/v{$version}.yaml";

        // Ensure directory exists
        if (! File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        // Convert to YAML
        $yaml = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Write file
        File::put($filePath, $yaml);

        // Clear cache for this driver
        $this->clearCache($driverId);
        Cache::forget("envelope_driver:{$driverId}:{$version}");

        return $filePath;
    }

    /**
     * Delete a driver YAML file
     */
    public function delete(string $driverId, string $version): bool
    {
        $filePath = "{$this->driverDirectory}/{$driverId}/v{$version}.yaml";

        if (! File::exists($filePath)) {
            return false;
        }

        File::delete($filePath);

        // Remove directory if empty
        $dirPath = "{$this->driverDirectory}/{$driverId}";
        if (File::isDirectory($dirPath) && count(File::files($dirPath)) === 0) {
            File::deleteDirectory($dirPath);
        }

        // Clear cache
        $this->clearCache($driverId);
        Cache::forget("envelope_driver:{$driverId}:{$version}");

        return true;
    }

    /**
     * Check if a driver exists
     */
    public function exists(string $driverId, string $version): bool
    {
        $filePath = "{$this->driverDirectory}/{$driverId}/v{$version}.yaml";

        return File::exists($filePath);
    }

    /**
     * Get count of envelopes using a specific driver
     */
    public function getUsageCount(string $driverId, string $version): int
    {
        return \LBHurtado\SettlementEnvelope\Models\Envelope::where('driver_id', $driverId)
            ->where('driver_version', $version)
            ->count();
    }

    /**
     * Convert form data to driver YAML structure
     */
    public function toYamlStructure(array $formData): array
    {
        return [
            'driver' => [
                'id' => $formData['id'],
                'version' => $formData['version'],
                'title' => $formData['title'],
                'description' => $formData['description'] ?? null,
                'domain' => $formData['domain'] ?? null,
                'issuer_type' => $formData['issuer_type'] ?? null,
            ],
            'payload' => [
                'schema' => [
                    'id' => $formData['payload_schema_id'] ?? "{$formData['id']}.v{$formData['version']}",
                    'format' => 'json_schema',
                    'inline' => $formData['payload_schema'] ?? [
                        'type' => 'object',
                        'properties' => new \stdClass,
                        'required' => [],
                    ],
                ],
                'storage' => [
                    'mode' => 'versioned',
                    'patch_strategy' => 'merge',
                ],
            ],
            'documents' => [
                'registry' => $formData['documents'] ?? [],
            ],
            'checklist' => [
                'template' => $formData['checklist'] ?? [],
            ],
            'signals' => [
                'definitions' => $formData['signals'] ?? [],
            ],
            'gates' => [
                'definitions' => $formData['gates'] ?? [],
            ],
            'audit' => [
                'enabled' => true,
                'capture' => ['payload_patch', 'attachment_upload', 'attachment_review', 'signal_set', 'gate_change'],
            ],
            'manifest' => [
                'enabled' => true,
                'includes' => [
                    'payload_hash' => true,
                    'attachments_hashes' => true,
                    'envelope_fingerprint' => true,
                ],
            ],
        ];
    }
}
