<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use App\Models\AgentYoutubeVideo;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeDestroyController
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

        // Delete associated chunks
        $youtube->chunks()->delete();

        // Delete the video
        $youtube->delete();

        return response()->json([
            'message' => 'YouTube video deleted successfully.',
        ]);
    }
}
