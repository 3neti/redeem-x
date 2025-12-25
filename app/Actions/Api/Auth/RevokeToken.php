<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Revoke a specific API token.
 * 
 * Endpoint: DELETE /api/v1/auth/tokens/{tokenId}
 */
class RevokeToken
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request, string $tokenId): JsonResponse
    {
        $token = $request->user()
            ->tokens()
            ->findOrFail($tokenId);

        $tokenName = $token->name;
        $token->delete();

        return ApiResponse::success([
            'message' => "Token '{$tokenName}' has been revoked successfully.",
        ]);
    }
}
