<?php

namespace Domain\Knowledge\Source\Youtube\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * Removes a YouTube video from an agent. The video and its chunks are deleted
 * only when no other agent still uses it; otherwise the agent is just
 * detached.
 */
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
