<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Transaction History Controller
 * 
 * Renders the transaction history page.
 * Data is fetched via API endpoints.
 */
class TransactionController extends Controller
{
    /**
     * Display the transaction history page.
     *
     * @return Response
     */
    public function index(): Response
    {
        return Inertia::render('transactions/Index');
    }
}
