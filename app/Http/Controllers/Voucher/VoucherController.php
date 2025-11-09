<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voucher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Voucher Management Controller
 * 
 * Handles listing, viewing, and exporting vouchers for authenticated users.
 */
class VoucherController extends Controller
{
    /**
     * Display the vouchers page.
     * 
     * Data is loaded via API from the frontend.
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Vouchers/Index');
    }

    /**
     * Display the specified voucher.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function show(Voucher $voucher): Response
    {
        $voucher->load(['owner', 'redeemers']);

        // Extract redemption inputs if voucher is redeemed
        $redemptionData = null;
        if ($voucher->redeemed_at && $voucher->redeemers->count() > 0) {
            $redeemer = $voucher->redeemers->first();
            $metadata = $redeemer->metadata ?? [];
            
            if (isset($metadata['redemption']['inputs'])) {
                $redemptionData = $metadata['redemption']['inputs'];
            }
        }

        return Inertia::render('Vouchers/Show', [
            'voucher' => VoucherData::fromModel($voucher),
            'redemption' => $redemptionData,
        ]);
    }

}
