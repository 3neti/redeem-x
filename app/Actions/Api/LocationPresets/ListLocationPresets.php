<?php

declare(strict_types=1);

namespace App\Actions\Api\LocationPresets;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List location presets for the authenticated user.
 *
 * Returns the user's own presets merged with system defaults.
 *
 * Endpoint: GET /api/v1/location-presets
 */
class ListLocationPresets
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $presets = $request->user()
            ->getLocationPresetsWithDefaults()
            ->map(fn ($preset) => [
                'id' => $preset->id,
                'name' => $preset->name,
                'coordinates' => $preset->coordinates,
                'radius' => $preset->radius,
                'is_default' => $preset->is_default,
                'centroid' => $preset->centroid(),
            ]);

        return ApiResponse::success($presets->values());
    }
}
