<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contacts;

use App\Http\Controllers\Controller;
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
     * Display the contacts page.
     */
    public function index(): Response
    {
        return Inertia::render('contacts/Index');
    }

    /**
     * Display the specified contact.
     */
    public function show(Contact $contact): Response
    {
        return Inertia::render('contacts/Show', [
            'contact' => $contact,
        ]);
    }
}
