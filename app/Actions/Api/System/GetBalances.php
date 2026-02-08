<?php

declare(strict_types=1);

namespace App\Actions\Api\System;

use App\Http\Responses\ApiResponse;
use App\Models\InstructionItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use LBHurtado\Cash\Models\Cash;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Get system and product wallet balances.
 *
 * Endpoint: GET /api/v1/system/balances
 */
class GetBalances
{
    use AsAction;

    /**
     * Handle API request.
     */
    public function asController(): JsonResponse
    {
        // Get system user
        $systemEmail = env('SYSTEM_USER_ID');
        $systemUser = User::where('email', $systemEmail)->first();

        // Get instruction items with wallets
        $instructionItems = InstructionItem::whereHas('wallet')
            ->with('wallet')
            ->get()
            ->map(fn ($item) => [
                'index' => $item->index,
                'name' => $item->name,
                'balance' => $item->balanceFloatNum,
                'currency' => $item->currency,
                'wallet_id' => $item->wallet->id,
            ]);

        // Get cash entities with wallets (escrow wallets)
        $cashWallets = Cash::whereHas('wallet')
            ->with('wallet')
            ->get()
            ->map(fn ($cash) => [
                'index' => 'cash.amount',
                'name' => 'Amount',
                'balance' => $cash->balanceFloatNum,
                'currency' => $cash->currency,
                'wallet_id' => $cash->wallet->id,
            ]);

        // Sum all cash wallets into a single product
        $cashProduct = null;
        if ($cashWallets->isNotEmpty()) {
            $cashProduct = [
                'index' => 'cash.amount',
                'name' => 'Amount',
                'balance' => $cashWallets->sum('balance'),
                'currency' => 'PHP',
                'wallet_id' => $cashWallets->first()['wallet_id'],
            ];
        }

        // Merge products
        $products = $instructionItems;
        if ($cashProduct) {
            $products = $products->prepend($cashProduct);
        }

        return ApiResponse::success([
            'system' => [
                'email' => $systemEmail,
                'balance' => $systemUser?->balanceFloatNum ?? 0,
                'currency' => 'PHP',
            ],
            'products' => $products,
            'totals' => [
                'system' => $systemUser?->balanceFloatNum ?? 0,
                'products' => $products->sum('balance'),
                'combined' => ($systemUser?->balanceFloatNum ?? 0) + $products->sum('balance'),
            ],
        ]);
    }
}
