<?php

namespace App\Http\Controllers\Agents\Leads;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentLeadsController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Leads', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => [
                'fields' => $agent->settings('leads'),
            ],
        ]);
    }
}
