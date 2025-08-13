<?php

namespace App\Http\Controllers\Agents\Starters;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StartersShowController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        if ($request->expectsJson()) {
            return $agent->starters->toArray();
        }

        return Inertia::render('agents/Starters', [
            'agent' => $agent,
            'starters' => $agent->starters->toArray(),
        ]);
    }
}
