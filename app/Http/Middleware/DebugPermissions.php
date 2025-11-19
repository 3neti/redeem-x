<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugPermissions
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            Log::info('DebugPermissions middleware', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'roles' => $request->user()->roles->pluck('name')->toArray(),
                'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
                'has_super_admin' => $request->user()->hasRole('super-admin'),
                'route' => $request->route()->getName(),
            ]);
        }

        return $next($request);
    }
}
