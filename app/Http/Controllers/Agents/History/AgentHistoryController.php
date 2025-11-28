<?php

namespace App\Http\Controllers\Agents\History;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentHistoryController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/History', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => $agent->settings('message_history'),
        ]);
    }
}
