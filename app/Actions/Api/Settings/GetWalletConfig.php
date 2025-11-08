<?php

declare(strict_types=1);

namespace App\Actions\Api\Settings;

use App\Http\Responses\ApiResponse;
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

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet ?? $user->createWallet(['name' => 'Default Wallet']);

        // Get recent transactions
        $recentTransactions = $wallet->transactions()
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount / 100, // Convert to major units
                    'confirmed' => $transaction->confirmed,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ];
            });

        return ApiResponse::success([
            'wallet' => [
                'balance' => $wallet->balanceFloat,
                'currency' => 'PHP',
            ],
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
