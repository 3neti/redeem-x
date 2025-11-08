<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\DataCollection;

/**
 * List user transactions via API.
 *
 * Endpoint: GET /api/v1/transactions
 */
class ListTransactions
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), 100);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = Voucher::query()
            ->with(['owner'])
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Filter by date range
        if ($dateFrom) {
            $query->whereDate('redeemed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('redeemed_at', '<=', $dateTo);
        }

        // Search by code
        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }

        $transactions = $query->paginate($perPage);

        // Transform to VoucherData DTOs using DataCollection
        $transactionData = new DataCollection(VoucherData::class, $transactions->items());

        return ApiResponse::success([
            'data' => $transactionData,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
