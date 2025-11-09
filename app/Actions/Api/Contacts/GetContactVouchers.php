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
        // Get vouchers redeemed by this contact via the voucher_entity pivot table
        // The package creates voucher_entity entries when a voucher is redeemed
        $vouchers = \LBHurtado\Voucher\Models\Voucher::query()
            ->join('voucher_entity', 'vouchers.id', '=', 'voucher_entity.voucher_id')
            ->where('voucher_entity.entity_type', Contact::class)
            ->where('voucher_entity.entity_id', $contact->id)
            ->whereNotNull('vouchers.redeemed_at')
            ->orderByDesc('vouchers.redeemed_at')
            ->select('vouchers.*')
            ->get();

        // Transform to VoucherData DTOs
        $vouchersData = new DataCollection(VoucherData::class, $vouchers);

        return ApiResponse::success([
            'vouchers' => $vouchersData,
        ]);
    }
}
