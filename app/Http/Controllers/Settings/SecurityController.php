<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Display security settings.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Security', [
            'security' => [
                'ip_whitelist_enabled' => $user->ip_whitelist_enabled,
                'ip_whitelist' => $user->ip_whitelist ?? [],
                'signature_enabled' => $user->signature_enabled,
                'signature_secret' => $user->signature_secret ? substr($user->signature_secret, 0, 16) . '...' : null,
                'rate_limit_tier' => $user->rate_limit_tier ?? 'basic',
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

        $user = $request->user();
        
        // Filter out empty values
        $whitelist = array_filter($validated['whitelist'] ?? [], fn($ip) => !empty(trim($ip)));

        $user->update([
            'ip_whitelist_enabled' => $validated['enabled'],
            'ip_whitelist' => array_values($whitelist),
        ]);

        return back()->with('status', 'ip-whitelist-updated');
    }

    /**
     * Generate new signature secret.
     */
    public function generateSignatureSecret(Request $request)
    {
        $user = $request->user();
        $secret = $user->generateSignatureSecret();

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

        $user = $request->user();

        if ($validated['enabled']) {
            $user->enableSignatureVerification();
        } else {
            $user->disableSignatureVerification();
        }

        return back()->with('status', 'signature-updated');
    }
}
