<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Data\WalletTransactionData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

class ListWalletTransactions
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'in:all,deposit,withdraw'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        
        $user = $request->user();
        $perPage = min($request->integer('per_page', 20), 100);
        $type = $request->input('type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = $user->walletTransactions()
            ->where('confirmed', true)
            ->latest()
            ->orderBy('id', 'desc');

        // Type filter
        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        // Date range
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Search by metadata fields or UUID
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                  ->orWhere('meta->sender_name', 'like', "%{$search}%")
                  ->orWhere('meta->sender_identifier', 'like', "%{$search}%")
                  ->orWhere('meta->voucher_code', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate($perPage);

        // Transform to DTOs
        $transactionData = new DataCollection(
            WalletTransactionData::class,
            $transactions->items()
        );

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
        ]);
    }
}
