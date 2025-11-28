<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeQaShowController
{
    public function __invoke(Request $request, Agent $agent, $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        // TODO: Implement Q&A show logic
        // This will be implemented by the backend team

        return response()->json([
            'id' => $id,
            'title' => '',
            'questions' => [],
            'answer' => '',
        ]);
    }
}
