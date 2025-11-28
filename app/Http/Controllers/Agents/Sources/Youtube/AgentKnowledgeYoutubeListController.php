<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return $agent
            ->youtubeVideos()
            ->select('source_youtube_videos.id', 'url', 'title', 'status', 'error_message', 'source_youtube_videos.created_at')
            ->latest('source_youtube_videos.created_at')
            ->get();
    }
}
