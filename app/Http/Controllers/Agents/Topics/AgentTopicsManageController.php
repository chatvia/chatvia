<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentTopicsManageController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(! has_permission_to_agent($agent, 'agent.topics.manage'), 403);

        return Inertia::render('agents/topics/Manage', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
