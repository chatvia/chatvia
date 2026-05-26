<?php

namespace Domain\Sources\Youtube\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;

class DeleteAgentYoutubeVideoAction
{
    public function execute(Agent $agent, SourceYoutubeVideo $video): void
    {
        $video->chunks()->where('agent_id', $agent->id)->delete();

        $otherAgents = $video->agents()->where('agents.id', '!=', $agent->id)->exists();

        if (! $otherAgents) {
            $video->delete();
        } else {
            $agent->youtubeVideos()->detach($video->id);
        }
    }
}
