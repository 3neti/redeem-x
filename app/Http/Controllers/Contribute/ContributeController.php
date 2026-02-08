<?php

namespace App\Http\Controllers\Contribute;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAuditLog;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Models\Voucher;

class ContributeController extends Controller
{
    public function __construct(
        private readonly EnvelopeService $envelopeService,
        private readonly DriverService $driverService,
    ) {}

    /**
     * Show the contribution page
     * Validates signed URL and contribution token
     */
    public function show(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'voucher' => 'required|string',
            'token' => 'required|uuid',
        ]);

        $voucherCode = strtoupper(trim($request->voucher));
        $tokenUuid = $request->token;

        // Find voucher
        $voucher = Voucher::where('code', $voucherCode)->first();
        if (! $voucher) {
            return inertia('contribute/Error', [
                'error' => 'Voucher not found',
                'code' => 'VOUCHER_NOT_FOUND',
            ]);
        }

        // Find envelope
        $envelope = $voucher->envelope;
        if (! $envelope) {
            return inertia('contribute/Error', [
                'error' => 'This voucher does not have a settlement envelope',
                'code' => 'NO_ENVELOPE',
            ]);
        }

        // Find and validate contribution token
        $token = EnvelopeContributionToken::byToken($tokenUuid)->first();
        if (! $token) {
            return inertia('contribute/Error', [
                'error' => 'Invalid contribution link',
                'code' => 'INVALID_TOKEN',
            ]);
        }

        if ($token->envelope_id !== $envelope->id) {
            return inertia('contribute/Error', [
                'error' => 'This link is not valid for this voucher',
                'code' => 'TOKEN_MISMATCH',
            ]);
        }

        if ($token->isExpired()) {
            return inertia('contribute/Error', [
                'error' => 'This contribution link has expired',
                'code' => 'TOKEN_EXPIRED',
            ]);
        }

        if ($token->isRevoked()) {
            return inertia('contribute/Error', [
                'error' => 'This contribution link has been revoked',
                'code' => 'TOKEN_REVOKED',
            ]);
        }

        // Check if password is required and not yet verified
        $sessionKey = "contribute_verified_{$token->id}";
        $passwordRequired = $token->requiresPassword();
        $passwordVerified = session($sessionKey, false);

        if ($passwordRequired && ! $passwordVerified) {
            return inertia('contribute/Password', [
                'voucher_code' => $voucher->code,
                'token' => $tokenUuid,
                'label' => $token->label,
                'recipient_name' => $token->recipient_name,
            ]);
        }

        // Record token usage
        $token->recordUsage();

        // Get driver for document types
        $driver = $this->driverService->load($envelope->driver_id, $envelope->driver_version);
        $documentTypes = collect($driver->documents->toArray())->map(fn ($doc) => [
            'code' => $doc['type'],
            'label' => $doc['title'],
            'description' => $doc['title'], // Use title as description if no dedicated field
            'required' => false, // Driver doesn't specify required at document level
            'max_files' => ($doc['multiple'] ?? false) ? 10 : 1,
        ])->values()->toArray();

        // Extract payload fields from checklist (payload_field items with specific pointers)
        $payloadSchemaFields = collect($driver->checklist->toArray())
            ->filter(fn ($item) => ($item['kind'] ?? '') === 'payload_field'
                && ($item['payload_pointer'] ?? '') !== '/' // Exclude root pointer (generic)
                && ! empty($item['payload_pointer']))
            ->map(fn ($item) => [
                'key' => $this->pointerToFieldName($item['payload_pointer'] ?? ''),
                'label' => $item['label'] ?? '',
                'required' => $item['required'] ?? false,
                'pointer' => $item['payload_pointer'] ?? '',
            ])
            ->values()
            ->toArray();

        // Get existing attachments
        $attachments = $envelope->attachments->map(fn ($att) => [
            'id' => $att->id,
            'file_name' => $att->original_filename,
            'doc_type' => $att->doc_type,
            'review_status' => is_string($att->review_status) ? $att->review_status : $att->review_status->value,
            'uploaded_at' => $att->created_at->toIso8601String(),
            'url' => Storage::disk($att->disk ?? 'public')->url($att->file_path),
        ])->toArray();

        return inertia('contribute/Index', [
            'voucher' => [
                'code' => $voucher->code,
                'type' => $voucher->voucher_type->value,
                'amount' => $voucher->instructions->cash->amount ?? 0,
                'currency' => $voucher->instructions->cash->currency ?? 'PHP',
            ],
            'envelope' => [
                'id' => $envelope->id,
                'status' => $envelope->status->value,
                'payload' => $envelope->payload,
            ],
            'token' => [
                'uuid' => $token->token,
                'label' => $token->label,
                'recipient_name' => $token->recipient_name,
                'expires_at' => $token->expires_at->toIso8601String(),
            ],
            'document_types' => $documentTypes,
            'existing_attachments' => $attachments,
            'payload_schema_fields' => $payloadSchemaFields,
            'config' => [
                'max_file_size' => config('settlement-envelope.max_file_size', 10 * 1024 * 1024),
                'allowed_mime_types' => config('settlement-envelope.allowed_mime_types', [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                ]),
            ],
        ]);
    }

    /**
     * Convert JSON pointer to field name
     * e.g., "/borrower/full_name" -> "borrower.full_name"
     */
    private function pointerToFieldName(string $pointer): string
    {
        $pointer = ltrim($pointer, '/');

        return str_replace('/', '.', $pointer);
    }

    /**
     * Verify password for contribution access
     */
    public function verifyPassword(Request $request)
    {
        $request->validate([
            'voucher' => 'required|string',
            'token' => 'required|uuid',
            'password' => 'required|string',
        ]);

        $tokenUuid = $request->token;
        $token = EnvelopeContributionToken::byToken($tokenUuid)->first();

        if (! $token || ! $token->isValid()) {
            return back()->withErrors(['password' => 'Invalid or expired link']);
        }

        if (! $token->verifyPassword($request->password)) {
            return back()->withErrors(['password' => 'Incorrect password']);
        }

        // Store verification in session
        session(["contribute_verified_{$token->id}" => true]);

        // Redirect back to show page (will now pass password check)
        return redirect()->route('contribute.show', [
            'voucher' => $request->voucher,
            'token' => $tokenUuid,
            'signature' => $request->signature,
            'expires' => $request->expires,
        ]);
    }

    /**
     * Upload document via contribution link
     */
    public function upload(Request $request)
    {
        $request->validate([
            'token' => 'required|uuid',
            'doc_type' => 'required|string',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $token = EnvelopeContributionToken::byToken($request->token)->first();

        if (! $token || ! $token->isValid()) {
            return response()->json(['error' => 'Invalid or expired contribution link'], 403);
        }

        // Verify session if password protected
        $sessionKey = "contribute_verified_{$token->id}";
        if ($token->requiresPassword() && ! session($sessionKey)) {
            return response()->json(['error' => 'Password verification required'], 403);
        }

        $envelope = $token->envelope;

        // Upload using EnvelopeService
        try {
            $attachment = $this->envelopeService->uploadAttachment(
                envelope: $envelope,
                docType: $request->doc_type,
                file: $request->file('file'),
                actor: null, // No authenticated user
                metadata: ['contributor_role' => 'external_contributor']
            );

            // Log external contribution with token details
            EnvelopeAuditLog::log(
                $envelope,
                EnvelopeAuditLog::ACTION_EXTERNAL_CONTRIBUTION,
                actor: $token, // Use token as actor for tracking
                actorRole: 'contribution_token',
                after: [
                    'attachment_id' => $attachment->id,
                    'doc_type' => $request->doc_type,
                    'filename' => $attachment->original_filename,
                ],
                metadata: [
                    'token_id' => $token->id,
                    'token_uuid' => $token->token,
                    'token_label' => $token->label,
                    'recipient_name' => $token->recipient_name,
                    'recipient_email' => $token->recipient_email,
                    'recipient_mobile' => $token->recipient_mobile,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            Log::info('[Contribute] Document uploaded via contribution token', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'doc_type' => $request->doc_type,
                'attachment_id' => $attachment->id,
            ]);

            // Handle review_status which may be string or enum depending on model cast
            $reviewStatus = is_string($attachment->review_status)
                ? $attachment->review_status
                : $attachment->review_status->value;

            return response()->json([
                'success' => true,
                'attachment' => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->original_filename,
                    'doc_type' => $attachment->doc_type,
                    'review_status' => $reviewStatus,
                    'url' => Storage::disk($attachment->disk ?? 'public')->url($attachment->file_path),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Contribute] Upload failed', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Upload failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Update envelope payload via contribution link
     *
     * NOTE: This bypasses schema validation since external contributors
     * may only provide partial data. The full payload validation should
     * happen when the envelope owner finalizes the envelope.
     */
    public function updatePayload(Request $request)
    {
        $request->validate([
            'token' => 'required|uuid',
            'payload' => 'required|array',
        ]);

        $token = EnvelopeContributionToken::byToken($request->token)->first();

        if (! $token || ! $token->isValid()) {
            return response()->json(['error' => 'Invalid or expired contribution link'], 403);
        }

        // Verify session if password protected
        $sessionKey = "contribute_verified_{$token->id}";
        if ($token->requiresPassword() && ! session($sessionKey)) {
            return response()->json(['error' => 'Password verification required'], 403);
        }

        $envelope = $token->envelope;

        if (! $envelope->canEdit()) {
            return response()->json(['error' => 'Envelope is not editable'], 403);
        }

        try {
            // Merge patch with existing payload (bypass schema validation for external contributors)
            $oldPayload = $envelope->payload ?? [];
            $newPayload = array_replace_recursive($oldPayload, $request->payload);

            // Update envelope directly without schema validation
            $envelope->update([
                'payload' => $newPayload,
                'payload_version' => $envelope->payload_version + 1,
            ]);

            // Update payload field checklist items and recompute gates
            // This is normally done by EnvelopeService::updatePayload() but we bypass it for external contributors
            $this->envelopeService->refreshChecklistAndGates($envelope);

            // Log external contribution
            EnvelopeAuditLog::log(
                $envelope,
                EnvelopeAuditLog::ACTION_EXTERNAL_CONTRIBUTION,
                actor: $token,
                actorRole: 'contribution_token',
                before: $oldPayload,
                after: $newPayload,
                metadata: [
                    'token_id' => $token->id,
                    'token_uuid' => $token->token,
                    'token_label' => $token->label,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            Log::info('[Contribute] Payload updated via contribution token', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
            ]);

            return response()->json([
                'success' => true,
                'payload' => $envelope->fresh()->payload,
            ]);
        } catch (\LBHurtado\SettlementEnvelope\Exceptions\PayloadValidationException $e) {
            // Schema validation failed - provide detailed error
            Log::warning('[Contribute] Payload validation failed', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'errors' => $e->getErrors(),
            ]);

            return response()->json([
                'error' => 'Payload validation failed',
                'details' => $e->getErrors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('[Contribute] Payload update failed', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Update failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Delete a pending attachment via contribution link
     */
    public function delete(Request $request)
    {
        $request->validate([
            'token' => 'required|uuid',
            'attachment_id' => 'required|integer',
        ]);

        $token = EnvelopeContributionToken::byToken($request->token)->first();

        if (! $token || ! $token->isValid()) {
            return response()->json(['error' => 'Invalid or expired contribution link'], 403);
        }

        // Verify session if password protected
        $sessionKey = "contribute_verified_{$token->id}";
        if ($token->requiresPassword() && ! session($sessionKey)) {
            return response()->json(['error' => 'Password verification required'], 403);
        }

        $envelope = $token->envelope;

        // Find the attachment
        $attachment = $envelope->attachments()->find($request->attachment_id);

        if (! $attachment) {
            return response()->json(['error' => 'Attachment not found'], 404);
        }

        // Only allow deleting pending attachments
        $reviewStatus = is_string($attachment->review_status)
            ? $attachment->review_status
            : $attachment->review_status->value;

        if ($reviewStatus !== 'pending') {
            return response()->json(['error' => 'Only pending attachments can be deleted'], 403);
        }

        try {
            // Log deletion before removing
            EnvelopeAuditLog::log(
                $envelope,
                'attachment_deleted',
                actor: $token,
                actorRole: 'contribution_token',
                before: [
                    'attachment_id' => $attachment->id,
                    'doc_type' => $attachment->doc_type,
                    'filename' => $attachment->original_filename,
                ],
                metadata: [
                    'token_id' => $token->id,
                    'token_uuid' => $token->token,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            // Delete the file from storage
            if ($attachment->file_path) {
                Storage::disk($attachment->disk ?? 'public')->delete($attachment->file_path);
            }

            // Delete the attachment record
            $attachment->delete();

            Log::info('[Contribute] Attachment deleted via contribution token', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'attachment_id' => $request->attachment_id,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('[Contribute] Delete failed', [
                'envelope_id' => $envelope->id,
                'token_id' => $token->id,
                'attachment_id' => $request->attachment_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Delete failed: '.$e->getMessage()], 500);
        }
    }
}
