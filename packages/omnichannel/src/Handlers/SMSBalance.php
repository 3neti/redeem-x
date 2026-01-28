<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Actions\Api\System\GetBalances;
use App\Data\Api\Wallet\BalanceData;
use App\Models\AccountBalance;
use App\Models\User;
use App\Notifications\BalanceNotification;
use App\Services\BalanceService;
use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;

class SMSBalance implements SMSHandlerInterface
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
     * @param array $values Parsed values from the SMS message.
     * @param string $from Sender's phone number.
     * @param string $to Receiver's phone number.
     * @return JsonResponse The response to send back.
     */
    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        Log::info('[SMSBalance] Processing BALANCE command', [
            'from' => $from,
            'to' => $to,
            'values' => $values,
        ]);

        // Look up user by mobile number
        $user = $this->findUserByMobile($from);

        if (!$user) {
            Log::warning('[SMSBalance] User not found', ['mobile' => $from]);
            return response()->json([
                'message' => 'No account found. Send REGISTER to create one.',
            ]);
        }

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
    protected function handleUserBalance(User $user, string $from): JsonResponse
    {
        try {
            $balance = BalanceData::fromWallet($user->wallet);
            
            $message = sprintf('Balance: %s', $this->formatMoney($balance->balance, 'PHP'));

            Log::info('[SMSBalance] User balance retrieved', [
                'user_id' => $user->id,
                'balance' => $balance->balance,
            ]);

            // Send notification (SMS, email, webhook)
            $user->notify(new BalanceNotification(
                type: 'user',
                balances: ['wallet' => $balance->balance],
                message: $message
            ));

            return response()->json(['message' => $message]);
        } catch (\Throwable $e) {
            Log::error('[SMSBalance] Failed to get user balance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve balance. Please try again.',
            ]);
        }
    }

    /**
     * Handle system balance query (BALANCE --system command).
     */
    protected function handleSystemBalance(User $user, string $from): JsonResponse
    {
        // Check permission
        if (!$user->can('view-balances')) {
            Log::warning('[SMSBalance] Unauthorized system balance request', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ]);
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

            Log::info('[SMSBalance] System balance retrieved', [
                'user_id' => $user->id,
                'system' => $systemBalance,
                'products' => $productsBalance,
            ]);

            // Send notification (SMS, email, webhook)
            $user->notify(new BalanceNotification(
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
                $message .= "\n" . $bankLine;
            }

            return response()->json(['message' => $message]);
        } catch (\Throwable $e) {
            Log::error('[SMSBalance] Failed to get system balance', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve system balance. Please try again.',
            ]);
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

            if (!$accountNumber) {
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
            Log::error('[SMSBalance] Failed to get bank balance', [
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
                Log::warning('[SMSBalance] Bank API timeout', [
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
            Log::warning('[SMSBalance] Bank API failed, using cache', [
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

        if (!$cached) {
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

    /**
     * Find user by mobile number.
     */
    protected function findUserByMobile(string $mobile): ?User
    {
        // Try exact match first
        $user = User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where('value', $mobile);
        })->first();

        if ($user) {
            return $user;
        }

        // Try with variations (with/without country code, leading zero)
        return User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
                ->where(function ($sub) use ($mobile) {
                    $sub->where('value', 'LIKE', "%{$mobile}%")
                        ->orWhere('value', 'LIKE', '%' . ltrim($mobile, '0') . '%');
                });
        })->first();
    }

    /**
     * Format money with thousands separator.
     */
    protected function formatMoney(float $amount, string $currency): string
    {
        try {
            $money = Money::of($amount, $currency);
            return $money->formatTo('en_PH');
        } catch (\Throwable $e) {
            // Fallback formatting
            return '₱' . number_format($amount, 2);
        }
    }

}
