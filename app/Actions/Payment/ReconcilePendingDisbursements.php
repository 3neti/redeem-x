<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Batch reconciliation of pending disbursements.
 *
 * Runs on a schedule (every 15 minutes) to check bank status for all
 * pending/unknown disbursement attempts that are older than 5 minutes.
 * Skips attempts that have been checked more than 10 times (flags for manual review).
 */
class ReconcilePendingDisbursements
{
    use AsAction;

    /** Maximum checks before flagging for manual review */
    private const MAX_ATTEMPTS = 10;

    /** Minimum age (minutes) before first reconciliation check */
    private const MIN_AGE_MINUTES = 5;

    public string $commandSignature = 'disbursement:reconcile-pending
                            {--limit=50 : Maximum attempts to process}
                            {--include-exhausted : Include attempts with >10 checks}';

    public string $commandDescription = 'Reconcile all pending disbursements by checking bank status';

    /**
     * @return array{processed: int, success: int, failed: int, pending: int, skipped: int, errors: int}
     */
    public function handle(int $limit = 50, bool $includeExhausted = false): array
    {
        $query = DisbursementAttempt::reconcilable()
            ->where('attempted_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->orderBy('attempted_at', 'asc');

        if (! $includeExhausted) {
            $query->where('attempt_count', '<=', self::MAX_ATTEMPTS);
        }

        $attempts = $query->limit($limit)->get();

        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'pending' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($attempts->isEmpty()) {
            Log::debug('[ReconcilePending] No pending attempts to reconcile');

            return $stats;
        }

        Log::info('[ReconcilePending] Starting batch reconciliation', [
            'count' => $attempts->count(),
            'limit' => $limit,
        ]);

        foreach ($attempts as $attempt) {
            $stats['processed']++;

            try {
                $result = ReconcileDisbursement::run($attempt->voucher_code);

                match ($result['action']) {
                    'confirmed_success' => $stats['success']++,
                    'confirmed_failed' => $stats['failed']++,
                    'still_pending' => $stats['pending']++,
                    default => $stats['skipped']++,
                };
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('[ReconcilePending] Error reconciling attempt', [
                    'attempt_id' => $attempt->id,
                    'voucher_code' => $attempt->voucher_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[ReconcilePending] Batch reconciliation complete', $stats);

        // Flag exhausted attempts for manual review
        $exhausted = DisbursementAttempt::reconcilable()
            ->where('attempt_count', '>', self::MAX_ATTEMPTS)
            ->count();

        if ($exhausted > 0) {
            Log::warning('[ReconcilePending] Attempts exceeding max checks — needs manual review', [
                'count' => $exhausted,
                'max_attempts' => self::MAX_ATTEMPTS,
            ]);
        }

        return $stats;
    }

    public function asCommand(Command $command): int
    {
        $limit = (int) $command->option('limit');
        $includeExhausted = $command->option('include-exhausted');

        $stats = $this->handle($limit, $includeExhausted);

        $command->newLine();

        if ($stats['processed'] === 0) {
            $command->info('No pending disbursements to reconcile.');

            return Command::SUCCESS;
        }

        $command->info("Processed: {$stats['processed']}");
        $command->line("  ✅ Confirmed success: {$stats['success']}");
        $command->line("  ❌ Confirmed failed: {$stats['failed']}");
        $command->line("  ⏳ Still pending: {$stats['pending']}");
        $command->line("  ⏭️  Skipped: {$stats['skipped']}");

        if ($stats['errors'] > 0) {
            $command->error("  ⚠️  Errors: {$stats['errors']}");
        }

        $command->newLine();

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
