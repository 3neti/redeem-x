<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\VoucherSettings;
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

    public function asController(ActionRequest $request, VoucherSettings $settings): JsonResponse
    {
        return ApiResponse::success([
            'preferences' => [
                'default_amount' => $settings->default_amount,
                'default_expiry_days' => $settings->default_expiry_days,
                'default_rider_url' => $settings->default_rider_url,
                'default_success_message' => $settings->default_success_message,
            ],
        ]);
    }
}
