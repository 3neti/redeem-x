<?php

declare(strict_types=1);

namespace App\Actions\Api\Contacts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * List contacts via API.
 *
 * Endpoint: GET /api/v1/contacts
 */
class ListContacts
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 100);
        $search = $request->input('search');

        $query = Contact::query()
            ->orderByDesc('updated_at');

        // Search by mobile only (name/email are schemaless attributes in meta JSON)
        if ($search) {
            $query->where('mobile', 'like', "%{$search}%");
        }

        $contacts = $query->paginate($perPage);

        // Get stats (name/email are schemaless, can't query at DB level)
        $stats = [
            'total' => Contact::count(),
        ];

        // Transform to array (ContactData doesn't have email/id, so use array)
        $contactsData = $contacts->items();
        $contactsArray = array_map(function ($contact) {
            return [
                'id' => $contact->id,
                'mobile' => $contact->mobile,
                'name' => $contact->name,
                'email' => $contact->email,
                'country' => $contact->country,
                'bank_account' => $contact->bank_account,
                'updated_at' => $contact->updated_at?->toISOString(),
                'created_at' => $contact->created_at?->toISOString(),
            ];
        }, $contactsData);

        return ApiResponse::success([
            'data' => $contactsArray,
            'pagination' => [
                'current_page' => $contacts->currentPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
                'last_page' => $contacts->lastPage(),
                'from' => $contacts->firstItem(),
                'to' => $contacts->lastItem(),
            ],
            'filters' => [
                'search' => $search,
            ],
            'stats' => $stats,
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
