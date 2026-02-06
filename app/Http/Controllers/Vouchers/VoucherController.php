<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vouchers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use Laravel\Pennant\Feature;

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

        $data = [
            'voucher' => VoucherData::fromModel($voucher),
            'input_field_options' => VoucherInputField::options(),
            'external_metadata' => $voucher->external_metadata, // Freeform JSON metadata
        ];

        // TODO: @package-candidate settlement-envelope
        // Extract envelope data logic to package when UI API stabilizes
        // See WARP.md "Pending Package Extractions" section
        if (method_exists($voucher, 'envelope')) {
            $envelope = $voucher->envelope;
            if ($envelope) {
                $envelope->load(['checklistItems', 'attachments', 'signals', 'auditLogs']);
                $data['envelope'] = [
                    'id' => $envelope->id,
                    'reference_code' => $envelope->reference_code,
                    'driver_id' => $envelope->driver_id,
                    'driver_version' => $envelope->driver_version,
                    'status' => $envelope->status->value,
                    'payload' => $envelope->payload,
                    'payload_version' => $envelope->payload_version,
                    'context' => $envelope->context,
                    'gates_cache' => $envelope->gates_cache ?? [],
                    'locked_at' => $envelope->locked_at?->toIso8601String(),
                    'settled_at' => $envelope->settled_at?->toIso8601String(),
                    'cancelled_at' => $envelope->cancelled_at?->toIso8601String(),
                    'created_at' => $envelope->created_at->toIso8601String(),
                    'updated_at' => $envelope->updated_at->toIso8601String(),
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
                    ])->toArray(),
                    'attachments' => $envelope->attachments->map(fn ($att) => [
                        'id' => $att->id,
                        'doc_type' => $att->doc_type,
                        'original_filename' => $att->original_filename,
                        'mime_type' => $att->mime_type,
                        'review_status' => $att->review_status,
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
                    'audit_logs' => $envelope->auditLogs->map(fn ($log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'actor_type' => $log->actor_type,
                        'actor_id' => $log->actor_id,
                        'before' => $log->before,
                        'after' => $log->after,
                        'created_at' => $log->created_at->toIso8601String(),
                    ])->toArray(),
                ];
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
}
