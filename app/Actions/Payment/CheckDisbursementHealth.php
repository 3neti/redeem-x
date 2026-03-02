<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check the bank-side status of a disbursement for a given voucher.
 *
 * Looks up DisbursementAttempt records and queries the payment gateway
 * for real-time bank status. Useful for diagnosing stuck or failed
 * disbursements.
 */
class CheckDisbursementHealth
{
    use AsAction;

    public string $commandSignature = 'disbursement:check
                            {code : The voucher code to check}
                            {--json : Output as JSON}';

    public string $commandDescription = 'Check the bank-side status of a disbursement for a voucher';

    /**
     * Core logic — check disbursement health for a voucher code.
     *
     * @return array{voucher: array, attempts: array, bank_status: ?array, interpretation: string}
     */
    public function handle(string $code): array
    {
        $voucher = Voucher::where('code', $code)->first();

        $result = [
            'voucher' => null,
            'attempts' => [],
            'bank_status' => null,
            'interpretation' => '',
        ];

        // Check voucher exists
        if (! $voucher) {
            $result['interpretation'] = "Voucher not found: {$code}";

            return $result;
        }

        $result['voucher'] = [
            'code' => $voucher->code,
            'status' => $voucher->redeemed_at ? 'redeemed' : 'unredeemed',
            'redeemed_at' => $voucher->redeemed_at?->toDateTimeString(),
            'disbursement_metadata' => $voucher->metadata['disbursement'] ?? null,
        ];

        // Find attempt records
        $attempts = DisbursementAttempt::byVoucherCode($code)
            ->orderBy('attempted_at', 'desc')
            ->get();

        if ($attempts->isEmpty()) {
            $result['interpretation'] = 'No disbursement attempts found for this voucher. '
                .'If the redemption rolled back due to a bank error, the attempt record was also lost.';

            return $result;
        }

        $result['attempts'] = $attempts->map(fn ($a) => [
            'id' => $a->id,
            'status' => $a->status,
            'reference_id' => $a->reference_id,
            'gateway_transaction_id' => $a->gateway_transaction_id,
            'amount' => $a->amount,
            'bank_code' => $a->bank_code,
            'settlement_rail' => $a->settlement_rail,
            'error_type' => $a->error_type,
            'error_message' => $a->error_message,
            'attempted_at' => $a->attempted_at?->toDateTimeString(),
            'completed_at' => $a->completed_at?->toDateTimeString(),
            'needs_reconciliation' => $a->needsReconciliation(),
        ])->all();

        // Try to query bank for the latest attempt
        $latest = $attempts->first();

        if ($latest->hasGatewayTransactionId()) {
            try {
                $gateway = app(PaymentGatewayInterface::class);
                $bankResult = $gateway->checkDisbursementStatus($latest->gateway_transaction_id);
                $result['bank_status'] = $bankResult;

                $result['interpretation'] = $this->interpret($latest, $bankResult);
            } catch (\Throwable $e) {
                Log::warning('[CheckDisbursementHealth] Failed to query bank', [
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
                $result['bank_status'] = ['status' => 'error', 'error' => $e->getMessage()];
                $result['interpretation'] = 'Could not reach the bank to check status. Error: '.$e->getMessage();
            }
        } else {
            $result['interpretation'] = 'No bank transaction ID found. '
                .'The bank may not have received this request (timeout before bank responded). '
                .'The transfer likely did NOT process, but verify with your bank dashboard to be sure.';
        }

        return $result;
    }

    /**
     * Interpret the bank status for the operator.
     */
    private function interpret(DisbursementAttempt $attempt, array $bankResult): string
    {
        $status = $bankResult['status'] ?? 'unknown';

        if ($status === 'error') {
            return 'Could not reach the bank. Error: '.($bankResult['error'] ?? 'unknown').'. '
                .'Try again later or check the bank dashboard directly.';
        }

        return match ($status) {
            'pending' => 'Bank received the request and it is still PENDING. '
                .'The transfer has not been sent to the recipient yet. '
                .'It may still complete or be rejected.',

            'processing' => 'Bank has FORWARDED the transfer to the clearing house (INSTAPAY/PESONET). '
                .'The money is in transit to the recipient. '
                .'It should complete shortly.',

            'completed' => 'Bank confirms the transfer is SETTLED — money has been delivered to the recipient. '
                .'If local records show "pending", run: php artisan disbursement:update-status --voucher='.$attempt->voucher_code,

            'failed' => 'Bank confirms the transfer was REJECTED. '
                .'The money was NOT delivered. '
                .'It is safe to retry the disbursement.',

            'cancelled' => 'The transfer was CANCELLED. Money was not sent.',

            default => "Unknown bank status: {$status}. Check the bank dashboard manually.",
        };
    }

    /**
     * Artisan command output.
     */
    public function asCommand(Command $command): int
    {
        $code = strtoupper($command->argument('code'));
        $asJson = $command->option('json');

        $result = $this->handle($code);

        if ($asJson) {
            $command->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        // Human-readable output
        $command->newLine();

        // Voucher info
        if (! $result['voucher']) {
            $command->error($result['interpretation']);

            return Command::FAILURE;
        }

        $v = $result['voucher'];
        $command->info("Voucher: {$v['code']}");
        $command->line("  Status: {$v['status']}");
        $command->line('  Redeemed: '.($v['redeemed_at'] ?? 'No'));

        if ($v['disbursement_metadata']) {
            $d = $v['disbursement_metadata'];
            $command->line('  Disbursement status (metadata): '.($d['status'] ?? 'N/A'));
            $command->line('  Transaction ID: '.($d['transaction_id'] ?? 'N/A'));
        }

        // Attempts
        $command->newLine();
        $attemptCount = count($result['attempts']);
        $command->info("Disbursement Attempts: {$attemptCount}");

        foreach ($result['attempts'] as $i => $a) {
            $num = $i + 1;
            $command->newLine();
            $command->line("  [{$num}] Status: {$a['status']}".($a['needs_reconciliation'] ? ' ⚠️  NEEDS RECONCILIATION' : ''));
            $command->line("      Reference: {$a['reference_id']}");
            $command->line('      Gateway TX ID: '.($a['gateway_transaction_id'] ?? 'NONE'));
            $command->line("      Amount: {$a['amount']}");
            $command->line('      Bank: '.($a['bank_code'] ?? 'N/A').' via '.($a['settlement_rail'] ?? 'N/A'));
            $command->line("      Attempted: {$a['attempted_at']}");

            if ($a['error_type']) {
                $command->line("      Error: [{$a['error_type']}] {$a['error_message']}");
            }
        }

        // Bank status
        $command->newLine();
        if ($result['bank_status']) {
            $bs = $result['bank_status'];
            $status = $bs['status'] ?? 'unknown';
            $statusLabel = strtoupper($status);

            if ($status === 'error') {
                $command->error("Bank Status: ERROR — {$bs['error']}");
            } elseif (in_array($status, ['completed', 'processing'])) {
                $command->info("Bank Status: {$statusLabel}");
            } else {
                $command->warn("Bank Status: {$statusLabel}");
            }
        }

        // Interpretation
        $command->newLine();
        $command->line('<fg=cyan>Interpretation:</>');
        $command->line("  {$result['interpretation']}");
        $command->newLine();

        return Command::SUCCESS;
    }
}
