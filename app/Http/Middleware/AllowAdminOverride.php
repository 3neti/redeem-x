<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow specific users to bypass admin permission checks.
 * 
 * Configure allowed emails in config/admin.php or via ADMIN_OVERRIDE_EMAILS env variable.
 */
class AllowAdminOverride
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Get allowed emails from config
        $allowedEmails = config('admin.override_emails', []);
        
        // If user's email is in the override list, grant all admin permissions via Gate
        if (in_array($user->email, $allowedEmails)) {
            Gate::before(function ($user, $ability) use ($allowedEmails) {
                // Allow all permission checks for override users
                if (in_array($user->email, $allowedEmails)) {
                    return true;
                }
            });
        }
        
        return $next($request);
    }
}
