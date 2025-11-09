<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCampaignRequest;
use App\Http\Requests\Settings\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Enums\VoucherInputField;

class CampaignController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return Inertia::render('settings/Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('settings/Campaigns/Create', [
            'input_field_options' => VoucherInputField::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        $campaign = Campaign::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);

        // Use campaignVouchers relationship (no dependency on Voucher model having campaigns())
        $totalVouchers = $campaign->campaignVouchers()->count();
        $redeemedVouchers = $campaign->campaignVouchers()
            ->whereHas('voucher', fn($q) => $q->whereNotNull('redeemed_at'))
            ->count();

        return Inertia::render('settings/Campaigns/Show', [
            'campaign' => $campaign,
            'stats' => [
                'total_vouchers' => $totalVouchers,
                'redeemed' => $redeemedVouchers,
                'pending' => $totalVouchers - $redeemedVouchers,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        return Inertia::render('settings/Campaigns/Edit', [
            'campaign' => $campaign,
            'input_field_options' => VoucherInputField::options(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $campaign->update($request->validated());

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('settings.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    /**
     * Duplicate an existing campaign.
     */
    public function duplicate(Campaign $campaign)
    {
        $this->authorize('duplicate', $campaign);

        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->slug = null; // Will auto-generate
        $newCampaign->status = 'draft';
        $newCampaign->save();

        return redirect()->route('settings.campaigns.edit', $newCampaign)
            ->with('success', 'Campaign duplicated successfully.');
    }
}
