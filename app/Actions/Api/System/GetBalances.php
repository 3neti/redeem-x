<?php

declare(strict_types=1);

namespace App\Actions\Api\System;

use App\Http\Responses\ApiResponse;
use App\Models\InstructionItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
            ->map(fn($item) => [
                'index' => $item->index,
                'name' => $item->name,
                'balance' => $item->balanceFloatNum,
                'currency' => $item->currency,
                'wallet_id' => $item->wallet->id,
            ]);
        
        return ApiResponse::success([
            'system' => [
                'email' => $systemEmail,
                'balance' => $systemUser?->balanceFloatNum ?? 0,
                'currency' => 'PHP',
            ],
            'products' => $instructionItems,
            'totals' => [
                'system' => $systemUser?->balanceFloatNum ?? 0,
                'products' => $instructionItems->sum('balance'),
                'combined' => ($systemUser?->balanceFloatNum ?? 0) + $instructionItems->sum('balance'),
            ],
        ]);
    }
}
