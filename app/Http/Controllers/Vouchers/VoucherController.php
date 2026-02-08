<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vouchers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Voucher Management Controller
 *
 * Handles listing, viewing, and exporting vouchers for authenticated users.
 */
class VoucherController extends Controller
{
    /**
     * Display the vouchers page.
     *
     * Data is loaded via API from the frontend.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('vouchers/Index');
    }

    /**
     * Display the specified voucher.
     */
    public function show(Voucher $voucher): Response
    {
        // Load relationships including inputs (single source of truth)
        $voucher->load(['owner', 'inputs']);

        // Get external metadata - prefer envelope payload, fallback to voucher storage (deprecated)
        $externalMetadata = $this->getExternalMetadataFromEnvelope($voucher) ?? $voucher->external_metadata;

        $data = [
            'voucher' => VoucherData::fromModel($voucher),
            'input_field_options' => VoucherInputField::options(),
            'external_metadata' => $externalMetadata,
        ];

        // TODO: @package-candidate settlement-envelope
        // Extract envelope data logic to package when UI API stabilizes
        // See WARP.md "Pending Package Extractions" section
        if (method_exists($voucher, 'envelope')) {
            $envelope = $voucher->envelope;
            if ($envelope) {
                $envelope->load(['checklistItems', 'attachments', 'signals', 'auditLogs']);

                // Compute gates and flags for UI
                $envelopeService = app(\LBHurtado\SettlementEnvelope\Services\EnvelopeService::class);
                $gates = $envelopeService->computeGates($envelope);
                $checklistContext = $this->buildChecklistContext($envelope);
                $signalContext = $this->buildSignalContext($envelope);

                $data['envelope'] = [
                    'id' => $envelope->id,
                    'reference_code' => $envelope->reference_code,
                    'driver_id' => $envelope->driver_id,
                    'driver_version' => $envelope->driver_version,
                    'status' => $envelope->status->value,
                    'payload' => $envelope->payload,
                    'payload_version' => $envelope->payload_version,
                    'context' => $envelope->context,
                    'gates_cache' => $gates,

                    // Computed flags for state machine (Phase 4)
                    'computed_flags' => [
                        'required_present' => $checklistContext['required_present'],
                        'required_accepted' => $checklistContext['required_accepted'],
                        'blocking_signals' => $signalContext['blocking'],
                        'all_signals_satisfied' => $signalContext['all_satisfied'],
                        'settleable' => $gates['settleable'] ?? false,
                    ],

                    // Status helpers from enum
                    'status_helpers' => [
                        'can_edit' => $envelope->status->canEdit(),
                        'can_lock' => $envelope->status->canLock(),
                        'can_settle' => $envelope->status->value === 'locked',
                        'can_cancel' => $envelope->status->canCancel(),
                        'can_reject' => $envelope->status->canReject(),
                        'can_reopen' => $envelope->status->canReopen(),
                        'is_terminal' => $envelope->status->isTerminal(),
                    ],

                    // Timestamps
                    'locked_at' => $envelope->locked_at?->toIso8601String(),
                    'settled_at' => $envelope->settled_at?->toIso8601String(),
                    'cancelled_at' => $envelope->cancelled_at?->toIso8601String(),
                    'rejected_at' => $envelope->rejected_at?->toIso8601String(),
                    'created_at' => $envelope->created_at->toIso8601String(),
                    'updated_at' => $envelope->updated_at->toIso8601String(),

                    // Relationships
                    'checklist_items' => $envelope->checklistItems->map(fn ($item) => [
                        'id' => $item->id,
                        'key' => $item->key,
                        'label' => $item->label,
                        'kind' => $item->kind->value,
                        'status' => $item->status->value,
                        'required' => $item->required,
                        'doc_type' => $item->doc_type,
                        'payload_pointer' => $item->payload_pointer,
                        'signal_key' => $item->signal_key,
                        'review_mode' => $item->review_mode ?? 'none',
                    ])->toArray(),
                    'attachments' => $envelope->attachments->map(fn ($att) => [
                        'id' => $att->id,
                        'doc_type' => $att->doc_type,
                        'original_filename' => $att->original_filename,
                        'mime_type' => $att->mime_type,
                        'size' => $att->size ?? null,
                        'review_status' => $att->review_status,
                        'rejection_reason' => $att->rejection_reason ?? null,
                        'url' => $att->file_path ? \Storage::disk($att->disk ?? 'public')->url($att->file_path) : null,
                        'created_at' => $att->created_at->toIso8601String(),
                    ])->toArray(),
                    'signals' => $envelope->signals->map(fn ($sig) => [
                        'id' => $sig->id,
                        'key' => $sig->key,
                        'type' => $sig->type,
                        'value' => $sig->value,
                        'source' => $sig->source,
                    ])->toArray(),
                    'audit_logs' => $envelope->auditLogs->sortBy('id')->values()->map(fn ($log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'actor_type' => $log->actor_type,
                        'actor_id' => $log->actor_id,
                        'actor_email' => $log->actor?->email ?? null,
                        'before' => $log->before,
                        'after' => $log->after,
                        'reason' => $log->reason ?? null,
                        'created_at' => $log->created_at->toIso8601String(),
                    ])->toArray(),
                ];
            }
        }

        // Add envelope drivers for creating new envelopes (when voucher has no envelope)
        if (! isset($data['envelope'])) {
            try {
                $driverService = app(\LBHurtado\SettlementEnvelope\Services\DriverService::class);
                $driverList = $driverService->list();
                $data['envelope_drivers'] = collect($driverList)->map(function ($item) use ($driverService) {
                    try {
                        $driver = $driverService->load($item['id'], $item['version']);

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
                        return null;
                    }
                })->filter()->values()->all();
            } catch (\Exception $e) {
                $data['envelope_drivers'] = [];
            }
        }

        // Add settlement data if feature is enabled
        if (Feature::active('settlement-vouchers') && $voucher->voucher_type) {
            $data['settlement'] = [
                'type' => $voucher->voucher_type->value,
                'state' => $voucher->state->value,
                'target_amount' => $voucher->target_amount,
                'paid_total' => $voucher->getPaidTotal(),
                'redeemed_total' => $voucher->getRedeemedTotal(),
                'remaining' => $voucher->getRemaining(),
                'available_balance' => $voucher->cash?->balanceFloat ?? 0,
                'can_accept_payment' => $voucher->canAcceptPayment(),
                'can_redeem' => $voucher->canRedeem(),
                'is_locked' => $voucher->isLocked(),
                'is_closed' => $voucher->isClosed(),
                'is_expired' => $voucher->isExpired(),
                'locked_at' => $voucher->locked_at?->toIso8601String(),
                'closed_at' => $voucher->closed_at?->toIso8601String(),
                'rules' => $voucher->rules,
            ];
        }

        return Inertia::render('vouchers/Show', $data);
    }

