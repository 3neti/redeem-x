<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Get transaction statistics via API.
 *
 * Endpoint: GET /api/v1/transactions/stats
 */
class GetTransactionStats
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Voucher::whereNotNull('redeemed_at');

        // Apply date filters
        if ($dateFrom) {
            $query->whereDate('redeemed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('redeemed_at', '<=', $dateTo);
        }

        $totalTransactions = $query->count();

        // Calculate total amount
        $vouchers = $query->get();
        $totalAmount = $vouchers->sum(function ($voucher) {
            return $voucher->instructions->cash->amount ?? 0;
        });

        // Today's transactions
        $todayTransactions = Voucher::whereNotNull('redeemed_at')
            ->whereDate('redeemed_at', today())
            ->count();

        // This month's transactions
        $monthTransactions = Voucher::whereNotNull('redeemed_at')
            ->whereMonth('redeemed_at', now()->month)
            ->whereYear('redeemed_at', now()->year)
            ->count();

        return ApiResponse::success([
            'stats' => [
                'total' => $totalTransactions,
                'total_amount' => $totalAmount,
                'today' => $todayTransactions,
                'this_month' => $monthTransactions,
                'currency' => 'PHP',
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
