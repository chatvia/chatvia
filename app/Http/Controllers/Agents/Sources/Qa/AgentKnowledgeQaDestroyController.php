<?php

namespace App\Http\Controllers\Agents\Sources\Qa;

use App\Models\Agent;
use App\Models\AgentQuestionAnswer;
use Illuminate\Http\Request;

class AgentKnowledgeQaDestroyController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $qa = AgentQuestionAnswer::where('agent_id', $agent->id)
            ->findOrFail($id);

        $qa->delete();

        return response()->json([
            'message' => 'Q&A entry deleted successfully',
        ]);
    }
}
