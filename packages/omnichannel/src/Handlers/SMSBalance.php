<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Actions\Api\System\GetBalances;
use App\Data\Api\Wallet\BalanceData;
use App\Models\AccountBalance;
use App\Models\User;
use App\Notifications\BalanceNotification;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;

class SMSBalance extends BaseSMSHandler
{
    /**
     * Bank balance staleness threshold (5 minutes)
     */
    private const STALE_THRESHOLD_MINUTES = 5;

    /**
     * Bank balance API timeout (5 seconds)
     */
    private const API_TIMEOUT_SECONDS = 5;

    /**
     * Handle BALANCE SMS command.
     *
     * @param  User|null  $user  The authenticated user
     * @param  array  $values  Parsed values from the SMS message
     * @param  string  $from  Sender's phone number
     * @param  string  $to  Receiver's phone number
     * @return JsonResponse The response to send back
     */
    protected function handle(?User $user, array $values, string $from, string $to): JsonResponse
    {
        // Check if --system flag is present
        $flag = $values['flag'] ?? null;
        $isSystemQuery = $flag && (strtolower($flag) === '--system' || strtolower($flag) === 'system');

        if ($isSystemQuery) {
            return $this->handleSystemBalance($user, $from);
        }

        return $this->handleUserBalance($user, $from);
    }

    /**
     * Handle user balance query (regular BALANCE command).
     */
    protected function handleUserBalance(?User $user, string $from): JsonResponse
    {
        // This should never happen due to requiresAuth() check in base handler,
        // but guard against it just in case
        if (! $user) {
            $this->logError('User is null in handleUserBalance', ['from' => $from]);
            return $this->errorResponse('Authentication required');
        }

        $balance = BalanceData::fromWallet($user->wallet);

        $message = sprintf('Balance: %s', $this->formatMoney($balance->balance, 'PHP'));

        $this->logInfo('User balance retrieved', [
            'user_id' => $user->id,
            'balance' => $balance->balance,
        ]);

        // Send notification (SMS, email, webhook)
        $this->sendNotification($user, new BalanceNotification(
            type: 'user',
            balances: ['wallet' => $balance->balance],
            message: $message
        ));

        return response()->json(['message' => $message]);
    }

    /**
     * Handle system balance query (BALANCE --system command).
     */
    protected function handleSystemBalance(?User $user, string $from): JsonResponse
    {
        if (! $user) {
            $this->logError('User is null in handleSystemBalance', ['from' => $from]);
            return $this->errorResponse('Authentication required');
        }

        // Check permission
        if (! $user->can('view-balances')) {
            $this->logWarning('Unauthorized system balance request', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $this->errorResponse('Unauthorized. Admin access required.', 403);
        }

        try {
            // Get wallet balances
            $balances = (new GetBalances)->asController();
            $data = $balances->getData();

            $systemBalance = $data->data->totals->system ?? 0;
            $productsBalance = $data->data->totals->products ?? 0;

            // Format wallet balances
            $walletLine = sprintf(
                'Wallet: %s | Products: %s',
                $this->formatMoney($systemBalance, 'PHP'),
                $this->formatMoney($productsBalance, 'PHP')
            );

            // Try to get bank balance
            $bankBalanceData = $this->getBankBalanceData();

            // Build balance data for notification
            $balances = [
                'wallet' => $systemBalance,
                'products' => $productsBalance,
            ];

            if ($bankBalanceData) {
                $balances['bank'] = $bankBalanceData['balance'];
                $balances['bank_timestamp'] = $bankBalanceData['timestamp'];
                $balances['bank_stale'] = $bankBalanceData['stale'];
            }

            $this->logInfo('System balance retrieved', [
                'user_id' => $user->id,
                'system' => $systemBalance,
                'products' => $productsBalance,
            ]);

            // Send notification (SMS, email, webhook)
            $this->sendNotification($user, new BalanceNotification(
                type: 'system',
                balances: $balances
            ));

            // Build message for JSON response
            $message = sprintf(
                'Wallet: %s | Products: %s',
                $this->formatMoney($systemBalance, 'PHP'),
                $this->formatMoney($productsBalance, 'PHP')
            );
            if ($bankBalanceData) {
                $bankLine = sprintf(
                    'Bank: %s (as of %s)',
                    $this->formatMoney($bankBalanceData['balance'], 'PHP'),
                    $bankBalanceData['timestamp']
                );
                if ($bankBalanceData['stale']) {
                    $bankLine .= ' ⚠️ STALE';
                }
                $message .= "\n".$bankLine;
            }

            return response()->json(['message' => $message]);
        } catch (\Throwable $e) {
            throw $e; // Let base handler catch and log
        }
    }

    /**
     * Get bank balance data with staleness indicator.
     *
     * @return array|null ['balance' => float, 'timestamp' => string, 'stale' => bool]
     */
    protected function getBankBalanceData(): ?array
    {
        try {
            $accountNumber = env('BALANCE_DEFAULT_ACCOUNT');

            if (! $accountNumber) {
                return null;
            }

            // Try to fetch fresh balance with timeout
            $freshBalance = $this->tryFetchBankBalance($accountNumber);

            if ($freshBalance) {
                return [
                    'balance' => $freshBalance['balance'] / 100,
                    'timestamp' => now()->format('g:i A'),
                    'stale' => false,
                ];
            }

            // Fall back to cached balance
            return $this->getCachedBankBalanceData($accountNumber);
        } catch (\Throwable $e) {
            $this->logError('Failed to get bank balance', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Try to fetch fresh bank balance with timeout.
     *
     * @return array|null Returns balance data or null on timeout/error
     */
    protected function tryFetchBankBalance(string $accountNumber): ?array
    {
        try {
            // Set timeout using a quick process
            $startTime = microtime(true);

            $service = app(BalanceService::class);
            $balance = $service->checkAndUpdate($accountNumber);

            $elapsed = microtime(true) - $startTime;

            if ($elapsed > self::API_TIMEOUT_SECONDS) {
                $this->logWarning('Bank API timeout', [
                    'elapsed' => $elapsed,
                    'account' => $accountNumber,
                ]);

                return null;
            }

            return [
                'balance' => $balance->balance,
                'currency' => $balance->currency,
                'checked_at' => $balance->checked_at,
            ];
        } catch (\Throwable $e) {
            $this->logWarning('Bank API failed, using cache', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get cached bank balance data with staleness indicator.
     *
     * @return array|null ['balance' => float, 'timestamp' => string, 'stale' => bool]
     */
    protected function getCachedBankBalanceData(string $accountNumber): ?array
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        $cached = AccountBalance::where('account_number', $accountNumber)
            ->where('gateway', $gatewayName)
            ->first();

        if (! $cached) {
            return null;
        }

        $minutesAgo = $cached->checked_at->diffInMinutes(now());
        $isStale = $minutesAgo > self::STALE_THRESHOLD_MINUTES;

        $timestamp = $isStale
            ? $cached->checked_at->format('M j, g:i A')
            : ($minutesAgo < 1 ? 'just now' : "{$minutesAgo} min ago");

        return [
            'balance' => $cached->balance / 100,
            'timestamp' => $timestamp,
            'stale' => $isStale,
        ];
    }
}
