<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update user profile via API.
 *
 * Endpoint: PATCH /api/v1/settings/profile
 */
class UpdateProfile
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->update([
            'name' => $validated['name'],
        ]);

        return ApiResponse::success([
            'message' => 'Profile updated successfully.',
            'profile' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar' => $request->user()->avatar,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
