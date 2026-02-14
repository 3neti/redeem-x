<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaTopUpController extends Controller
{
    /**
     * Display PWA top-up page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $recentTopUps = $user->getTopUps()->take(5)->map(fn ($topUp) => [
            'reference_no' => $topUp->reference_no,
            'amount' => $topUp->amount,
            'status' => $topUp->payment_status,
            'gateway' => $topUp->gateway,
            'institution_code' => $topUp->institution_code,
            'created_at' => $topUp->created_at->toIso8601String(),
        ]);

        $pendingTopUps = $user->getPendingTopUps()->map(fn ($topUp) => [
            'reference_no' => $topUp->reference_no,
            'amount' => $topUp->amount,
            'status' => $topUp->payment_status,
            'gateway' => $topUp->gateway,
            'institution_code' => $topUp->institution_code,
            'created_at' => $topUp->created_at->toIso8601String(),
        ]);

        return Inertia::render('pwa/TopUp', [
            'balance' => $user->balanceFloat,
            'recentTopUps' => $recentTopUps,
            'pendingTopUps' => $pendingTopUps,
            'isSuperAdmin' => $user->hasRole('super-admin'),
        ]);
    }
}
