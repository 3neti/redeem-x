<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Settings\VoucherSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    /**
     * Show the user's preferences settings page.
     */
    public function edit(Request $request, VoucherSettings $settings): Response
    {
        return Inertia::render('settings/Preferences', [
            'preferences' => [
                'default_amount' => $settings->default_amount,
                'default_expiry_days' => $settings->default_expiry_days,
                'default_rider_url' => $settings->default_rider_url,
                'default_success_message' => $settings->default_success_message,
            ],
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's preferences.
     */
    public function update(Request $request, VoucherSettings $settings): RedirectResponse
    {
        $request->validate([
            'default_amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'default_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'default_rider_url' => ['nullable', 'url', 'max:500'],
            'default_success_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings->default_amount = (int) $request->default_amount;
        $settings->default_expiry_days = $request->default_expiry_days;
        $settings->default_rider_url = $request->default_rider_url ?: config('app.url');
        $settings->default_success_message = $request->default_success_message ?: 'Thank you for redeeming your voucher! The cash will be transferred shortly.';
        
        $settings->save();

        return to_route('preferences.edit')->with('status', 'Preferences updated successfully!');
    }
}
