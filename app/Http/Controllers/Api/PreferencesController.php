<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    /**
     * Update user's voucher generation mode preference.
     */
    public function updateVoucherMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:simple,advanced',
        ]);

        $user = $request->user();
        $preferences = $user->ui_preferences ?? [];
        $preferences['voucher_generate_mode'] = $request->mode;
        
        $user->update([
            'ui_preferences' => $preferences,
        ]);

        return response()->json([
            'success' => true,
            'mode' => $request->mode,
        ]);
    }
}
