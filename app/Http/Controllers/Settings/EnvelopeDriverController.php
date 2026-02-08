<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreEnvelopeDriverRequest;
use App\Http\Requests\Settings\UpdateEnvelopeDriverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\SettlementEnvelope\Exceptions\InvalidDriverException;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Envelope Driver Controller
 *
 * Admin interface for managing envelope driver configurations.
 * Drivers define schema, documents, checklist, signals, and gates for envelopes.
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
                $extends = $this->driverService->getRawExtends($item['id'], $item['version']);

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
                    'extends' => $extends,
                    'is_base' => empty($extends),
                    'family' => $this->driverService->extractFamily($driver->id),
                ];
            } catch (\Exception $e) {
                // Return basic info if driver fails to load
                return [
                    'id' => $item['id'],
                    'version' => $item['version'],
                    'title' => $item['id'],
                    'description' => 'Error loading driver: '.$e->getMessage(),
                    'domain' => null,
                    'documents_count' => 0,
                    'checklist_count' => 0,
                    'signals_count' => 0,
                    'gates_count' => 0,
                    'extends' => [],
                    'is_base' => true,
                    'family' => $this->driverService->extractFamily($item['id']),
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
            $usageCount = $this->driverService->getUsageCount($id, $version);
            $extends = $this->driverService->getRawExtends($id, $version);
            $extendedBy = $this->driverService->getExtendedBy($id, $version);

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
                    'extends' => $extends,
                    'extended_by' => $extendedBy,
                    'is_base' => empty($extends),
                ],
                'usage_count' => $usageCount,
            ]);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException $e) {
            abort(404, "Driver not found: {$id}@{$version}");
        }
    }

    /**
     * Show the form for creating a new driver.
     */
    public function create(): Response
    {
        return Inertia::render('settings/envelope-drivers/Create', [
            'templates' => $this->getDriverTemplates(),
        ]);
    }

    /**
     * Store a newly created driver.
     */
    public function store(StoreEnvelopeDriverRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Convert form data to YAML structure
        $yamlData = $this->driverService->toYamlStructure($validated);

        // Write the driver file
        $this->driverService->write($validated['id'], $validated['version'], $yamlData);

        return redirect()
            ->route('settings.envelope-drivers.show', [
                'id' => $validated['id'],
                'version' => $validated['version'],
            ])
            ->with('success', "Driver '{$validated['title']}' created successfully.");
    }

    /**
     * Show the form for editing an existing driver.
     */
    public function edit(string $id, string $version): Response
    {
        try {
            $driver = $this->driverService->load($id, $version);
            $schema = $this->driverService->getSchema($driver);
            $usageCount = $this->driverService->getUsageCount($id, $version);

            return Inertia::render('settings/envelope-drivers/Edit', [
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
                    'payload_schema' => $schema,
                ],
                'usage_count' => $usageCount,
            ]);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException $e) {
            abort(404, "Driver not found: {$id}@{$version}");
        }
    }

    /**
     * Update the specified driver.
     */
    public function update(UpdateEnvelopeDriverRequest $request, string $id, string $version): RedirectResponse
    {
        try {
            $this->driverService->load($id, $version);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException $e) {
            abort(404, "Driver not found: {$id}@{$version}");
        }

        $validated = $request->validated();

        // Determine target version
        $targetVersion = $version;
        if (! empty($validated['save_as_new_version']) && ! empty($validated['new_version'])) {
            $targetVersion = $validated['new_version'];

            // Check if new version already exists
            if ($this->driverService->exists($id, $targetVersion)) {
                return back()->withErrors(['new_version' => "Version {$targetVersion} already exists."]);
            }
        }

        // Build form data with id and version
        $formData = array_merge($validated, [
            'id' => $id,
            'version' => $targetVersion,
        ]);

        // Convert to YAML structure and write
        $yamlData = $this->driverService->toYamlStructure($formData);
        $this->driverService->write($id, $targetVersion, $yamlData);

        $message = $targetVersion !== $version
            ? "Driver saved as new version {$targetVersion}."
            : "Driver '{$validated['title']}' updated successfully.";

        return redirect()
            ->route('settings.envelope-drivers.show', ['id' => $id, 'version' => $targetVersion])
            ->with('success', $message);
    }

    /**
     * Delete the specified driver.
     */
    public function destroy(string $id, string $version): RedirectResponse
    {
        // Check usage before delete
        $usageCount = $this->driverService->getUsageCount($id, $version);

        if ($usageCount > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete driver: {$usageCount} envelope(s) are using this driver.",
            ]);
        }

        $deleted = $this->driverService->delete($id, $version);

        if (! $deleted) {
            return back()->withErrors(['delete' => 'Failed to delete driver.']);
        }

        return redirect()
            ->route('settings.envelope-drivers.index')
            ->with('success', "Driver '{$id}@{$version}' deleted successfully.");
    }

    /**
     * Export a driver as YAML file download.
     */
    public function export(string $id, string $version): StreamedResponse
    {
        $disk = config('settlement-envelope.driver_disk', 'envelope-drivers');
        $path = "{$id}/v{$version}.yaml";

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, "Driver not found: {$id}@{$version}");
        }

        $filename = "{$id}-v{$version}.yaml";

        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => 'application/x-yaml',
        ]);
    }

    /**
     * Import a driver from uploaded YAML file.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:yaml,yml,txt', 'max:1024'], // 1MB max
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());

        try {
            $data = Yaml::parse($content);
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Invalid YAML file: '.$e->getMessage()]);
        }

        // Validate driver structure
        if (! isset($data['driver']['id']) || ! isset($data['driver']['version'])) {
            return back()->withErrors(['file' => 'Invalid driver format: missing driver.id or driver.version']);
        }

        $driverId = $data['driver']['id'];
        $version = $data['driver']['version'];

        // Check if driver already exists
        if ($this->driverService->exists($driverId, $version)) {
            return back()->withErrors(['file' => "Driver '{$driverId}@{$version}' already exists. Delete it first or change the version."]);
        }

        // Validate the driver can be parsed
        try {
            $this->driverService->write($driverId, $version, $data);
            // Try to load it to validate
            $this->driverService->load($driverId, $version);
        } catch (InvalidDriverException $e) {
            // Delete the invalid driver
            $this->driverService->delete($driverId, $version);

            return back()->withErrors(['file' => 'Invalid driver structure: '.$e->getMessage()]);
        }

        return redirect()
            ->route('settings.envelope-drivers.show', ['id' => $driverId, 'version' => $version])
            ->with('success', "Driver '{$driverId}@{$version}' imported successfully.");
    }

    /**
     * Get available driver templates for the create form.
     */
    protected function getDriverTemplates(): array
    {
        return [
            [
                'id' => 'simple',
                'title' => 'Simple Envelope',
                'description' => 'Basic envelope with minimal requirements',
                'data' => [
                    'payload_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'reference' => ['type' => 'string'],
                        ],
                        'required' => ['name'],
                    ],
                    'documents' => [],
                    'checklist' => [
                        [
                            'key' => 'name_provided',
                            'label' => 'Name provided',
                            'kind' => 'payload_field',
                            'payload_pointer' => '/name',
                            'required' => true,
                            'review' => 'none',
                        ],
                    ],
                    'signals' => [
                        [
                            'key' => 'approved',
                            'type' => 'boolean',
                            'source' => 'host',
                            'default' => false,
                            'required' => true,
                            'signal_category' => 'decision',
                            'system_settable' => false,
                        ],
                    ],
                    'gates' => [
                        ['key' => 'payload_valid', 'rule' => 'payload.valid == true'],
                        ['key' => 'settleable', 'rule' => 'gate.payload_valid && signal.approved'],
                    ],
                ],
            ],
            [
                'id' => 'document-review',
                'title' => 'Document Review',
                'description' => 'Envelope requiring document upload and review',
                'data' => [
                    'payload_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'reference_id' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'amount' => ['type' => 'number'],
                        ],
                        'required' => ['reference_id', 'amount'],
                    ],
                    'documents' => [
                        [
                            'type' => 'SUPPORTING_DOC',
                            'title' => 'Supporting Document',
                            'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
                            'max_size_mb' => 10,
                            'multiple' => false,
                        ],
                    ],
                    'checklist' => [
                        [
                            'key' => 'reference_provided',
                            'label' => 'Reference ID provided',
                            'kind' => 'payload_field',
                            'payload_pointer' => '/reference_id',
                            'required' => true,
                            'review' => 'none',
                        ],
                        [
                            'key' => 'document_uploaded',
                            'label' => 'Supporting document uploaded',
                            'kind' => 'document',
                            'doc_type' => 'SUPPORTING_DOC',
                            'required' => true,
                            'review' => 'required',
                        ],
                    ],
                    'signals' => [
                        [
                            'key' => 'approved',
                            'type' => 'boolean',
                            'source' => 'host',
                            'default' => false,
                            'required' => true,
                            'signal_category' => 'decision',
                            'system_settable' => false,
                        ],
                    ],
                    'gates' => [
                        ['key' => 'payload_valid', 'rule' => 'payload.valid == true'],
                        ['key' => 'docs_reviewed', 'rule' => 'checklist.required_accepted == true'],
                        ['key' => 'settleable', 'rule' => 'gate.payload_valid && gate.docs_reviewed && signal.approved'],
                    ],
                ],
            ],
            [
                'id' => 'payment-verification',
                'title' => 'Payment Verification',
                'description' => 'Envelope for payment/transaction verification flows',
                'data' => [
                    'payload_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'transaction_id' => ['type' => 'string'],
                            'amount' => ['type' => 'number', 'minimum' => 0],
                            'currency' => ['type' => 'string', 'default' => 'PHP'],
                            'payer_name' => ['type' => 'string'],
                            'payer_mobile' => ['type' => 'string'],
                        ],
                        'required' => ['transaction_id', 'amount', 'payer_name'],
                    ],
                    'documents' => [
                        [
                            'type' => 'PROOF_OF_PAYMENT',
                            'title' => 'Proof of Payment',
                            'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                            'max_size_mb' => 5,
                            'multiple' => false,
                        ],
                    ],
                    'checklist' => [
                        [
                            'key' => 'transaction_id_provided',
                            'label' => 'Transaction ID provided',
                            'kind' => 'payload_field',
                            'payload_pointer' => '/transaction_id',
                            'required' => true,
                            'review' => 'none',
                        ],
                        [
                            'key' => 'amount_specified',
                            'label' => 'Amount specified',
                            'kind' => 'payload_field',
                            'payload_pointer' => '/amount',
                            'required' => true,
                            'review' => 'none',
                        ],
                        [
                            'key' => 'payment_proof',
                            'label' => 'Proof of payment uploaded',
                            'kind' => 'document',
                            'doc_type' => 'PROOF_OF_PAYMENT',
                            'required' => true,
                            'review' => 'required',
                        ],
                        [
                            'key' => 'payment_verified',
                            'label' => 'Payment verified',
                            'kind' => 'signal',
                            'signal_key' => 'payment_verified',
                            'required' => true,
                            'review' => 'none',
                        ],
                    ],
                    'signals' => [
                        [
                            'key' => 'payment_verified',
                            'type' => 'boolean',
                            'source' => 'host',
                            'default' => false,
                            'required' => true,
                            'signal_category' => 'integration',
                            'system_settable' => true,
                        ],
                        [
                            'key' => 'approved',
                            'type' => 'boolean',
                            'source' => 'host',
                            'default' => false,
                            'required' => true,
                            'signal_category' => 'decision',
                            'system_settable' => false,
                        ],
                    ],
                    'gates' => [
                        ['key' => 'payload_valid', 'rule' => 'payload.valid == true'],
                        ['key' => 'docs_ready', 'rule' => 'checklist.required_present == true'],
                        ['key' => 'payment_confirmed', 'rule' => 'signal.payment_verified == true'],
                        ['key' => 'settleable', 'rule' => 'gate.payload_valid && gate.docs_ready && gate.payment_confirmed && signal.approved'],
                    ],
                ],
            ],
        ];
    }
}
