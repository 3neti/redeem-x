<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoadController extends Controller
{
    /**
     * Display the wallet load page.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('wallet/Load', [
            'loadWalletConfig' => config('load-wallet'),
        ]);
    }
}
