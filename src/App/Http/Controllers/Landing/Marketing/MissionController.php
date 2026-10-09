<?php

namespace App\Http\Controllers\Landing\Marketing;

use Illuminate\Contracts\View\View;

/**
 * Renders the public mission page of the marketing site. It is a static view
 * with no data.
 */
class MissionController
{
    public function __invoke(): View
    {
        return view('landing.mission');
    }
}
