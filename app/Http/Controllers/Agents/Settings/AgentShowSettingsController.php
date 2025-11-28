<?php

namespace App\Http\Controllers\Agents\Settings;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentShowSettingsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Persona', ['agent' => AgentResourceData::fromModel($agent)]);
    }
}
