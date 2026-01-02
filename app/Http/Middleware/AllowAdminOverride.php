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
 * Supports DUAL authorization:
 * 1. Role-based (recommended): Users with super-admin/admin/power-user roles
 * 2. Override emails (legacy): Emails in ADMIN_OVERRIDE_EMAILS env variable
 * 
 * This ensures backward compatibility during migration from .env to roles.
 */
class AllowAdminOverride
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Check role-based authorization first (recommended approach)
        $hasAdminRole = $user->hasAnyRole(['super-admin', 'admin', 'power-user']);
        
        // Check override email (legacy approach for backward compatibility)
        $allowedEmails = config('admin.override_emails', []);
        $isOverrideEmail = in_array($user->email, $allowedEmails);
        
        // Grant admin access if EITHER condition is true
        if ($hasAdminRole || $isOverrideEmail) {
            Gate::before(function ($gateUser, $ability) use ($allowedEmails) {
                // Check if user has admin role
                if ($gateUser->hasAnyRole(['super-admin', 'admin', 'power-user'])) {
                    return true;
                }
                
                // Check if user is in override email list
                if (in_array($gateUser->email, $allowedEmails)) {
                    return true;
                }
            });
        }
        
        return $next($request);
    }
}
