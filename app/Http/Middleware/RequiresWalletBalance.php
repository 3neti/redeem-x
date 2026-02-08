<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresWalletBalance
{
    /**
     * Handle an incoming request.
     * Redirects to top-up page if user has zero or negative balance.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->balanceFloat <= 0) {
            return redirect()->route('wallet.qr', [
                'reason' => 'insufficient_balance',
                'return_to' => $request->fullUrl(),
            ])->with('flash', [
                'type' => 'warning',
                'message' => 'Please add funds to your wallet to generate vouchers.',
            ]);
        }

        return $next($request);
    }
}
