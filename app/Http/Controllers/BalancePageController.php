<?php

namespace App\Http\Controllers;

use App\Services\BalanceService;
use App\Services\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BalancePageController extends Controller
{
    /**
     * Display the balance monitoring page.
     * 
     * Access control:
     * - Admin role required (configurable via BALANCE_VIEW_ROLE)
     * - Can be disabled via BALANCE_VIEW_ENABLED=false
     */
    public function index(BalanceService $service, ReconciliationService $reconciliation): Response
    {
        // Check if balance viewing is enabled
        if (!config('balance.view_enabled', true)) {
            abort(403, 'Balance viewing is currently disabled.');
        }

        // Check role-based access (if configured)
        $requiredRole = config('balance.view_role', 'admin');
        
        // If role is empty or null, allow all authenticated users
        if ($requiredRole && !auth()->user()->hasRole($requiredRole)) {
            abort(403, 'You do not have permission to view balance information.');
        }

        $accountNumber = config('balance.default_account') 
            ?? config('payment-gateway.default_account')
            ?? config('omnipay.test_account')
            ?? config('disbursement.account_number');

        if (!$accountNumber) {
            abort(500, 'No default account configured. Set BALANCE_DEFAULT_ACCOUNT in .env');
        }

        $balance = $service->getCurrentBalance($accountNumber);
        $trend = $balance ? $service->getTrend($accountNumber, 7) : collect();
        $history = $balance ? $service->getHistory($accountNumber, 20) : collect();

        // Get alerts for this account
        $alerts = $balance ? $balance->alerts : collect();

        // Map history to include formatted attributes
        $historyData = $history->map(fn($entry) => [
            'balance' => $entry->balance,
            'available_balance' => $entry->available_balance,
            'currency' => $entry->currency,
            'formatted_balance' => $entry->formatted_balance,
            'formatted_available_balance' => $entry->formatted_available_balance,
            'recorded_at' => $entry->recorded_at->toIso8601String(),
        ]);

        // Map trend to include formatted attributes
        $trendData = $trend->map(fn($entry) => [
            'balance' => $entry->balance,
            'available_balance' => $entry->available_balance,
            'currency' => $entry->currency,
            'formatted_balance' => $entry->formatted_balance,
            'formatted_available_balance' => $entry->formatted_available_balance,
            'recorded_at' => $entry->recorded_at->toIso8601String(),
        ]);

        // Get reconciliation status
        $reconciliationStatus = $reconciliation->getReconciliationStatus($accountNumber);

        return Inertia::render('balances/Index', [
            'balance' => $balance,
            'trend' => $trendData,
            'history' => $historyData,
            'alerts' => $alerts,
            'accountNumber' => $accountNumber,
            'canManageAlerts' => auth()->user()->hasRole($requiredRole),
            'reconciliation' => $reconciliationStatus,
        ]);
    }
}
