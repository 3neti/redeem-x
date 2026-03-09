<?php

declare(strict_types=1);

namespace App\Actions\Api\LocationPresets;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update an existing location preset.
 *
 * Users can only update their own presets, not system defaults.
 *
 * Endpoint: PUT /api/v1/location-presets/{id}
 */
class UpdateLocationPreset
{
    use AsAction;

    public function asController(ActionRequest $request, int $id): JsonResponse
    {
        $preset = $request->user()
            ->locationPresets()
            ->where('is_default', false)
            ->find($id);

        if (! $preset) {
            return ApiResponse::notFound('Location preset not found.');
        }

        $preset->update($request->validated());

        return ApiResponse::success([
            'message' => 'Location preset updated.',
            'preset' => [
                'id' => $preset->id,
                'name' => $preset->name,
                'coordinates' => $preset->coordinates,
                'radius' => $preset->radius,
                'is_default' => $preset->is_default,
                'centroid' => $preset->centroid(),
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'coordinates' => ['sometimes', 'array', 'min:3'],
            'coordinates.*.lat' => ['required_with:coordinates', 'numeric', 'between:-90,90'],
            'coordinates.*.lng' => ['required_with:coordinates', 'numeric', 'between:-180,180'],
            'radius' => ['sometimes', 'integer', 'min:0', 'max:50000'],
        ];
    }
}
