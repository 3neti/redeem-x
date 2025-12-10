<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
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
        $bank = $request->input('bank');
        $rail = $request->input('rail');
        $status = $request->input('status');

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
        if ($bank) {
            $query->whereJsonContains('metadata->disbursement->bank', $bank);
        }
        if ($rail) {
            $query->whereJsonContains('metadata->disbursement->rail', $rail);
        }
        if ($status) {
            $query->whereJsonContains('metadata->disbursement->status', $status);
        }

        $transactions = $query->get();
        $filename = 'transactions-' . now()->format('Y-m-d-His') . '.csv';

        $bankRegistry = app(BankRegistry::class);

        return response()->streamDownload(function () use ($transactions, $bankRegistry) {
            $handle = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($handle, [
                'Voucher Code',
                'Amount',
                'Currency',
                'Bank',
                'Account',
                'Rail',
                'Status',
                'Operation ID',
                'Transaction UUID',
                'Redeemed At',
                'Disbursed At',
                'Created At',
            ]);

            // CSV Rows
            foreach ($transactions as $transaction) {
                $amount = $transaction->instructions->cash->amount ?? 0;
                $currency = $transaction->instructions->cash->currency ?? 'PHP';
                $disbursement = $transaction->metadata['disbursement'] ?? null;

                // Get bank name from registry if disbursement exists
                $bankName = $disbursement 
                    ? $bankRegistry->getBankName($disbursement['bank'] ?? '')
                    : 'N/A';

                fputcsv($handle, [
                    $transaction->code,
                    $amount,
                    $currency,
                    $bankName,
                    $disbursement['account'] ?? 'N/A',
                    $disbursement['rail'] ?? 'N/A',
                    $disbursement['status'] ?? 'N/A',
                    $disbursement['operation_id'] ?? 'N/A',
                    $disbursement['transaction_uuid'] ?? 'N/A',
                    $transaction->redeemed_at?->toDateTimeString(),
                    $disbursement['disbursed_at'] ?? 'N/A',
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
            'bank' => ['nullable', 'string', 'max:50'],
            'rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
