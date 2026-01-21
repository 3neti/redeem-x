<?php

declare(strict_types=1);

namespace App\Actions\Api\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use Dedoc\Scramble\Attributes\Group;

/**
 * Get Voucher Payment History
 *
 * Retrieve all payment transactions for a settlement/payable voucher.
 * 
 * @group Vouchers
 */
#[Group('Vouchers')]
class GetPaymentHistory
{
    /**
     * Get payment history for a voucher
     */
    public function __invoke(Request $request, string $code): JsonResponse
    {
        // Find voucher with cash entity
        $voucher = Voucher::where('code', $code)->first();
        
        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        }
        
        // Get cash entity
        $cash = $voucher->cash;
        
        if (!$cash || !$cash->wallet) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No payment history found',
            ]);
        }
        
        // Get payment transactions (deposits with flow: 'pay', confirmed only)
        $transactions = $cash->wallet->transactions()
            ->where('type', 'deposit')
            ->whereJsonContains('meta->flow', 'pay')
            ->where('confirmed', true)  // Only show confirmed payments
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'amount' => $tx->amount, // Already in minor units
                    'created_at' => $tx->created_at->toIso8601String(),
                    'meta' => $tx->meta,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $transactions,
            'message' => 'Payment history retrieved successfully',
        ]);
    }
}
