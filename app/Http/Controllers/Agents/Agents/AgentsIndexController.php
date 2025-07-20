<?php

namespace App\Http\Controllers\Agents\Agents;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentsIndexController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('agents/AgentsIndex');
    }
}
