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
     * Display the contacts page.
     *
     * @return Response
     */
    public function index(): Response
    {
        return Inertia::render('Contacts/Index');
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

}
