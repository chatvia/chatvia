<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;

class AgentKnowledgeYoutubeListController
{
    public function __invoke(Agent $agent)
    {
        return $agent
            ->youtubeVideos()
            ->select('id', 'url', 'title', 'status', 'error_message', 'created_at')
            ->latest()
            ->get();
    }
}
