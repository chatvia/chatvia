<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeFileController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.train.files'),
            code: 403,
        );

        return Inertia::render('agents/Sources/FilesSource', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
