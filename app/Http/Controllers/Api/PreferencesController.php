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
        
        \Log::info('[PreferencesController] Before update', [
            'user_id' => $user->id,
            'requested_mode' => $request->mode,
            'current_preferences' => $user->ui_preferences,
        ]);
        
        $preferences = $user->ui_preferences ?? [];
        $preferences['voucher_generate_mode'] = $request->mode;
        
        $user->update([
            'ui_preferences' => $preferences,
        ]);
        
        // Refresh to get updated value from database
        $user->refresh();
        
        \Log::info('[PreferencesController] After update', [
            'user_id' => $user->id,
            'saved_mode' => $request->mode,
            'updated_preferences' => $user->ui_preferences,
            'mode_value_in_db' => $user->ui_preferences['voucher_generate_mode'] ?? 'NOT_SET',
        ]);

        return response()->json([
            'success' => true,
            'mode' => $request->mode,
        ]);
    }
}
