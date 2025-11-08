<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\VoucherSettings;
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

    public function asController(ActionRequest $request, VoucherSettings $settings): JsonResponse
    {
        $validated = $request->validated();

        $settings->default_amount = (int) $validated['default_amount'];
        $settings->default_expiry_days = $validated['default_expiry_days'];
        $settings->default_rider_url = $validated['default_rider_url'] ?? config('app.url');
        $settings->default_success_message = $validated['default_success_message'] ?? 'Thank you for redeeming your voucher! The cash will be transferred shortly.';
        
        $settings->save();

        return ApiResponse::success([
            'message' => 'Preferences updated successfully.',
            'preferences' => [
                'default_amount' => $settings->default_amount,
                'default_expiry_days' => $settings->default_expiry_days,
                'default_rider_url' => $settings->default_rider_url,
                'default_success_message' => $settings->default_success_message,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'default_amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'default_rider_url' => ['nullable', 'url', 'max:500'],
            'default_success_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
