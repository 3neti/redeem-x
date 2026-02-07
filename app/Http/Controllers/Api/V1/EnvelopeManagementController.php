<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Envelope Management Controller
 *
 * Provides API endpoints for:
 * - Listing available envelope drivers (for UI selectors)
 * - Creating envelopes for existing vouchers
 */
class EnvelopeManagementController extends Controller
{
    public function __construct(
        private readonly DriverService $driverService
    ) {}

    /**
     * List available envelope drivers.
     *
     * GET /api/v1/envelope-drivers
     *
     * Returns driver summaries for frontend selectors.
     */
    public function listDrivers(): JsonResponse
    {
        $driverList = $this->driverService->list();

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
                    'payload_schema' => $driver->payload->schema->inline,
                ];
            } catch (\Exception $e) {
                // Skip drivers that fail to load
                return null;
            }
        })->filter()->values()->all();

        return response()->json($drivers);
    }

    /**
     * Create an envelope for an existing voucher.
     *
     * POST /api/v1/vouchers/{code}/envelope
     *
     * @param Request $request
     * @param Voucher $voucher
     * @return JsonResponse
     */
    public function createEnvelope(Request $request, Voucher $voucher): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'driver_id' => 'required|string',
            'driver_version' => 'required|string',
            'initial_payload' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        // Check if voucher already has an envelope
        if ($voucher->hasEnvelope()) {
            return response()->json([
                'message' => 'Voucher already has an envelope attached',
                'envelope_id' => $voucher->envelope->id,
            ], 422);
        }

        // Verify driver exists
        try {
            $this->driverService->load($validated['driver_id'], $validated['driver_version']);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DriverNotFoundException $e) {
            return response()->json([
                'message' => 'Driver not found: ' . $validated['driver_id'] . '@' . $validated['driver_version'],
            ], 404);
        }

        // Create envelope
        $envelope = $voucher->createEnvelope(
            driverId: $validated['driver_id'],
            driverVersion: $validated['driver_version'],
            initialPayload: $validated['initial_payload'] ?? null,
            context: array_merge($validated['context'] ?? [], [
                'created_via' => 'api',
                'created_at' => now()->toIso8601String(),
            ]),
            actor: Auth::user()
        );

        return response()->json([
            'message' => 'Envelope created successfully',
            'envelope' => [
                'id' => $envelope->id,
                'reference_code' => $envelope->reference_code,
                'driver_id' => $envelope->driver_id,
                'driver_version' => $envelope->driver_version,
                'status' => $envelope->status->value,
            ],
        ], 201);
    }
}
