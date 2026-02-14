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
        $primaryAlias = $user->primaryVendorAlias;
        $merchant = $user->merchant;

        // Get merchant display name if available
        $merchantDisplayName = null;
        if ($merchant) {
            $templateService = app(\App\Services\MerchantNameTemplateService::class);
            $template = $merchant->merchant_name_template
                ?? config('payment-gateway.qr_merchant_name.template', '{name} - {city}');
            $merchantDisplayName = $templateService->render($template, $merchant, $user);
        }

        return Inertia::render('Pwa/Settings', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'avatar' => $user->avatar,
            ],
            'merchant' => $merchant ? [
                'name' => $merchant->name,
                'description' => $merchant->description,
                'display_name' => $merchantDisplayName,
            ] : null,
            'vendorAlias' => $primaryAlias ? [
                'alias' => $primaryAlias->alias,
                'status' => $primaryAlias->status,
                'assigned_at' => $primaryAlias->assigned_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
