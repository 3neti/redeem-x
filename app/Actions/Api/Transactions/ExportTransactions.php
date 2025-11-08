<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export transactions as CSV via API.
 *
 * Endpoint: GET /api/v1/transactions/export
 */
class ExportTransactions
{
    use AsAction;

    public function asController(ActionRequest $request): StreamedResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = Voucher::query()
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at');

        // Apply filters
        if ($dateFrom) {
            $query->whereDate('redeemed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('redeemed_at', '<=', $dateTo);
        }
        if ($search) {
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

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
