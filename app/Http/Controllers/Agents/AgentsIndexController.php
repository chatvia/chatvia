<?php

namespace App\Http\Controllers\Agents;

use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentsIndexController
{
    public function __invoke(Request $request)
    {
        if ($request->expectsJson()) {
            return $request->user()->agents()->latest()->get();
        }

        return Inertia::render('agents/AgentsIndex');
    }
}
