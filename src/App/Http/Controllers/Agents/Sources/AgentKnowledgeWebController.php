<?php

namespace App\Http\Controllers\Agents\Sources;

use Domain\Agents\Data\Resources\AgentResourceData;
use App\Http\Controllers\Controller;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeWebController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.train.web'),
            code: 403,
        );

        return Inertia::render('agents/Sources/Web', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
