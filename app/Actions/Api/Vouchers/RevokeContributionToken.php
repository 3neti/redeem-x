<?php

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LBHurtado\SettlementEnvelope\Models\EnvelopeAuditLog;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class RevokeContributionToken
{
    use AsAction;

    public function asController(Request $request, Voucher $voucher, EnvelopeContributionToken $token): JsonResponse
    {
        // Validate ownership
        if ($voucher->owner_id !== auth()->id()) {
            return response()->json([
                'error' => 'You are not the owner of this voucher',
            ], 403);
        }

        $envelope = $voucher->envelope;
        if (! $envelope) {
            return response()->json([
                'error' => 'Voucher does not have a settlement envelope',
            ], 400);
        }

        // Verify token belongs to this envelope
        if ($token->envelope_id !== $envelope->id) {
            return response()->json([
                'error' => 'Token does not belong to this voucher',
            ], 403);
        }

        if ($token->isRevoked()) {
            return response()->json([
                'error' => 'Token is already revoked',
            ], 400);
        }

        // Revoke the token
        $token->revoke();

        // Log revocation
        EnvelopeAuditLog::log(
            $envelope,
            EnvelopeAuditLog::ACTION_CONTRIBUTION_TOKEN_REVOKED,
            actor: auth()->user(),
            actorRole: 'owner',
            after: [
                'token_id' => $token->id,
                'token_uuid' => $token->token,
                'label' => $token->label,
                'recipient_name' => $token->recipient_name,
            ],
        );

        Log::info('[ContributionLink] Token revoked', [
            'voucher_code' => $voucher->code,
            'envelope_id' => $envelope->id,
            'token_id' => $token->id,
            'revoked_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contribution link has been revoked',
        ]);
    }
}
