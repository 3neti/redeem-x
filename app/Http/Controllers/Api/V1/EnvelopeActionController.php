<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAttachment;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Envelope Action Controller
 *
 * Handles workflow actions for settlement envelopes:
 * - Status transitions (lock, settle, cancel, reopen)
 * - Attachment reviews (accept, reject)
 * - Signal updates
 * - Payload patches
 */
class EnvelopeActionController extends Controller
{
    public function __construct(
        private readonly EnvelopeService $envelopeService
    ) {}

    /**
     * Lock an envelope (transition to LOCKED state).
     *
     * POST /api/v1/vouchers/{voucher}/envelope/lock
     */
    public function lock(Voucher $voucher): JsonResponse
    {
        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canLock()) {
            return response()->json([
                'message' => 'Cannot lock envelope in current state',
                'status' => $envelope->status->value,
            ], 422);
        }

        if (!$envelope->isSettleable()) {
            return response()->json([
                'message' => 'Envelope is not settleable - check gates and requirements',
            ], 422);
        }

        $envelope = $this->envelopeService->lock($envelope, Auth::user());

        return response()->json([
            'message' => 'Envelope locked successfully',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Settle an envelope (transition to SETTLED state).
     *
     * POST /api/v1/vouchers/{voucher}/envelope/settle
     */
    public function settle(Voucher $voucher): JsonResponse
    {
        $envelope = $this->getEnvelope($voucher);

        if ($envelope->status->value !== 'locked') {
            return response()->json([
                'message' => 'Can only settle a locked envelope',
                'status' => $envelope->status->value,
            ], 422);
        }

        $envelope = $this->envelopeService->settle($envelope, Auth::user());

        return response()->json([
            'message' => 'Envelope settled successfully',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Cancel an envelope (transition to CANCELLED state).
     *
     * POST /api/v1/vouchers/{voucher}/envelope/cancel
     */
    public function cancel(Request $request, Voucher $voucher): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canCancel()) {
            return response()->json([
                'message' => 'Cannot cancel envelope in current state',
                'status' => $envelope->status->value,
            ], 422);
        }

        $envelope = $this->envelopeService->cancel($envelope, $request->reason, Auth::user());

        return response()->json([
            'message' => 'Envelope cancelled',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Reopen a locked envelope (transition to REOPENED state).
     *
     * POST /api/v1/vouchers/{voucher}/envelope/reopen
     */
    public function reopen(Request $request, Voucher $voucher): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canReopen()) {
            return response()->json([
                'message' => 'Can only reopen a locked envelope',
                'status' => $envelope->status->value,
            ], 422);
        }

        $envelope = $this->envelopeService->reopen($envelope, $request->reason, Auth::user());

        return response()->json([
            'message' => 'Envelope reopened',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Accept an attachment (mark as reviewed/accepted).
     *
     * POST /api/v1/envelopes/{envelope}/attachments/{attachment}/accept
     */
    public function acceptAttachment(Envelope $envelope, EnvelopeAttachment $attachment): JsonResponse
    {
        if ($attachment->envelope_id !== $envelope->id) {
            abort(404, 'Attachment not found on this envelope');
        }

        if ($attachment->review_status !== 'pending') {
            return response()->json([
                'message' => 'Attachment already reviewed',
                'status' => $attachment->review_status,
            ], 422);
        }

        $this->envelopeService->reviewAttachment($attachment, 'accepted', Auth::user());
        $attachment->refresh();
        $envelope->refresh();

        return response()->json([
            'message' => 'Attachment accepted',
            'attachment' => $this->formatAttachment($attachment),
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Reject an attachment with reason.
     *
     * POST /api/v1/envelopes/{envelope}/attachments/{attachment}/reject
     */
    public function rejectAttachment(Request $request, Envelope $envelope, EnvelopeAttachment $attachment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($attachment->envelope_id !== $envelope->id) {
            abort(404, 'Attachment not found on this envelope');
        }

        if ($attachment->review_status !== 'pending') {
            return response()->json([
                'message' => 'Attachment already reviewed',
                'status' => $attachment->review_status,
            ], 422);
        }

        $this->envelopeService->reviewAttachment($attachment, 'rejected', Auth::user(), $request->reason);
        $attachment->refresh();
        $envelope->refresh();

        return response()->json([
            'message' => 'Attachment rejected',
            'attachment' => $this->formatAttachment($attachment),
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Set a signal value.
     *
     * POST /api/v1/vouchers/{voucher}/envelope/signals/{key}
     */
    public function setSignal(Request $request, Voucher $voucher, string $key): JsonResponse
    {
        $request->validate([
            'value' => 'required|boolean',
        ]);

        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canEdit()) {
            return response()->json([
                'message' => 'Cannot modify signals in current state',
                'status' => $envelope->status->value,
            ], 422);
        }

        $this->envelopeService->setSignal($envelope, $key, $request->value);
        $envelope->refresh();

        return response()->json([
            'message' => 'Signal updated',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Upload an attachment to the envelope.
     *
     * POST /api/v1/vouchers/{voucher}/envelope/attachments
     */
    public function uploadAttachment(Request $request, Voucher $voucher): JsonResponse
    {
        $request->validate([
            'doc_type' => 'required|string|max:100',
            'file' => 'required|file|max:10240', // 10MB max
            'metadata' => 'nullable|array',
        ]);

        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canEdit()) {
            return response()->json([
                'message' => 'Cannot upload attachments in current state',
                'status' => $envelope->status->value,
            ], 422);
        }

        try {
            $attachment = $this->envelopeService->uploadAttachment(
                $envelope,
                $request->doc_type,
                $request->file('file'),
                Auth::user(),
                $request->metadata
            );

            $envelope->refresh();

            return response()->json([
                'message' => 'Attachment uploaded successfully',
                'attachment' => $this->formatAttachment($attachment),
                'envelope' => $this->formatEnvelope($envelope),
            ]);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\DocumentTypeNotAllowedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update envelope payload.
     *
     * PATCH /api/v1/vouchers/{voucher}/envelope/payload
     */
    public function updatePayload(Request $request, Voucher $voucher): JsonResponse
    {
        $request->validate([
            'payload' => 'required|array',
        ]);

        $envelope = $this->getEnvelope($voucher);

        if (!$envelope->status->canEdit()) {
            return response()->json([
                'message' => 'Cannot modify payload in current state',
                'status' => $envelope->status->value,
            ], 422);
        }

        $envelope = $this->envelopeService->updatePayload($envelope, $request->payload);

        return response()->json([
            'message' => 'Payload updated',
            'envelope' => $this->formatEnvelope($envelope),
        ]);
    }

    /**
     * Get envelope for a voucher or fail.
     */
    private function getEnvelope(Voucher $voucher): Envelope
    {
        $envelope = $voucher->envelope;

        if (!$envelope) {
            abort(404, 'Voucher does not have an envelope');
        }

        return $envelope;
    }

    /**
     * Format envelope for JSON response.
     */
    private function formatEnvelope(Envelope $envelope): array
    {
        $envelope->load(['checklistItems', 'attachments', 'signals']);
        $gates = $this->envelopeService->computeGates($envelope);

        return [
            'id' => $envelope->id,
            'reference_code' => $envelope->reference_code,
            'status' => $envelope->status->value,
            'payload_version' => $envelope->payload_version,
            'gates_cache' => $gates,
            'status_helpers' => [
                'can_edit' => $envelope->status->canEdit(),
                'can_lock' => $envelope->status->canLock(),
                'can_settle' => $envelope->status->value === 'locked',
                'can_cancel' => $envelope->status->canCancel(),
                'can_reopen' => $envelope->status->canReopen(),
                'is_terminal' => $envelope->status->isTerminal(),
            ],
            'locked_at' => $envelope->locked_at?->toIso8601String(),
            'settled_at' => $envelope->settled_at?->toIso8601String(),
        ];
    }

    /**
     * Format attachment for JSON response.
     */
    private function formatAttachment(EnvelopeAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'doc_type' => $attachment->doc_type,
            'review_status' => $attachment->review_status,
            'rejection_reason' => $attachment->rejection_reason ?? null,
        ];
    }
}
