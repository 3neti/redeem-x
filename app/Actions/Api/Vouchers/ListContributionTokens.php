<?php

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class ListContributionTokens
{
    use AsAction;

    public function asController(Request $request, Voucher $voucher): JsonResponse
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

        $tokens = $envelope->contributionTokens()
            ->with('creator:id,name,email')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'tokens' => $tokens->map(fn ($token) => [
                'id' => $token->id,
                'uuid' => $token->token,
                'label' => $token->label,
                'recipient_name' => $token->recipient_name,
                'recipient_email' => $token->recipient_email,
                'recipient_mobile' => $token->recipient_mobile,
                'password_protected' => $token->requiresPassword(),
                'is_valid' => $token->isValid(),
                'is_expired' => $token->isExpired(),
                'is_revoked' => $token->isRevoked(),
                'expires_at' => $token->expires_at->toIso8601String(),
                'revoked_at' => $token->revoked_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'use_count' => $token->use_count,
                'created_by' => $token->creator?->name ?? 'Unknown',
                'created_at' => $token->created_at->toIso8601String(),
                'url' => $token->isValid() ? $token->generateUrl($voucher->code) : null,
            ]),
        ]);
    }
}
