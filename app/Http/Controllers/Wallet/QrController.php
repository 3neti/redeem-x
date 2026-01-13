<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrController extends Controller
{
    /**
     * Display the wallet QR load page.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('wallet/Qr', [
            'loadWalletConfig' => config('load-wallet'),
            'reason' => $request->query('reason'),
            'return_to' => $request->query('return_to'),
        ]);
    }
}
