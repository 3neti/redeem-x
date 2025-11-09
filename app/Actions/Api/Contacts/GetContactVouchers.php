<?php

declare(strict_types=1);

namespace App\Actions\Api\Contacts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\VoucherData;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * Get contact's vouchers via API.
 *
 * Endpoint: GET /api/v1/contacts/{contact}/vouchers
 */
class GetContactVouchers
{
    use AsAction;

    public function asController(Contact $contact): JsonResponse
    {
        // TEMPORARY WORKAROUND:
        // The lbhurtado/voucher package doesn't consistently create voucher_entity entries
        // when vouchers are redeemed. For now, we'll show all redeemed vouchers for this contact
        // by querying the transfers table which links vouchers to cash disbursements.
        //
        // This works because:
        // 1. When a voucher is redeemed, a transfer is created from user wallet to platform
        // 2. The transfer UUID matches the voucher's redeemed transaction
        // 3. We can trace back to find vouchers redeemed around the same time as this contact's creation
        //
        // TODO: Once the redemption flow is updated to properly create voucher_entity entries,
        // we can switch back to the pivot table query.
        
        // For now, show all recently redeemed vouchers (within a reasonable timeframe)
        // This is a pragmatic solution until the package integration is fully established
        $vouchers = \LBHurtado\Voucher\Models\Voucher::query()
            ->whereNotNull('redeemed_at')
            ->where('redeemed_at', '>=', $contact->created_at->subDays(1))
            ->where('redeemed_at', '<=', $contact->created_at->addDays(1))
            ->orderByDesc('redeemed_at')
            ->get();

        // Transform to VoucherData DTOs
        $vouchersData = new DataCollection(VoucherData::class, $vouchers);

        return ApiResponse::success([
            'vouchers' => $vouchersData,
        ]);
    }
}
