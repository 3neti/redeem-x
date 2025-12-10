<?php

namespace App\Services;

use App\Models\AccountBalance;
use App\Models\BalanceAlert;
use App\Models\BalanceHistory;
use App\Notifications\LowBalanceAlert;
use Brick\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

class BalanceService
{
    private const DEBUG = false;
    
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    /**
     * Check and update balance for an account.
     */
    public function checkAndUpdate(string $accountNumber): AccountBalance
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        // Check balance from gateway
        $result = $this->gateway->checkAccountBalance($accountNumber);

        // Update or create record
        $balance = AccountBalance::updateOrCreate(
            [
                'account_number' => $accountNumber,
                'gateway' => $gatewayName,
            ],
            [
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
                'currency' => $result['currency'],
                'checked_at' => now(),
                'metadata' => $result['raw'],
            ]
        );

        // Record history
        BalanceHistory::create([
            'account_number' => $accountNumber,
            'gateway' => $gatewayName,
            'balance' => $result['balance'],
            'available_balance' => $result['available_balance'],
            'currency' => $result['currency'],
            'recorded_at' => now(),
        ]);

        // Check for alerts
        $this->checkAlerts($balance);

        if (self::DEBUG) {
            Log::info('[BalanceService] Balance updated', [
                'account' => $accountNumber,
                'balance' => $result['balance'],
                'available_balance' => $result['available_balance'],
            ]);
        }

        return $balance->fresh();
    }

    /**
     * Check alerts and trigger if needed.
     */
    protected function checkAlerts(AccountBalance $balance): void
    {
        $alerts = $balance->alerts()
            ->where('enabled', true)
            ->where('threshold', '>', $balance->balance)
            ->get();

        foreach ($alerts as $alert) {
            // Prevent spam - only alert once per day
            if ($alert->wasTriggeredToday()) {
                continue;
            }

            $this->triggerAlert($balance, $alert);

            $alert->update(['last_triggered_at' => now()]);
        }
    }

    /**
     * Trigger an alert.
     */
    protected function triggerAlert(AccountBalance $balance, BalanceAlert $alert): void
    {
        $message = "Low balance alert: {$balance->account_number} has {$balance->formatted_balance} (threshold: {$alert->formatted_threshold})";

        switch ($alert->alert_type) {
            case 'email':
                foreach ($alert->recipients as $email) {
                    Notification::route('mail', $email)
                        ->notify(new LowBalanceAlert($balance, $alert));
                }
                break;

            case 'sms':
                foreach ($alert->recipients as $phone) {
                    // TODO: Integrate with SMS notification system
                    Log::info('[BalanceService] SMS alert', [
                        'phone' => $phone,
                        'message' => $message,
                    ]);
                }
                break;

            case 'webhook':
                foreach ($alert->recipients as $url) {
                    try {
                        Http::post($url, [
                            'type' => 'low_balance_alert',
                            'account' => $balance->account_number,
                            'balance' => $balance->balance,
                            'available_balance' => $balance->available_balance,
                            'threshold' => $alert->threshold,
                            'currency' => $balance->currency,
                            'checked_at' => $balance->checked_at->toIso8601String(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('[BalanceService] Webhook alert failed', [
                            'url' => $url,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                break;
        }

        Log::warning('[BalanceService] Low balance alert triggered', [
            'account' => $balance->account_number,
            'balance' => $balance->balance,
            'threshold' => $alert->threshold,
            'alert_type' => $alert->alert_type,
        ]);
    }

    /**
     * Get balance trend for account.
     */
    public function getTrend(string $accountNumber, int $days = 7): Collection
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        return BalanceHistory::query()
            ->where('account_number', $accountNumber)
            ->where('gateway', $gatewayName)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Get complete balance history for account.
     */
    public function getHistory(string $accountNumber, int $limit = 100): Collection
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        return BalanceHistory::query()
            ->where('account_number', $accountNumber)
            ->where('gateway', $gatewayName)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get current balance from database.
     */
    public function getCurrentBalance(string $accountNumber): ?AccountBalance
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        return AccountBalance::query()
            ->where('account_number', $accountNumber)
            ->where('gateway', $gatewayName)
            ->first();
    }

    /**
     * Create or update balance alert.
     */
    public function createAlert(
        string $accountNumber,
        int $threshold,
        string $alertType,
        array $recipients
    ): BalanceAlert {
        $gatewayName = config('payment-gateway.default', 'netbank');

        return BalanceAlert::create([
            'account_number' => $accountNumber,
            'gateway' => $gatewayName,
            'threshold' => $threshold,
            'alert_type' => $alertType,
            'recipients' => $recipients,
            'enabled' => true,
        ]);
    }

    /**
     * Check if balance is below threshold.
     */
    public function isBalanceLow(string $accountNumber, int $threshold): bool
    {
        $balance = $this->getCurrentBalance($accountNumber);

        if (!$balance) {
            return false;
        }

        return $balance->balance < $threshold;
    }
}
