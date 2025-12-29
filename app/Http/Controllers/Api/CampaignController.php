<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Campaign Management
 *
 * Manage voucher generation campaigns. Campaigns are reusable templates that store
 * voucher configuration (amount, inputs, validations, feedback settings).
 *
 * @group Campaigns
 */
class CampaignController extends Controller
{
    /**
     * List Campaigns
     *
     * Get all active campaigns for the authenticated user. Campaigns are used as templates
     * when generating vouchers via the dashboard or API.
     *
     * @operationId listCampaigns
     * @authenticated
     * @response 200 [{"id":1,"name":"Standard Campaign","slug":"standard","instructions":{...}}]
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->select('id', 'name', 'slug', 'instructions')
            ->get();

        return response()->json($campaigns);
    }

    /**
     * Show Campaign
     *
     * Get details of a specific campaign by ID. Includes complete voucher instructions
     * configuration stored in the campaign template.
     *
     * @operationId showCampaign
     * @authenticated
     * @urlParam campaign integer required The campaign ID. Example: 1
     * @response 200 {"id":1,"name":"Standard Campaign","slug":"standard","instructions":{...},"created_at":"2025-12-29T00:00:00Z"}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404 {"message":"Campaign not found."}
     */
    public function show(Campaign $campaign): JsonResponse
    {
        Gate::authorize('view', $campaign);

        return response()->json($campaign);
    }
}
