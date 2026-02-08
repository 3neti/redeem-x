<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Data\Api\Auth\TokenData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * List all API tokens for the authenticated user.
 *
 * Endpoint: GET /api/v1/auth/tokens
 */
class ListTokens
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get();

        $tokenData = new DataCollection(
            TokenData::class,
            $tokens->map(fn ($token) => TokenData::fromToken($token))
        );

        return ApiResponse::success([
            'tokens' => $tokenData,
            'total' => $tokens->count(),
        ]);
    }
}
