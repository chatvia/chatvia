<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;

class AgentKnowledgeQaListController
{
    public function __invoke(Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        // TODO: Implement Q&A list logic
        // This will be implemented by the backend team

        return response()->json([
            'data' => [],
        ]);
    }
}
