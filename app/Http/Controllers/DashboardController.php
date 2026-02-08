<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard Controller
 *
 * Renders the main dashboard page.
 * Data is fetched via API endpoints on the frontend.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard');
    }
}
