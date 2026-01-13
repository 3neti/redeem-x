<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * Export Transactions to CSV
 *
 * Download transaction history as a CSV file for accounting, auditing, and reporting.
 * 
 * Generates a comprehensive CSV export of all redeemed vouchers with complete disbursement details.
 * Perfect for importing into accounting software, Excel analysis, or external reporting systems.
 * 
 * **CSV Columns Include:**
 * - Voucher Code - Unique voucher identifier
 * - Amount & Currency - Disbursement amount in PHP
 * - Bank - Full bank name (resolved from code)
 * - Account - Disbursement destination account
 * - Rail - Settlement rail (INSTAPAY/PESONET)
 * - Status - Disbursement status
 * - Operation ID - Gateway transaction reference
 * - Transaction UUID - Internal transaction identifier
 * - Timestamps - Redeemed, disbursed, created dates
 * 
 * **File Format:**
 * - Standard CSV format (RFC 4180 compliant)
 * - UTF-8 encoding
 * - Filename: `transactions-YYYY-MM-DD-HHMMSS.csv`
 * 
 * **Use Cases:**
 * - Monthly financial reconciliation
 * - Tax reporting and compliance
 * - Importing into QuickBooks, Xero, etc.
 * - Data analysis in Excel/Google Sheets
 *
 * @group Transactions
 * @authenticated
 */
#[Group('Transactions')]
class ExportTransactions
{
    /**
     * Export transactions to CSV file.
     *
     * Generate and download a CSV file containing filtered transaction data with all disbursement details.
     */
    #[QueryParameter('date_from', description: '*optional* - Export transactions from this date (YYYY-MM-DD format). Filters by redemption date.', type: 'string', example: '2024-01-01')]
    #[QueryParameter('date_to', description: '*optional* - Export transactions until this date (YYYY-MM-DD format). Must be after or equal to date_from.', type: 'string', example: '2024-12-31')]
    #[QueryParameter('search', description: '*optional* - Filter by voucher code. Supports partial matching.', type: 'string', example: 'PROMO')]
    #[QueryParameter('bank', description: '*optional* - Filter by disbursement bank code (e.g., GXCHPHM2XXX for GCash).', type: 'string', example: 'GXCHPHM2XXX')]
    #[QueryParameter('rail', description: '*optional* - Filter by settlement rail: INSTAPAY or PESONET.', type: 'string', example: 'INSTAPAY')]
    #[QueryParameter('status', description: '*optional* - Filter by disbursement status (e.g., "success", "pending", "failed").', type: 'string', example: 'success')]
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:50'],
            'rail' => ['nullable', 'string', 'in:INSTAPAY,PESONET'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $bank = $request->input('bank');
        $rail = $request->input('rail');
        $status = $request->input('status');

        $query = $user->vouchers()
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
}
