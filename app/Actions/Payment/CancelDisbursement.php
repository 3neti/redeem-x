<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cancel/abandon a pending disbursement so the system stops trying to reconcile it.
 *
 * IMPORTANT: Most bank transfer APIs (including NetBank) do NOT support cancellation
 * once submitted. This command only manages the LOCAL state. The operator must contact
 * the bank directly for actual reversal if the transfer was already processed.
 */
class CancelDisbursement
{
    use AsAction;

    public string $commandSignature = 'disbursement:cancel
                            {code : The voucher code}
                            {--force : Cancel even if bank status is pending/processing}';

    public string $commandDescription = 'Cancel/abandon a pending disbursement (local state only)';

    /**
     * Core logic — attempt to cancel a disbursement.
     *
     * @return array{success: bool, message: string, attempt: ?array, bank_status: ?string}
     */
    public function handle(string $code, bool $force = false): array
    {
        // Find reconcilable attempts for this voucher
        $attempt = DisbursementAttempt::byVoucherCode($code)
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(function ($inner) {
                        $inner->where('status', 'failed')
                            ->where(function ($timeout) {
                                $timeout->whereNotNull('gateway_transaction_id')
                                    ->orWhere('error_type', 'network_timeout')
                                    ->orWhere('error_type', 'ConnectionException');
                            });
                    });
            })
            ->orderBy('attempted_at', 'desc')
            ->first();

        if (! $attempt) {
            // Check if there's any attempt at all
            $anyAttempt = DisbursementAttempt::byVoucherCode($code)->latest('attempted_at')->first();

            if (! $anyAttempt) {
                return [
                    'success' => false,
                    'message' => 'No disbursement attempts found for this voucher.',
                    'attempt' => null,
                    'bank_status' => null,
                ];
            }

            return [
                'success' => false,
                'message' => "Latest attempt is already in final state: {$anyAttempt->status}. Nothing to cancel.",
                'attempt' => $this->formatAttempt($anyAttempt),
                'bank_status' => null,
            ];
        }

        // If attempt has a gateway transaction ID, check bank status first
        $bankStatus = null;
        if ($attempt->hasGatewayTransactionId()) {
            try {
                $gateway = app(PaymentGatewayInterface::class);
                $result = $gateway->checkDisbursementStatus($attempt->gateway_transaction_id);
                $bankStatus = $result['status'] ?? 'unknown';
            } catch (\Throwable $e) {
                Log::warning('[CancelDisbursement] Could not check bank status', [
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
                $bankStatus = 'unreachable';
            }

            // Bank already completed — refuse to cancel
            if ($bankStatus === 'completed') {
                return [
                    'success' => false,
                    'message' => 'Bank already SETTLED this transfer. Money has been delivered. '
                        .'Cannot cancel. Use disbursement:reconcile to update local records instead.',
                    'attempt' => $this->formatAttempt($attempt),
                    'bank_status' => $bankStatus,
                ];
            }

            // Bank still processing — warn
            if (in_array($bankStatus, ['pending', 'processing']) && ! $force) {
                return [
                    'success' => false,
                    'message' => "Bank status is {$bankStatus}. The transfer may still complete. "
                        .'Cancelling locally only prevents our system from retrying — the bank transfer may still go through. '
                        .'Use --force to cancel anyway.',
                    'attempt' => $this->formatAttempt($attempt),
                    'bank_status' => $bankStatus,
                ];
            }
        }

        // Safe to cancel: no transaction ID (bank never received), bank rejected, or forced
        $reason = $force ? 'Force-cancelled by operator' : 'Cancelled by operator';
        if ($bankStatus === 'failed') {
            $reason = 'Cancelled — bank confirmed rejection';
        } elseif (! $attempt->hasGatewayTransactionId()) {
            $reason = 'Cancelled — bank never received the request';
        }

        $attempt->markAsCancelled($reason);

        Log::info('[CancelDisbursement] Disbursement cancelled', [
            'code' => $code,
            'attempt_id' => $attempt->id,
            'reason' => $reason,
            'bank_status' => $bankStatus,
            'forced' => $force,
        ]);

        return [
            'success' => true,
            'message' => "Disbursement cancelled: {$reason}",
            'attempt' => $this->formatAttempt($attempt->fresh()),
            'bank_status' => $bankStatus,
        ];
    }

    private function formatAttempt(DisbursementAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'reference_id' => $attempt->reference_id,
            'gateway_transaction_id' => $attempt->gateway_transaction_id,
            'amount' => $attempt->amount,
            'error_type' => $attempt->error_type,
            'error_message' => $attempt->error_message,
            'attempted_at' => $attempt->attempted_at?->toDateTimeString(),
        ];
    }

    /**
     * Artisan command output.
     */
    public function asCommand(Command $command): int
    {
        $code = strtoupper($command->argument('code'));
        $force = $command->option('force');

        $result = $this->handle($code, $force);

        $command->newLine();

        if ($result['success']) {
            $command->info($result['message']);
        } else {
            $command->error($result['message']);
        }

        if ($result['attempt']) {
            $a = $result['attempt'];
            $command->newLine();
            $command->line("  Status: {$a['status']}");
            $command->line("  Reference: {$a['reference_id']}");
            $command->line('  Gateway TX ID: '.($a['gateway_transaction_id'] ?? 'NONE'));
            $command->line("  Amount: {$a['amount']}");
        }

        if ($result['bank_status']) {
            $command->line("  Bank Status: {$result['bank_status']}");
        }

        $command->newLine();

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
