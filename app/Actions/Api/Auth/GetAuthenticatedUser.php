<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Data\Api\Auth\UserData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get authenticated user information with current token abilities.
 *
 * Endpoint: GET /api/v1/auth/me
 */
class GetAuthenticatedUser
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Get current token abilities if authenticated via token
        $tokenAbilities = $user->currentAccessToken()?->abilities ?? null;

        $userData = UserData::fromUser($user, $tokenAbilities);

        return ApiResponse::success([
            'user' => $userData,
        ]);
    }
}
