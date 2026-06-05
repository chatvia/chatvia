<?php

namespace Domain\Knowledge\Source\Youtube\Observers;

use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

class SourceYoutubeVideoObserver
{
    /**
     * Handle the SourceYoutubeVideo "created" event.
     */
    public function created(SourceYoutubeVideo $youtubeVideo): void
    {
        //
    }

    /**
     * Handle the SourceYoutubeVideo "updated" event.
     */
    public function updated(SourceYoutubeVideo $youtubeVideo): void
    {
        //
    }

    /**
     * Handle the SourceYoutubeVideo "deleted" event.
     */
    public function deleted(SourceYoutubeVideo $youtubeVideo): void
    {
        // Chunks (incl. summary) cleaned up by Chunkable::bootChunkable() via the
        // deleting event — FK cascade handles chunk_references.

        // Detach all agents (removes agent_sources polymorphic pivot rows)
        $youtubeVideo->agents()->detach();
    }

    /**
     * Handle the SourceYoutubeVideo "restored" event.
     */
    public function restored(SourceYoutubeVideo $youtubeVideo): void
    {
        //
    }

    /**
     * Handle the SourceYoutubeVideo "force deleted" event.
     */
    public function forceDeleted(SourceYoutubeVideo $youtubeVideo): void
    {
        $this->deleted($youtubeVideo);
    }
}
