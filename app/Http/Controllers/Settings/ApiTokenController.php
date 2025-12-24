<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Available abilities/permissions for API tokens
     */
    public static function availableAbilities(): array
    {
        return [
            [
                'value' => 'voucher:generate',
                'label' => 'Generate Vouchers',
                'description' => 'Create new vouchers with specified amounts and settings',
            ],
            [
                'value' => 'voucher:list',
                'label' => 'List Vouchers',
                'description' => 'View list of all vouchers',
            ],
            [
                'value' => 'voucher:view',
                'label' => 'View Voucher',
                'description' => 'View detailed information about a specific voucher',
            ],
            [
                'value' => 'voucher:cancel',
                'label' => 'Cancel Voucher',
                'description' => 'Cancel or void existing vouchers',
            ],
            [
                'value' => 'transaction:list',
                'label' => 'List Transactions',
                'description' => 'View list of all transactions',
            ],
            [
                'value' => 'transaction:view',
                'label' => 'View Transaction',
                'description' => 'View detailed information about a specific transaction',
            ],
            [
                'value' => 'transaction:export',
                'label' => 'Export Transactions',
                'description' => 'Export transaction data to CSV or other formats',
            ],
            [
                'value' => 'settings:view',
                'label' => 'View Settings',
                'description' => 'View account settings and configuration',
            ],
            [
                'value' => 'settings:update',
                'label' => 'Update Settings',
                'description' => 'Modify account settings and configuration',
            ],
            [
                'value' => 'contact:list',
                'label' => 'List Contacts',
                'description' => 'View list of all contacts',
            ],
            [
                'value' => 'contact:view',
                'label' => 'View Contact',
                'description' => 'View detailed information about a specific contact',
            ],
        ];
    }

    /**
     * Display API tokens management page
     */
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ]);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'availableAbilities' => self::availableAbilities(),
        ]);
    }

    /**
     * Create a new API token
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'in:' . implode(',', array_column(self::availableAbilities(), 'value'))],
            'expires_in_days' => ['nullable', 'integer', 'in:30,60,90,180,365'],
        ]);

        // Calculate expiration date
        $expiresAt = $validated['expires_in_days']
            ? Carbon::now()->addDays($validated['expires_in_days'])
            : null;

        // Create token
        $token = $request->user()->createToken(
            name: $validated['name'],
            abilities: $validated['abilities'],
            expiresAt: $expiresAt
        );

        // Get all tokens again to show the updated list
        $tokens = $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities ?? [],
                'last_used_at' => $t->last_used_at?->toISOString(),
                'expires_at' => $t->expires_at?->toISOString(),
                'created_at' => $t->created_at->toISOString(),
            ]);

        // Return to index page with plain text token (shown only once)
        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'availableAbilities' => self::availableAbilities(),
            'plainTextToken' => $token->plainTextToken,
        ]);
    }

    /**
     * Revoke an API token
     */
    public function destroy(Request $request, string $tokenId)
    {
        $token = $request->user()
            ->tokens()
            ->findOrFail($tokenId);

        $token->delete();

        return redirect()->route('settings.api-tokens.index')->with([
            'success' => 'API token has been revoked.',
        ]);
    }

    /**
     * Revoke all API tokens for the user
     */
    public function destroyAll(Request $request)
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return redirect()->route('settings.api-tokens.index')->with([
            'success' => "All {$count} API tokens have been revoked.",
        ]);
    }
}
