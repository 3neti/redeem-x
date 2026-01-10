<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Display the portal landing page.
     * 
     * Public route - no authentication required.
     * Shows cash register UI for instant voucher generation.
     */
    public function show(): Response
    {
        $user = Auth::user();
        
        return Inertia::render('Portal', [
            'is_authenticated' => $user !== null,
            'wallet_balance' => $user?->balanceFloatNum ?? 0,
            'vouchers_count' => $user?->vouchers()->count() ?? 0,
            'formatted_balance' => $user ? '₱' . number_format($user->balanceFloatNum, 2) : '₱0.00',
            'page_title' => config('portal.title', 'Portal'),
            'page_subtitle' => config('portal.subtitle', 'Generate vouchers instantly'),
            'config' => config('portal'),
        ]);
    }
}
