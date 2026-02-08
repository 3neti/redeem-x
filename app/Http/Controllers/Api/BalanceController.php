<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountBalance;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function __construct(
        protected BalanceService $service
    ) {}

    /**
     * Display a listing of all account balances.
     */
    public function index(): JsonResponse
    {
        $gatewayName = config('payment-gateway.default', 'netbank');

        $balances = AccountBalance::query()
            ->where('gateway', $gatewayName)
            ->orderBy('checked_at', 'desc')
            ->get()
            ->map(fn ($balance) => [
                'account_number' => $balance->account_number,
                'gateway' => $balance->gateway,
                'balance' => $balance->balance,
                'available_balance' => $balance->available_balance,
                'currency' => $balance->currency,
                'formatted_balance' => $balance->formatted_balance,
                'formatted_available_balance' => $balance->formatted_available_balance,
                'checked_at' => $balance->checked_at->toIso8601String(),
                'is_low' => $balance->isLow(),
            ]);

        return response()->json([
            'data' => $balances,
        ]);
    }

    /**
     * Display the specified account balance.
     */
    public function show(string $accountNumber): JsonResponse
    {
        $balance = $this->service->getCurrentBalance($accountNumber);

        if (! $balance) {
            return response()->json([
                'message' => 'Account balance not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'account_number' => $balance->account_number,
                'gateway' => $balance->gateway,
                'balance' => $balance->balance,
                'available_balance' => $balance->available_balance,
                'currency' => $balance->currency,
                'formatted_balance' => $balance->formatted_balance,
                'formatted_available_balance' => $balance->formatted_available_balance,
                'checked_at' => $balance->checked_at->toIso8601String(),
                'is_low' => $balance->isLow(),
                'metadata' => $balance->metadata,
            ],
        ]);
    }

    /**
     * Refresh balance for a specific account.
     */
    public function refresh(string $accountNumber): JsonResponse
    {
        try {
            $balance = $this->service->checkAndUpdate($accountNumber);

            return response()->json([
                'message' => 'Balance refreshed successfully.',
                'data' => [
                    'account_number' => $balance->account_number,
                    'gateway' => $balance->gateway,
                    'balance' => $balance->balance,
                    'available_balance' => $balance->available_balance,
                    'currency' => $balance->currency,
                    'formatted_balance' => $balance->formatted_balance,
                    'formatted_available_balance' => $balance->formatted_available_balance,
                    'checked_at' => $balance->checked_at->toIso8601String(),
                    'is_low' => $balance->isLow(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to refresh balance.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get balance history for an account.
     */
    public function history(Request $request, string $accountNumber): JsonResponse
    {
        $limit = $request->integer('limit', 100);
        $days = $request->integer('days');

        $history = $days
            ? $this->service->getTrend($accountNumber, $days)
            : $this->service->getHistory($accountNumber, $limit);

        return response()->json([
            'data' => $history->map(fn ($entry) => [
                'balance' => $entry->balance,
                'available_balance' => $entry->available_balance,
                'currency' => $entry->currency,
                'formatted_balance' => $entry->formatted_balance,
                'formatted_available_balance' => $entry->formatted_available_balance,
                'recorded_at' => $entry->recorded_at->toIso8601String(),
            ]),
        ]);
    }
}
