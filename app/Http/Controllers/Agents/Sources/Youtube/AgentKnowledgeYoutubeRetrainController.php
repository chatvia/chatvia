<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use App\Models\SourceYoutubeVideo;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeRetrainController
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

        $youtube->retrain($agent->id);

        return response()->json([
            'message' => 'YouTube video retraining started.',
        ]);
    }
}
