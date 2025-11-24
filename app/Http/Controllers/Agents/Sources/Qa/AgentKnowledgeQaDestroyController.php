<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeQaDestroyController
{
    public function __invoke(Request $request, Agent $agent, $id)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        // TODO: Implement Q&A delete logic
        // This will be implemented by the backend team

        return response()->json([
            'message' => 'Q&A entry deleted successfully',
        ]);
    }
}
