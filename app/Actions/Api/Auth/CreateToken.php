<?php

declare(strict_types=1);

namespace App\Actions\Api\Auth;

use App\Data\Api\Auth\TokenData;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new API token for the authenticated user.
 *
 * Endpoint: POST /api/v1/auth/tokens
 */
class CreateToken
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Calculate expiration date
        $expiresAt = isset($validated['expires_in_days'])
            ? Carbon::now()->addDays($validated['expires_in_days'])
            : null;

        // Create token with specified abilities
        $newToken = $request->user()->createToken(
            name: $validated['name'],
            abilities: $validated['abilities'] ?? ['*'],
            expiresAt: $expiresAt
        );

        // Get the created token from database
        $token = $request->user()
            ->tokens()
            ->find($newToken->accessToken->id);

        $tokenData = TokenData::fromToken($token, $newToken->plainTextToken);

        return ApiResponse::created([
            'token' => $tokenData,
            'message' => 'API token created successfully. Store the plain text token securely.',
        ]);
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        $availableAbilities = array_column(
            ApiTokenController::availableAbilities(),
            'value'
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['required', 'string', 'in:'.implode(',', $availableAbilities).',*'],
            'expires_in_days' => ['nullable', 'integer', 'in:30,60,90,180,365'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Token name is required',
            'abilities.*.in' => 'Invalid token ability specified',
        ];
    }
}
