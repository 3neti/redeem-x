<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transaction History Controller
 * 
 * Handles viewing redemption history and exporting transaction data.
 */
class TransactionController extends Controller
{
    /**
     * Display a listing of transactions (redeemed vouchers).
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Voucher::query()
            ->with(['owner'])
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('redeemed_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('redeemed_at', '<=', $request->input('date_to'));
        }

        // Search by code
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%{$search}%");
        }

        // Paginate
        $transactions = $query->paginate(20)->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => VoucherData::collection($transactions),
            'filters' => [
                'search' => $request->input('search'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'stats' => $this->getTransactionStats($request),
        ]);
    }

    /**
     * Export transactions as CSV.
     *
     * @param  Request  $request
     * @return StreamedResponse
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Voucher::query()
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Apply same filters as index
        if ($request->filled('date_from')) {
            $query->whereDate('redeemed_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('redeemed_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%{$search}%");
        }

        $transactions = $query->get();

        $filename = 'transactions-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($handle, [
                'Voucher Code',
                'Amount',
                'Currency',
                'Redeemed At',
                'Created At',
            ]);

            // CSV Rows
            foreach ($transactions as $transaction) {
                $amount = $transaction->instructions->cash->amount ?? 0;
                $currency = $transaction->instructions->cash->currency ?? 'PHP';

                fputcsv($handle, [
                    $transaction->code,
                    $amount,
                    $currency,
                    $transaction->redeemed_at?->toDateTimeString(),
                    $transaction->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get transaction statistics.
     *
     * @param  Request  $request
     * @return array
     */
    protected function getTransactionStats(Request $request): array
    {
        $query = Voucher::whereNotNull('redeemed_at');

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->whereDate('redeemed_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('redeemed_at', '<=', $request->input('date_to'));
        }

        $totalTransactions = $query->count();

        // Calculate total amount (using DTO accessor)
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

        return [
            'total' => $totalTransactions,
            'total_amount' => $totalAmount,
            'today' => $todayTransactions,
            'this_month' => $monthTransactions,
        ];
    }
}
