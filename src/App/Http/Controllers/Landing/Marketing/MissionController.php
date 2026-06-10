<?php

namespace App\Http\Controllers\Landing\Marketing;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class MissionController
{
    public function __invoke(): Factory|View
    {
        return view('landing.mission');
    }
}
