<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Merchant\Models\Merchant;

class LoadPublicController extends Controller
{
    /**
     * Display public QR code for a merchant.
     * 
     * This is a public page accessible without authentication.
     * Shows only the QR code for the specified merchant.
     */
    public function __invoke(Request $request, string $uuid): Response
    {
        // Find merchant by UUID
        $merchant = Merchant::where('uuid', $uuid)->firstOrFail();
        
        return Inertia::render('wallet/LoadPublic', [
            'merchantUuid' => $uuid,
            'merchantName' => $merchant->name,
            'merchantCity' => $merchant->city,
            'config' => config('load-wallet.public', []),
        ]);
    }
}
