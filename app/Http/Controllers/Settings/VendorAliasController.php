<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AssignVendorAliasRequest;
use App\Http\Requests\Settings\UpdateVendorAliasRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Merchant\Actions\AssignVendorAlias;
use LBHurtado\Merchant\Models\VendorAlias;

class VendorAliasController extends Controller
{
    /**
     * Display a listing of vendor aliases.
     */
    public function index(Request $request): Response
    {
        $query = VendorAlias::query()
            ->with(['owner:id,name,email', 'assignedBy:id,name'])
            ->orderByDesc('assigned_at');
        
        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('alias', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        
        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        
        $aliases = $query->paginate(15)->withQueryString();
        
        return Inertia::render('settings/vendor-aliases/Index', [
            'aliases' => $aliases,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'config' => [
                'min_length' => config('merchant.alias.min_length', 3),
                'max_length' => config('merchant.alias.max_length', 8),
            ],
        ]);
    }
    
    /**
     * Store a newly created vendor alias.
     */
    public function store(AssignVendorAliasRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        try {
            AssignVendorAlias::run(
                ownerUserId: $validated['user_id'],
                alias: $validated['alias'],
                assignedByUserId: $request->user()->id,
                notes: $validated['notes'] ?? null
            );
            
            return back()->with('success', 'Vendor alias assigned successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['alias' => $e->getMessage()]);
        }
    }
    
    /**
     * Update the specified vendor alias.
     */
    public function update(UpdateVendorAliasRequest $request, VendorAlias $vendorAlias): RedirectResponse
    {
        $validated = $request->validated();
        
        $vendorAlias->update($validated);
        
        return back()->with('success', 'Vendor alias updated successfully.');
    }
    
    /**
     * Remove the specified vendor alias.
     */
    public function destroy(VendorAlias $vendorAlias): RedirectResponse
    {
        $vendorAlias->delete();
        
        return back()->with('success', 'Vendor alias deleted successfully.');
    }
    
    /**
     * List active vendor aliases for API/dropdown use.
     */
    public function list(Request $request)
    {
        $aliases = VendorAlias::query()
            ->where('status', 'active')
            ->with('owner:id,name,email')
            ->orderBy('alias')
            ->get()
            ->map(fn($alias) => [
                'id' => $alias->id,
                'alias' => $alias->alias,
                'owner_name' => $alias->owner?->name,
            ]);
        
        return response()->json(['aliases' => $aliases]);
    }
    
    /**
     * Search users for typeahead (AJAX endpoint).
     */
    public function searchUsers(Request $request)
    {
        $query = $request->input('query', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }
        
        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'email')
            ->limit(10)
            ->get();
        
        return response()->json($users);
    }
}
