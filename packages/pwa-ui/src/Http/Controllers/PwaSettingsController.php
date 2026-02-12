<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaSettingsController extends Controller
{
    /**
     * Display settings page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Pwa/Settings', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'avatar' => $user->avatar,
            ],
            'merchant' => $user->merchant ? [
                'name' => $user->merchant->name,
                'description' => $user->merchant->description,
            ] : null,
        ]);
    }
}