    /**
     * Build checklist context for computed flags.
     * Mirrors GateEvaluator::buildChecklistContext() for frontend use.
     */
    private function buildChecklistContext($envelope): array
    {
        $items = $envelope->checklistItems;
        $requiredItems = $items->where('required', true);
        $requiredCount = $requiredItems->count();

        // Count required items that are NOT missing
        $requiredPresentCount = $requiredItems
            ->filter(fn ($item) => $item->status->value !== 'missing')
            ->count();

        // Count required items that are accepted
        $requiredAcceptedCount = $requiredItems
            ->filter(fn ($item) => $item->status->value === 'accepted')
            ->count();

        return [
            'required_present' => $requiredCount > 0 ? $requiredPresentCount === $requiredCount : true,
            'required_accepted' => $requiredCount > 0 ? $requiredAcceptedCount === $requiredCount : true,
        ];
    }

    /**
     * Build signal context for computed flags.
     * Extracts blocking signals for frontend display.
     */
    private function buildSignalContext($envelope): array
    {
        $blocking = [];

        // Check for signals that should be true but aren't
        foreach ($envelope->signals as $signal) {
            // For now, consider boolean signals with value 'false' as blocking
            // In Phase 5, this will use driver signal definitions for required/blocking logic
            if ($signal->type === 'boolean' && $signal->value !== 'true') {
                $blocking[] = $signal->key;
            }
        }

        return [
            'blocking' => $blocking,
            'all_satisfied' => empty($blocking),
        ];
    }

    /**
     * Get external metadata from envelope payload (if envelope exists)
     * @deprecated Use envelope payload instead of voucher external_metadata
     */
    private function getExternalMetadataFromEnvelope(Voucher $voucher): ?array
    {
        $envelope = $voucher->envelope;
        if (! $envelope) {
            return null;
        }

        // Return the envelope payload as external metadata
        return $envelope->payload;
    }
}
