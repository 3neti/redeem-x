<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        
        // Get available features (only if user is super-admin)
        $availableFeatures = [];
        if ($user->hasRole('super-admin')) {
            $availableFeatures = $this->getAvailableFeatures($user);
        }
        
        return Inertia::render('settings/Profile', [
            'status' => $request->session()->get('status'),
            'available_features' => $availableFeatures,
            'reason' => $request->query('reason'),
            'return_to' => $request->query('return_to'),
        ]);
    }
    
    /**
     * Get list of available features with their current status.
     */
    protected function getAvailableFeatures(User $user): array
    {
        $features = [
            [
                'key' => 'advanced-pricing-mode',
                'name' => 'Advanced Pricing Mode',
                'description' => 'Show advanced pricing options in voucher generation with collapsible cards, location validation, and complex pricing rules.',
                'locked' => true, // Can't be manually toggled (role-based)
            ],
            [
                'key' => 'beta-features',
                'name' => 'Beta Features',
                'description' => 'Access experimental features before public release. These features are under active development and may change.',
                'locked' => false, // Can be toggled
            ],
        ];
        
        // Add current status for each feature
        return collect($features)->map(function ($feature) use ($user) {
            $feature['active'] = Feature::for($user)->active($feature['key']);
            return $feature;
        })->toArray();
    }

    /**
     * Update the user's profile settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'phone:PH,mobile'],
            'webhook' => ['nullable', 'url'],
        ]);

        $user = $request->user();
        
        // Update basic profile
        $user->update(['name' => $validated['name']]);
        
        // Update channels (mobile and webhook)
        $user->mobile = $validated['mobile'];
        
        if (!empty($validated['webhook'])) {
            $user->webhook = $validated['webhook'];
        } else {
            // Delete webhook if empty
            $user->setChannel('webhook', null);
        }

        // Check for return_to parameter (from middleware redirects)
        if ($returnTo = $request->query('return_to')) {
            return redirect($returnTo)->with('flash', [
                'type' => 'success',
                'message' => 'Profile updated! Continuing to your destination.',
            ]);
        }

        return to_route('profile.edit')->with('flash', [
            'type' => 'success',
            'message' => 'Profile updated successfully.',
        ]);
    }
    
    /**
     * Toggle a feature flag for the current user.
     */
    public function toggleFeature(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        // Only super-admins can manage feature flags
        if (!$user->hasRole('super-admin')) {
            abort(403, 'Only super-admins can manage feature flags.');
        }
        
        $validated = $request->validate([
            'feature' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);
        
        // Check if feature is locked (can't be manually toggled)
        $availableFeatures = $this->getAvailableFeatures($user);
        $feature = collect($availableFeatures)->firstWhere('key', $validated['feature']);
        
        if (!$feature) {
            return back()->withErrors(['feature' => 'Invalid feature.']);
        }
        
        if ($feature['locked']) {
            return back()->withErrors(['feature' => 'This feature is locked and cannot be manually toggled.']);
        }
        
        // Toggle feature
        if ($validated['enabled']) {
            Feature::for($user)->activate($validated['feature']);
        } else {
            Feature::for($user)->deactivate($validated['feature']);
        }
        
        return back()->with('status', 'feature-toggled');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(AuthKitAccountDeletionRequest $request): RedirectResponse
    {
        return $request->delete(
            using: fn (User $user) => $user->delete()
        );
    }
}
