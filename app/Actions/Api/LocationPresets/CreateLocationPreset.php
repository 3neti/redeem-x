<?php

declare(strict_types=1);

namespace App\Actions\Api\LocationPresets;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new location preset for the authenticated user.
 *
 * Endpoint: POST /api/v1/location-presets
 */
class CreateLocationPreset
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $preset = $request->user()->addLocationPreset(
            name: $validated['name'],
            coordinates: $validated['coordinates'],
            radius: $validated['radius'] ?? 0,
        );

        return ApiResponse::created([
            'message' => 'Location preset created.',
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
            'name' => ['required', 'string', 'max:100'],
            'coordinates' => ['required', 'array', 'min:3'],
            'coordinates.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:0', 'max:50000'],
        ];
    }
}
