<?php

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAuditLog;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateContributionLink
{
    use AsAction;

    public function handle(
        Voucher $voucher,
        array $options = []
    ): EnvelopeContributionToken {
        $envelope = $voucher->envelope;

        if (! $envelope) {
            throw new \InvalidArgumentException('Voucher does not have a settlement envelope');
        }

        // Create contribution token
        $token = EnvelopeContributionToken::create([
            'envelope_id' => $envelope->id,
            'label' => $options['label'] ?? null,
            'recipient_name' => $options['recipient_name'] ?? null,
            'recipient_email' => $options['recipient_email'] ?? null,
            'recipient_mobile' => $options['recipient_mobile'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'password' => $options['password'] ?? null, // Auto-hashed via cast
            'created_by' => auth()->id(),
            'expires_at' => now()->addDays($options['expires_days'] ?? 7),
        ]);

        // Log token creation
        EnvelopeAuditLog::log(
            $envelope,
            EnvelopeAuditLog::ACTION_CONTRIBUTION_TOKEN_CREATED,
            actor: auth()->user(),
            actorRole: 'owner',
            after: [
                'token_id' => $token->id,
                'token_uuid' => $token->token,
                'label' => $token->label,
                'recipient_name' => $token->recipient_name,
                'expires_at' => $token->expires_at->toIso8601String(),
                'password_protected' => $token->requiresPassword(),
            ],
        );

        Log::info('[ContributionLink] Token created', [
            'voucher_code' => $voucher->code,
            'envelope_id' => $envelope->id,
            'token_id' => $token->id,
            'created_by' => auth()->id(),
        ]);

        return $token;
    }

    public function asController(Request $request, Voucher $voucher): JsonResponse
    {
        // Validate ownership
        if ($voucher->owner_id !== auth()->id()) {
            return response()->json([
                'error' => 'You are not the owner of this voucher',
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'label' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'recipient_mobile' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
            'password' => 'nullable|string|min:4',
            'expires_days' => 'nullable|integer|min:1|max:90',
        ]);

        try {
            $token = $this->handle($voucher, $validated);

            return response()->json([
                'success' => true,
                'token' => [
                    'id' => $token->id,
                    'uuid' => $token->token,
                    'label' => $token->label,
                    'recipient_name' => $token->recipient_name,
                    'recipient_email' => $token->recipient_email,
                    'recipient_mobile' => $token->recipient_mobile,
                    'password_protected' => $token->requiresPassword(),
                    'expires_at' => $token->expires_at->toIso8601String(),
                    'url' => $token->generateUrl($voucher->code),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('[ContributionLink] Failed to create token', [
                'voucher_code' => $voucher->code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
