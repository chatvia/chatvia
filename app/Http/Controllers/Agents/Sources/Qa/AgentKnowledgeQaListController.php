<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;

class AgentKnowledgeQaListController
{
    public function __invoke(Agent $agent)
    {
        // TODO: Implement Q&A list logic
        // This will be implemented by the backend team

        return response()->json([
            'data' => [],
        ]);
    }
}
