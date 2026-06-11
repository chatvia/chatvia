<?php

namespace Domain\Knowledge\Source\Youtube\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

class DeleteAgentYoutubeVideoAction
{
    public function execute(Agent $agent, SourceYoutubeVideo $video): void
    {
        // Chunks are per-source, not per-agent: only delete them (via the
        // bootChunkable cascade, which also catches the summary chunk) when
        // no other agent still uses this video.
        $otherAgents = $video->agents()->where('agents.id', '!=', $agent->id)->exists();

        if (! $otherAgents) {
            $video->delete();
        } else {
            $agent->youtubeVideos()->detach($video->id);
        }
    }
}
