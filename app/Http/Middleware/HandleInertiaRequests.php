<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Debug permissions
        $permissions = [];
        if ($request->user()) {
            $permissions = $request->user()->getAllPermissions()->pluck('name')->toArray();
            \Log::info('HandleInertiaRequests permissions:', ['permissions' => $permissions, 'user' => $request->user()->email]);
        }

        $parentShare = parent::share($request);
        
        return array_merge($parentShare, [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user()?->load(['roles:name', 'permissions:name', 'wallet']),
                'roles' => $request->user()?->roles->pluck('name')->toArray() ?? [],
                'permissions' => $permissions,
                'is_admin_override' => $request->user() && in_array($request->user()->email, config('admin.override_emails', [])),
            ],
            'balance' => [
                'view_enabled' => config('balance.view_enabled', true),
                'view_role' => config('balance.view_role', 'admin'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sidebar' => [
                'balance' => config('sidebar.balance'),
            ],
            'redeem' => [
                'widget' => config('redeem.widget'),
            ],
        ]);
    }
}
