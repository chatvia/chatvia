<?php

namespace App\Http\Controllers\Agents\Persona;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentShowPersonaController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Persona', ['agent' => $agent]);
    }
}
