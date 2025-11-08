<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;

/**
 * Contact Management Controller
 * 
 * Handles listing and viewing contacts who have redeemed vouchers.
 */
class ContactController extends Controller
{
    /**
     * Display a listing of contacts.
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Contact::query()
            ->orderByDesc('updated_at');

        // Search by name or mobile
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Paginate
        $contacts = $query->paginate(15)->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $request->input('search'),
            ],
            'stats' => $this->getContactStats(),
        ]);
    }

    /**
     * Display the specified contact.
     *
     * @param  Contact  $contact
     * @return Response
     */
    public function show(Contact $contact): Response
    {
        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
        ]);
    }

    /**
     * Get contact statistics.
     *
     * @return array
     */
    protected function getContactStats(): array
    {
        $total = Contact::count();
        $withEmail = Contact::whereNotNull('email')->count();
        $withName = Contact::whereNotNull('name')->count();

        return compact('total', 'withEmail', 'withName');
    }
}
