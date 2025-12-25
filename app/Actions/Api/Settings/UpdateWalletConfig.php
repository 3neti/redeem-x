<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
use App\Settings\WalletConfigSettings;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWalletConfig
{
    use AsAction;

    public function asController(\Lorisleiva\Actions\ActionRequest $request, WalletConfigSettings $settings): JsonResponse
    {
        $validated = $request->validated();

        // Update wallet settings
        if (isset($validated['default_settlement_rail'])) {
            $settings->default_settlement_rail = $validated['default_settlement_rail'];
        }
        if (isset($validated['default_fee_strategy'])) {
            $settings->default_fee_strategy = $validated['default_fee_strategy'];
        }
        if (isset($validated['auto_disburse'])) {
            $settings->auto_disburse = $validated['auto_disburse'];
        }
        if (isset($validated['low_balance_threshold'])) {
            $settings->low_balance_threshold = $validated['low_balance_threshold'];
        }
        if (isset($validated['low_balance_notifications'])) {
            $settings->low_balance_notifications = $validated['low_balance_notifications'];
        }

        $settings->save();

        return ApiResponse::success([
            'message' => 'Wallet configuration updated successfully.',
            'wallet' => [
                'default_settlement_rail' => $settings->default_settlement_rail,
                'default_fee_strategy' => $settings->default_fee_strategy,
                'auto_disburse' => $settings->auto_disburse,
                'low_balance_threshold' => $settings->low_balance_threshold,
                'low_balance_notifications' => $settings->low_balance_notifications,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'default_settlement_rail' => ['sometimes', 'string', 'in:auto,instapay,pesonet'],
            'default_fee_strategy' => ['sometimes', 'string', 'in:absorb,include,add'],
            'auto_disburse' => ['sometimes', 'boolean'],
            'low_balance_threshold' => ['sometimes', 'numeric', 'min:0'],
            'low_balance_notifications' => ['sometimes', 'boolean'],
        ];
    }
}
