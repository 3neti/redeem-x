<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\Wallet\Events\DisbursementConfirmed;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reconcile a pending/failed disbursement by checking bank status.
 *
 * When a disbursement fails at redemption time (Phase A), the voucher is marked
 * redeemed but the cash wallet still has balance (WithdrawCash was never called).
 * This action queries the bank for the real outcome and:
 *
 *   - On confirmed success: calls WithdrawCash, updates records, fires DisbursementConfirmed
 *   - On confirmed failure: marks attempt as failed, updates voucher metadata
 *   - On still-pending: reports current status (no changes)
 */
class ReconcileDisbursement
{
    use AsAction;

    public string $commandSignature = 'disbursement:reconcile
                            {code : The voucher code to reconcile}
                            {--json : Output as JSON}';

    public string $commandDescription = 'Reconcile a pending disbursement by checking bank status and completing the ledger entry';

    /**
     * Core logic — reconcile a single voucher's disbursement.
     *
     * @return array{success: bool, action: string, message: string, attempt: ?array, bank_status: ?string}
     */
    public function handle(string $code): array
    {
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return $this->result(false, 'not_found', "Voucher not found: {$code}");
        }

        if (! $voucher->redeemed_at) {
            return $this->result(false, 'not_redeemed', 'Voucher has not been redeemed — nothing to reconcile.');
        }

        // Find the latest reconcilable attempt
        $attempt = DisbursementAttempt::byVoucherCode($code)
            ->reconcilable()
            ->orderBy('attempted_at', 'desc')
            ->first();

        if (! $attempt) {
            // Check if there's any attempt at all (might already be reconciled)
            $latest = DisbursementAttempt::byVoucherCode($code)->latest('attempted_at')->first();

            if (! $latest) {
                return $this->result(false, 'no_attempts', 'No disbursement attempts found for this voucher.');
            }

            return $this->result(
                false,
                'already_final',
                "Latest attempt is already in final state: {$latest->status}. Nothing to reconcile.",
                $this->formatAttempt($latest)
            );
        }

        // Must have a gateway transaction ID to query the bank
        if (! $attempt->hasGatewayTransactionId()) {
            return $this->result(
                false,
                'no_transaction_id',
                'No bank transaction ID on this attempt. The bank likely never received the request. '
                    .'Use disbursement:cancel to abandon, or retry the disbursement manually.',
                $this->formatAttempt($attempt)
            );
        }

        // Query the bank
        try {
            $gateway = app(PaymentGatewayInterface::class);
            $bankResult = $gateway->checkDisbursementStatus($attempt->gateway_transaction_id);
        } catch (\Throwable $e) {
            Log::warning('[ReconcileDisbursement] Failed to query bank', [
                'code' => $code,
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);

            return $this->result(
                false,
                'gateway_error',
                'Could not reach the bank: '.$e->getMessage(),
                $this->formatAttempt($attempt)
            );
        }

        $bankStatus = $bankResult['status'] ?? 'unknown';

        if ($bankStatus === 'error') {
            return $this->result(
                false,
                'gateway_error',
                'Gateway returned error: '.($bankResult['error'] ?? 'unknown'),
                $this->formatAttempt($attempt),
                $bankStatus
            );
        }

        // Normalize the bank status
        $gatewayName = $attempt->gateway ?? config('payment-gateway.default', 'netbank');
        $normalizedStatus = DisbursementStatus::fromGateway($gatewayName, $bankStatus);

        // === CONFIRMED SUCCESS ===
        if ($normalizedStatus === DisbursementStatus::COMPLETED) {
            return $this->handleConfirmedSuccess($voucher, $attempt, $bankResult, $gatewayName);
        }

        // === CONFIRMED FAILURE ===
        if ($normalizedStatus === DisbursementStatus::FAILED) {
            return $this->handleConfirmedFailure($voucher, $attempt, $bankStatus);
        }

        // === STILL PENDING / PROCESSING ===
        $attempt->increment('attempt_count');
        $attempt->update(['last_checked_at' => now()]);

        Log::debug('[ReconcileDisbursement] Still pending', [
            'code' => $code,
            'bank_status' => $bankStatus,
            'normalized' => $normalizedStatus->value,
            'attempt_count' => $attempt->attempt_count,
        ]);

        return $this->result(
            true,
            'still_pending',
            "Bank status is still {$normalizedStatus->getLabel()}. Check #{$attempt->attempt_count}. Will check again later.",
            $this->formatAttempt($attempt->fresh()),
            $bankStatus
        );
    }

