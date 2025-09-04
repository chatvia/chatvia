<?php

namespace App\Http\Controllers\Agents\Persona;

use App\Enums\PermissionEnum;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentShowPersonaController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $user = $request->user();

        if ($user->missingPermission(PermissionEnum::PERSONA_VIEW)) {
            return back()->with('error', 'You do not have permission to view page');
        }

        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Persona', ['agent' => $agent]);
    }
}
