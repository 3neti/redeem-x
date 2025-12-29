<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Settings\SecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // Only allow system user or admin override emails
            $user = $request->user();
            $systemUserId = config('account.system_user_id');
            $adminEmails = config('admin.override_emails', []);

            if ($user->id !== $systemUserId && !in_array($user->email, $adminEmails)) {
                abort(403, 'Only system administrators can access security settings.');
            }

            return $next($request);
        });
    }

    /**
     * Display security settings.
     */
    public function edit(Request $request): Response
    {
        $settings = app(SecuritySettings::class);

        return Inertia::render('settings/Security', [
            'security' => [
                'ip_whitelist_enabled' => $settings->ip_whitelist_enabled,
                'ip_whitelist' => $settings->ip_whitelist,
                'signature_enabled' => $settings->signature_enabled,
                'signature_secret' => $settings->signature_secret ? substr($settings->signature_secret, 0, 16) . '...' : null,
                'rate_limit_tier' => $settings->rate_limit_tier,
            ],
        ]);
    }

    /**
     * Update IP whitelist settings.
     */
    public function updateIpWhitelist(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'whitelist' => 'nullable|array',
            'whitelist.*' => 'nullable|string|max:255',
        ]);

        $settings = app(SecuritySettings::class);
        
        // Filter out empty values
        $whitelist = array_filter($validated['whitelist'] ?? [], fn($ip) => !empty(trim($ip)));

        $settings->ip_whitelist_enabled = $validated['enabled'];
        $settings->ip_whitelist = array_values($whitelist);
        $settings->save();

        return back()->with('status', 'ip-whitelist-updated');
    }

    /**
     * Generate new signature secret.
     */
    public function generateSignatureSecret(Request $request)
    {
        $settings = app(SecuritySettings::class);
        $secret = bin2hex(random_bytes(32)); // 64-char hex string

        $settings->signature_secret = $secret;
        $settings->save();

        return back()->with([
            'status' => 'signature-secret-generated',
            'secret' => $secret, // Show full secret once
        ]);
    }

    /**
     * Update signature settings.
     */
    public function updateSignature(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $settings = app(SecuritySettings::class);
        $settings->signature_enabled = $validated['enabled'];
        $settings->save();

        return back()->with('status', 'signature-updated');
    }
}
