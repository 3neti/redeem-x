<?php

declare(strict_types=1);

namespace App\Actions\Api\LocationPresets;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete a location preset.
 *
 * Users can only delete their own presets, not system defaults.
 *
 * Endpoint: DELETE /api/v1/location-presets/{id}
 */
class DeleteLocationPreset
{
    use AsAction;

    public function asController(ActionRequest $request, int $id): JsonResponse
    {
        $deleted = $request->user()->deleteLocationPreset($id);

        if (! $deleted) {
            return ApiResponse::notFound('Location preset not found.');
        }

        return ApiResponse::success([
            'message' => 'Location preset deleted.',
        ]);
    }
}
