<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Revoke all API tokens for the authenticated user.
 *
 * Endpoint: DELETE /api/v1/auth/tokens
 */
class RevokeAllTokens
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return ApiResponse::success([
            'message' => "All {$count} API tokens have been revoked successfully.",
            'revoked_count' => $count,
        ]);
    }
}
