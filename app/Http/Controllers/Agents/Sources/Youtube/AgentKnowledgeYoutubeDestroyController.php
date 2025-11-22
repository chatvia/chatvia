<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use App\Models\SourceYoutubeVideo;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeDestroyController
{
    public function __invoke(Request $request, Agent $agent, SourceYoutubeVideo $youtube)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        // Check if the video belongs to this agent
        abort_if(
            boolean: ! $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            code: 404,
        );

        // Delete associated chunks for this agent only
        $youtube->chunks()->where('agent_id', $agent->id)->delete();

        // Check if this video is used by other agents
        $otherAgentsCount = $youtube->agents()->where('agents.id', '!=', $agent->id)->count();

        if ($otherAgentsCount === 0) {
            // No other agents use this video, safe to delete
            $youtube->delete();
        } else {
            // Other agents still use this video, just detach from this agent
            $agent->youtubeVideos()->detach($youtube->id);
        }

        return response()->json([
            'message' => 'YouTube video deleted successfully.',
        ]);
    }
}
