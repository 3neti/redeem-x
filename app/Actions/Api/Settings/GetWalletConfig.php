<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\WalletConfigSettings;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get user wallet configuration via API.
 *
 * Endpoint: GET /api/v1/settings/wallet
 */
class GetWalletConfig
{
    use AsAction;

    public function asController(ActionRequest $request, WalletConfigSettings $settings): JsonResponse
    {
        return ApiResponse::success([
            'wallet' => [
                'default_settlement_rail' => $settings->default_settlement_rail,
                'default_fee_strategy' => $settings->default_fee_strategy,
                'auto_disburse' => $settings->auto_disburse,
                'low_balance_threshold' => $settings->low_balance_threshold,
                'low_balance_notifications' => $settings->low_balance_notifications,
            ],
        ]);
    }
}
