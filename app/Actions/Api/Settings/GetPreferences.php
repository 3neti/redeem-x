<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\UserPreferencesSettings;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get user preferences via API.
 *
 * Endpoint: GET /api/v1/settings/preferences
 */
class GetPreferences
{
    use AsAction;

    public function asController(ActionRequest $request, UserPreferencesSettings $settings): JsonResponse
    {
        return ApiResponse::success([
            'preferences' => [
                'notifications' => $settings->notifications,
                'timezone' => $settings->timezone,
                'language' => $settings->language,
                'currency' => $settings->currency,
                'date_format' => $settings->date_format,
            ],
        ]);
    }
}
