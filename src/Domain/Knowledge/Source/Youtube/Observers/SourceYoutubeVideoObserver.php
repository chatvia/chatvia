<?php

namespace Domain\Knowledge\Source\Youtube\Observers;

use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * Cleans up after a deleted YouTube source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source.
 */
class SourceYoutubeVideoObserver
{
    public function deleted(SourceYoutubeVideo $video): void
    {
        $video->agents()->detach();
    }

    public function forceDeleted(SourceYoutubeVideo $video): void
    {
        $this->deleted($video);
    }
}
