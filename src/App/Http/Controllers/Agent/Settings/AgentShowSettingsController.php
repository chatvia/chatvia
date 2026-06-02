<?php

namespace App\Http\Controllers\Agent\Settings;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
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
