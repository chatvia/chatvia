<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Youtube\Observers;

use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted YouTube source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source, and
 * wipes its storage folder ({@see KnowledgeStoragePath}), where a transcript
 * would be kept.
 *
 * The wipe waits until the caller's transaction commits (or runs at once when
 * there is none), so a rolled-back delete keeps the files its row points to.
 */
class SourceYoutubeVideoObserver
{
    public function deleted(SourceYoutubeVideo $video): void
    {
        $video->agents()->detach();

        $folder = KnowledgeStoragePath::forSource($video);

        DB::afterCommit(static fn (): bool => Storage::deleteDirectory($folder));
    }

    public function forceDeleted(SourceYoutubeVideo $video): void
    {
        $this->deleted($video);
    }
}
