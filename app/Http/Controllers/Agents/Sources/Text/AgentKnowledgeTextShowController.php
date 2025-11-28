<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;

class AgentKnowledgeTextShowController
{
    public function __invoke(Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return $agent->texts()->where('source_texts.id', $id)->first();
    }
}
