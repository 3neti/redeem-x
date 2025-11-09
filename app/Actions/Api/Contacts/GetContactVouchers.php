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
        // Get vouchers redeemed by this contact
        $vouchers = $contact->vouchers()->orderByDesc('redeemed_at')->get();

        // Transform to VoucherData DTOs
        $vouchersData = new DataCollection(VoucherData::class, $vouchers);

        return ApiResponse::success([
            'vouchers' => $vouchersData,
        ]);
    }
}
