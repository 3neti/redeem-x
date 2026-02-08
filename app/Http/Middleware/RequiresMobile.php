<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresMobile
{
    /**
     * Handle an incoming request.
     * Redirects to profile settings if user has no mobile number.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Load channels relationship if not already loaded
        if (! $user->relationLoaded('channels')) {
            $user->load('channels');
        }

        if (! $user->mobile) {
            return redirect()->route('profile.edit', [
                'reason' => 'mobile_required',
                'return_to' => $request->fullUrl(),
            ])->with('flash', [
                'type' => 'warning',
                'message' => 'Please add your mobile number to continue.',
            ]);
        }

        return $next($request);
    }
}
