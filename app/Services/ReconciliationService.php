<?php

namespace App\Services;

use App\Models\AccountBalance;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\Log;

class ReconciliationService
{
    public function __construct(
        protected BalanceService $balanceService
    ) {}

    /**
     * Get total system balance (all user wallets).
     * 
     * @return int Balance in centavos
     */
    public function getTotalSystemBalance(): int
    {
        // Sum all user wallet balances (assuming bavix/laravel-wallet or similar)
        // Adjust this if your wallet implementation differs
        $total = User::sum('balance') ?? 0;

        Log::debug('[ReconciliationService] Total system balance calculated', [
            'total' => $total,
            'formatted' => Money::ofMinor($total, 'PHP')->formatTo('en_PH'),
        ]);

        return (int) $total;
    }

    /**
     * Get current bank balance.
     * 
     * @param string|null $accountNumber Account number (uses default if null)
     * @return int Balance in centavos
     */
    public function getBankBalance(?string $accountNumber = null): int
    {
        $accountNumber = $accountNumber ?? $this->getDefaultAccountNumber();

        $balance = $this->balanceService->getCurrentBalance($accountNumber);

        return $balance ? $balance->balance : 0;
    }

    /**
     * Get buffer amount based on configuration.
     * 
     * @param int $bankBalance Bank balance in centavos
     * @return int Buffer amount in centavos
     */
    public function getBuffer(int $bankBalance): int
    {
        // Custom amount takes precedence
        if ($customAmount = config('balance.reconciliation.buffer_amount')) {
            return (int) $customAmount;
        }

        // Otherwise use percentage
        $bufferPercent = config('balance.reconciliation.buffer', 10);
        return (int) ($bankBalance * ($bufferPercent / 100));
    }

    /**
     * Calculate available amount for voucher generation.
     * 
     * @param int $bankBalance Bank balance in centavos
     * @return int Available amount in centavos
     */
    public function getAvailableAmount(int $bankBalance): int
    {
        $systemBalance = $this->getTotalSystemBalance();
        $buffer = $this->getBuffer($bankBalance);

        return max(0, $bankBalance - $systemBalance - $buffer);
    }

    /**
     * Get comprehensive reconciliation status.
     * 
     * @param string|null $accountNumber Account number (uses default if null)
     * @return array Reconciliation status data
     */
    public function getReconciliationStatus(?string $accountNumber = null): array
    {
        if (!config('balance.reconciliation.enabled', true)) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'message' => 'Reconciliation checks are disabled',
            ];
        }

        $bankBalance = $this->getBankBalance($accountNumber);
        $systemBalance = $this->getTotalSystemBalance();
        $buffer = $this->getBuffer($bankBalance);
        $available = $this->getAvailableAmount($bankBalance);
        $warningThreshold = config('balance.reconciliation.warning_threshold', 90);

        $discrepancy = $systemBalance - $bankBalance;
        $usagePercent = $bankBalance > 0 ? ($systemBalance / $bankBalance) * 100 : 0;

        // Determine status
        if ($discrepancy > 0) {
            $status = 'critical';
            $message = 'System balance exceeds bank balance!';
        } elseif ($usagePercent >= $warningThreshold) {
            $status = 'warning';
            $message = sprintf('System balance at %.1f%% of bank balance', $usagePercent);
        } else {
            $status = 'safe';
            $message = 'Balances reconciled';
        }

        // Check if suppressed
        $suppressWarnings = config('balance.reconciliation.suppress_warnings', false);

        $result = [
            'enabled' => true,
            'status' => $status,
            'message' => $message,
            'bank_balance' => $bankBalance,
            'system_balance' => $systemBalance,
            'discrepancy' => $discrepancy,
            'usage_percent' => round($usagePercent, 2),
            'available' => $available,
            'buffer' => $buffer,
            'formatted' => [
                'bank_balance' => Money::ofMinor($bankBalance, 'PHP')->formatTo('en_PH'),
                'system_balance' => Money::ofMinor($systemBalance, 'PHP')->formatTo('en_PH'),
                'discrepancy' => Money::ofMinor(abs($discrepancy), 'PHP')->formatTo('en_PH'),
                'available' => Money::ofMinor($available, 'PHP')->formatTo('en_PH'),
                'buffer' => Money::ofMinor($buffer, 'PHP')->formatTo('en_PH'),
            ],
            'suppressed' => $suppressWarnings,
        ];

        Log::info('[ReconciliationService] Reconciliation status', $result);

        return $result;
    }

    /**
     * Check if voucher generation should be blocked.
     * 
     * @param int $requestedAmount Amount to generate in centavos
     * @param string|null $accountNumber Account number
     * @return bool True if should block
     */
    public function shouldBlockGeneration(int $requestedAmount, ?string $accountNumber = null): bool
    {
        if (!config('balance.reconciliation.enabled', true)) {
            return false;
        }

        if (config('balance.reconciliation.override', false)) {
            Log::warning('[ReconciliationService] Override active - generation allowed');
            return false;
        }

        if (config('balance.reconciliation.allow_overgeneration', false)) {
            Log::warning('[ReconciliationService] Overgeneration allowed by config');
            return false;
        }

        if (!config('balance.reconciliation.block_generation', true)) {
            return false;
        }

        $available = $this->getAvailableAmount($this->getBankBalance($accountNumber));

        return $requestedAmount > $available;
    }

    /**
     * Get default account number from config.
     * 
     * @return string|null
     */
    protected function getDefaultAccountNumber(): ?string
    {
        return config('balance.default_account')
            ?? config('payment-gateway.default_account')
            ?? config('omnipay.test_account')
            ?? config('disbursement.account_number');
    }

    /**
     * Get formatted generation limit message.
     * 
     * @param string|null $accountNumber Account number
     * @return string
     */
    public function getGenerationLimitMessage(?string $accountNumber = null): string
    {
        $status = $this->getReconciliationStatus($accountNumber);

        if ($status['status'] === 'critical') {
            return 'Cannot generate vouchers: System balance exceeds bank balance. Contact administrator.';
        }

        return sprintf(
            'Maximum available for generation: %s (Bank: %s, System: %s, Buffer: %s)',
            $status['formatted']['available'],
            $status['formatted']['bank_balance'],
            $status['formatted']['system_balance'],
            $status['formatted']['buffer']
        );
    }
}
