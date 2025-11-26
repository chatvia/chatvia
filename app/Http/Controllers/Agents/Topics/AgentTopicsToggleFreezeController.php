<?php

namespace App\Http\Controllers\Agents\Topics;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentTopicsToggleFreezeController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $validated = $request->validate([
            'is_frozen' => 'required|boolean',
        ]);

        // Update all agent_topics pivot records for this agent
        $agent->topics()->updateExistingPivot(
            $agent->topics()->pluck('chat_topics.id')->toArray(),
            ['is_frozen' => $validated['is_frozen']]
        );

        return response()->json([
            'message' => 'Topics freeze status updated successfully',
            'is_frozen' => $validated['is_frozen'],
        ]);
    }
}
