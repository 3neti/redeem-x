<?php

declare(strict_types=1);

namespace App\Actions\Api\Contacts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Show contact details via API.
 *
 * Endpoint: GET /api/v1/contacts/{contact}
 */
class ShowContact
{
    use AsAction;

    public function asController(Contact $contact): JsonResponse
    {
        return ApiResponse::success([
            'contact' => [
                'id' => $contact->id,
                'mobile' => $contact->mobile,
                'name' => $contact->name,
                'email' => $contact->email,
                'country' => $contact->country,
                'bank_account' => $contact->bank_account,
                'updated_at' => $contact->updated_at?->toISOString(),
                'created_at' => $contact->created_at?->toISOString(),
            ],
        ]);
    }
}
