<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use App\Models\AgentYoutubeVideo;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeRetrainController
{
    public function __invoke(Request $request, Agent $agent, AgentYoutubeVideo $youtube)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        abort_if(
            boolean: $youtube->agent_id !== $agent->id,
            code: 404,
        );

        $youtube->retrain();

        return response()->json([
            'message' => 'YouTube video retraining started.',
        ]);
    }
}
