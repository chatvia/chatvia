<?php

namespace App\Http\Controllers\Dashboard;

use Inertia\Inertia;

class DashboardController
{
    public function __invoke()
    {
        return Inertia::render('Dashboard');
    }
}
