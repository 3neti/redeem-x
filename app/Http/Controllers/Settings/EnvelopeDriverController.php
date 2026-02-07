<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\SettlementEnvelope\Services\DriverService;

/**
 * Envelope Driver Controller
 *
 * Admin interface for viewing envelope driver configurations.
 * Read-only - YAML files are the source of truth.
 */
class EnvelopeDriverController extends Controller
{
    public function __construct(
        private readonly DriverService $driverService
    ) {}

    /**
     * List available envelope drivers.
     */
    public function index(): Response
    {
        $driverList = $this->driverService->list();
        
        // Load full driver data for each
        $drivers = collect($driverList)->map(function ($item) {
            try {
                $driver = $this->driverService->load($item['id'], $item['version']);
                return [
                    'id' => $driver->id,
                    'version' => $driver->version,
                    'title' => $driver->title,
                    'description' => $driver->description,
                    'domain' => $driver->domain,
                    'documents_count' => $driver->documents->count(),
                    'checklist_count' => $driver->checklist->count(),
                    'signals_count' => $driver->signals->count(),
                    'gates_count' => $driver->gates->count(),
                ];
            } catch (\Exception $e) {
                // Return basic info if driver fails to load
                return [
                    'id' => $item['id'],
                    'version' => $item['version'],
                    'title' => $item['id'],
                    'description' => 'Error loading driver: ' . $e->getMessage(),
                    'domain' => null,
                    'documents_count' => 0,
                    'checklist_count' => 0,
                    'signals_count' => 0,
                    'gates_count' => 0,
                    'error' => true,
                ];
            }
        })->values()->all();

        return Inertia::render('settings/envelope-drivers/Index', [
            'drivers' => $drivers,
        ]);
    }

    /**
     * Show driver details.
     */
    public function show(string $id, string $version): Response
    {
        try {
            $driver = $this->driverService->load($id, $version);
            $schema = $this->driverService->getSchema($driver);
            
            return Inertia::render('settings/envelope-drivers/Show', [
                'driver' => [
                    'id' => $driver->id,
                    'version' => $driver->version,
                    'title' => $driver->title,
                    'description' => $driver->description,
                    'domain' => $driver->domain,
                    'issuer_type' => $driver->issuer_type,
                    'documents' => $driver->documents->toArray(),
                    'checklist' => $driver->checklist->toArray(),
                    'signals' => $driver->signals->toArray(),
                    'gates' => $driver->gates->toArray(),
                    'payload' => [
                        'schema' => [
                            'id' => $driver->payload->schema->id,
                            'format' => $driver->payload->schema->format,
                            'inline' => $schema,
                        ],
                        'storage' => $driver->payload->storage ? [
                            'mode' => $driver->payload->storage->mode,
                            'patch_strategy' => $driver->payload->storage->patch_strategy,
                        ] : null,
                    ],
                    'permissions' => $driver->permissions,
                    'ui' => $driver->ui,
                ],
            ]);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException $e) {
            abort(404, "Driver not found: {$id}@{$version}");
        }
    }
}
