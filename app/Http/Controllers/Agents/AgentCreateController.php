<?php

namespace App\Http\Controllers\Agents;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentCreateController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('agents/CreateAgent');
    }
}
