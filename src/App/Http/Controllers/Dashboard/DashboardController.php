<?php

namespace App\Http\Controllers\Dashboard;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard home page shell; its numbers load from
 * DashboardStatsController.
 */
class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard/home/pages/Dashboard');
    }
}
