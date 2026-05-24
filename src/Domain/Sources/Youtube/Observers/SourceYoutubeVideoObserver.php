<?php

namespace Domain\Sources\Youtube\Observers;

use Domain\Sources\Youtube\Models\SourceYoutubeVideo;

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
        // Delete all chunks associated with this YouTube video
        $youtubeVideo->chunks()->delete();

        // Detach all agents (removes agent_youtube_video pivot records)
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
