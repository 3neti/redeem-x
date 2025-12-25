<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\UserPreferencesSettings;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update user preferences via API.
 *
 * Endpoint: PATCH /api/v1/settings/preferences
 */
class UpdatePreferences
{
    use AsAction;

    public function asController(ActionRequest $request, UserPreferencesSettings $settings): JsonResponse
    {
        $validated = $request->validated();

        // Update user preferences
        if (isset($validated['notifications'])) {
            $settings->notifications = $validated['notifications'];
        }
        if (isset($validated['timezone'])) {
            $settings->timezone = $validated['timezone'];
        }
        if (isset($validated['language'])) {
            $settings->language = $validated['language'];
        }
        if (isset($validated['currency'])) {
            $settings->currency = $validated['currency'];
        }
        if (isset($validated['date_format'])) {
            $settings->date_format = $validated['date_format'];
        }

        $settings->save();

        return ApiResponse::success([
            'message' => 'Preferences updated successfully.',
            'preferences' => [
                'notifications' => $settings->notifications,
                'timezone' => $settings->timezone,
                'language' => $settings->language,
                'currency' => $settings->currency,
                'date_format' => $settings->date_format,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'notifications' => ['sometimes', 'array'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.sms' => ['sometimes', 'boolean'],
            'notifications.push' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'language' => ['sometimes', 'string', 'in:en,tl,fil'],
            'currency' => ['sometimes', 'string', 'in:PHP,USD,EUR'],
            'date_format' => ['sometimes', 'string', 'in:Y-m-d,m/d/Y,d/m/Y'],
        ];
    }
}
