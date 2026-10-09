<?php

namespace App\Http\Controllers\Landing\Marketing;

use Illuminate\Contracts\View\View;

/**
 * The public mission page.
 */
class MissionController
{
    public function __invoke(): View
    {
        return view('landing.mission');
    }
}