    /**
     * Bank confirmed the transfer was delivered — complete the internal ledger.
     */
    private function handleConfirmedSuccess(
        Voucher $voucher,
        DisbursementAttempt $attempt,
        array $bankResult,
        string $gatewayName
    ): array {
        $code = $voucher->code;
        $withdrawalUuid = null;

        // 1. WithdrawCash — debit the internal cash wallet (the step skipped during failed disbursement)
        $cash = $voucher->cash;

        if ($cash && $cash->wallet->balance > 0) {
            try {
                $withdrawal = WithdrawCash::run(
                    $cash,
                    $attempt->gateway_transaction_id,
                    'Reconciled: Bank confirmed delivery',
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_code' => $code,
                        'flow' => 'reconcile',
                        'attempt_id' => $attempt->id,
                    ]
                );
                $withdrawalUuid = $withdrawal->uuid;

                Log::info('[ReconcileDisbursement] WithdrawCash completed', [
                    'code' => $code,
                    'withdrawal_uuid' => $withdrawalUuid,
                ]);
            } catch (\Throwable $e) {
                Log::error('[ReconcileDisbursement] WithdrawCash failed', [
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);

                return $this->result(
                    false,
                    'withdraw_failed',
                    'Bank confirmed success but internal withdrawal failed: '.$e->getMessage()
                        .' — DisbursementAttempt NOT updated. Investigate manually.',
                    $this->formatAttempt($attempt),
                    'completed'
                );
            }
        } else {
            Log::warning('[ReconcileDisbursement] Cash wallet already empty — skipping WithdrawCash', [
                'code' => $code,
                'cash_id' => $cash?->getKey(),
            ]);
        }

        // 2. Update DisbursementAttempt
        $attempt->markAsSuccess($attempt->gateway_transaction_id);

        // 3. Update voucher metadata
        $metadata = $voucher->metadata;
        $metadata['disbursement']['status'] = DisbursementStatus::COMPLETED->value;
        $metadata['disbursement']['status_updated_at'] = now()->toIso8601String();
        $metadata['disbursement']['reconciled_at'] = now()->toIso8601String();
        unset($metadata['disbursement']['requires_reconciliation']);
        unset($metadata['disbursement']['error']);

        if ($withdrawalUuid) {
            $metadata['disbursement']['cash_withdrawal_uuid'] = $withdrawalUuid;
        }

        // Enrich from bank raw response if available
        if (! empty($bankResult['raw'])) {
            $metadata['disbursement']['status_raw'] = $bankResult['raw'];
        }

        $voucher->metadata = $metadata;
        $voucher->save();

        // 4. Fire DisbursementConfirmed event
        event(new DisbursementConfirmed($voucher));

        Log::info('[ReconcileDisbursement] Success — disbursement confirmed and ledger updated', [
            'code' => $code,
            'attempt_id' => $attempt->id,
            'withdrawal_uuid' => $withdrawalUuid,
        ]);

        return $this->result(
            true,
            'confirmed_success',
            'Bank confirmed delivery. Cash wallet debited, records updated.',
            $this->formatAttempt($attempt->fresh()),
            'completed'
        );
    }

    /**
     * Bank confirmed the transfer was rejected — mark as failed.
     */
    private function handleConfirmedFailure(
        Voucher $voucher,
        DisbursementAttempt $attempt,
        string $bankStatus
    ): array {
        $code = $voucher->code;

        // 1. Update DisbursementAttempt
        $attempt->markAsFailed('Bank confirmed rejection', 'bank_rejected');

        // 2. Update voucher metadata
        $metadata = $voucher->metadata;
        $metadata['disbursement']['status'] = DisbursementStatus::FAILED->value;
        $metadata['disbursement']['status_updated_at'] = now()->toIso8601String();
        $metadata['disbursement']['reconciled_at'] = now()->toIso8601String();
        $voucher->metadata = $metadata;
        $voucher->save();

        Log::info('[ReconcileDisbursement] Failure confirmed — bank rejected transfer', [
            'code' => $code,
            'attempt_id' => $attempt->id,
            'bank_status' => $bankStatus,
        ]);

        return $this->result(
            true,
            'confirmed_failed',
            'Bank confirmed rejection. Money was NOT delivered. Attempt marked as failed. '
                .'Operator can retry the disbursement if needed.',
            $this->formatAttempt($attempt->fresh()),
            $bankStatus
        );
    }

    /**
     * Build a consistent result array.
     */
    private function result(
        bool $success,
        string $action,
        string $message,
        ?array $attempt = null,
        ?string $bankStatus = null,
    ): array {
        return [
            'success' => $success,
            'action' => $action,
            'message' => $message,
            'attempt' => $attempt,
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
            'bank_code' => $attempt->bank_code,
            'settlement_rail' => $attempt->settlement_rail,
            'error_type' => $attempt->error_type,
            'error_message' => $attempt->error_message,
            'attempted_at' => $attempt->attempted_at?->toDateTimeString(),
            'completed_at' => $attempt->completed_at?->toDateTimeString(),
        ];
    }

    /**
     * Run as a queued job (for scheduled reconciliation).
     */
    public function asJob(string $code): void
    {
        try {
            $result = $this->handle($code);

            if (! $result['success'] && $result['action'] !== 'still_pending') {
                Log::warning('[ReconcileDisbursement:Job] Non-success result', [
                    'code' => $code,
                    'action' => $result['action'],
                    'message' => $result['message'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[ReconcileDisbursement:Job] Unexpected error', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }
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

            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        }

        $command->newLine();

        // Show result
        if ($result['success']) {
            $icon = match ($result['action']) {
                'confirmed_success' => '✅',
                'confirmed_failed' => '❌',
                'still_pending' => '⏳',
                default => 'ℹ️',
            };
            $command->info("{$icon} {$result['message']}");
        } else {
            $command->error($result['message']);
        }

        // Show attempt details
        if ($result['attempt']) {
            $a = $result['attempt'];
            $command->newLine();
            $command->line("  Attempt #{$a['id']}:");
            $command->line("    Status: {$a['status']}");
            $command->line("    Reference: {$a['reference_id']}");
            $command->line('    Gateway TX ID: '.($a['gateway_transaction_id'] ?? 'NONE'));
            $command->line("    Amount: {$a['amount']}");
            $command->line('    Bank: '.($a['bank_code'] ?? 'N/A').' via '.($a['settlement_rail'] ?? 'N/A'));
            $command->line("    Attempted: {$a['attempted_at']}");

            if ($a['completed_at']) {
                $command->line("    Completed: {$a['completed_at']}");
            }
        }

        if ($result['bank_status']) {
            $command->line("    Bank Status: {$result['bank_status']}");
        }

        $command->newLine();

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
