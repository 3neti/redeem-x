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
        // Get vouchers where this contact is the owner (polymorphic relationship)
        // Vouchers are linked to contacts via owner_type and owner_id
        $vouchers = \LBHurtado\Voucher\Models\Voucher::query()
            ->where('owner_type', Contact::class)
            ->where('owner_id', $contact->id)
            ->whereNotNull('redeemed_at')
            ->orderByDesc('redeemed_at')
            ->get();

        // Transform to VoucherData DTOs
        $vouchersData = new DataCollection(VoucherData::class, $vouchers);

        return ApiResponse::success([
            'vouchers' => $vouchersData,
        ]);
    }
}
