<?php

declare(strict_types=1);

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Transaction History Controller
 *
 * Renders the transaction history page.
 * Data is fetched via API endpoints.
 */
class TransactionController extends Controller
{
    /**
     * Display the transaction history page.
     */
    public function index(): Response
    {
        return Inertia::render('transactions/Index');
    }

    /**
     * Export transactions to CSV.
     */
    public function export(Request $request)
    {
        $query = Voucher::query()
            ->where('owner_type', get_class($request->user()))
            ->where('owner_id', $request->user()->id)
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Apply filters if provided
        if ($request->has('date_from')) {
            $query->whereDate('redeemed_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('redeemed_at', '<=', $request->date_to);
        }
        if ($request->has('status')) {
            // Add status filtering if needed
        }

        $transactions = $query->get();

        // Generate CSV
        $filename = 'transactions_'.now()->format('Y-m-d_His').'.csv';
        $handle = fopen('php://temp', 'r+');

        // CSV Headers
        fputcsv($handle, [
            'Voucher Code',
            'Amount',
            'Currency',
            'Status',
            'Redeemed At',
            'Created At',
        ]);

        // CSV Data
        foreach ($transactions as $transaction) {
            fputcsv($handle, [
                $transaction->code,
                $transaction->instructions->cash->amount ?? 0,
                $transaction->instructions->cash->currency ?? 'PHP',
                $transaction->status ?? 'redeemed',
                $transaction->redeemed_at?->toDateTimeString(),
                $transaction->created_at->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return ResponseFacade::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
