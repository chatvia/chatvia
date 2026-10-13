<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Observers;

use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted website source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source, and
 * wipes its storage folder ({@see KnowledgeStoragePath}) with the website
 * document.
 *
 * The wipe waits until the caller's transaction commits (or runs at once when
 * there is none), so a rolled-back delete keeps the file its row points to.
 */
class SourceWebsiteObserver
{
    public function deleted(SourceWebsite $website): void
    {
        $website->agents()->detach();

        $folder = KnowledgeStoragePath::forSource($website);

        DB::afterCommit(static fn (): bool => Storage::deleteDirectory($folder));
    }

    public function forceDeleted(SourceWebsite $website): void
    {
        $this->deleted($website);
    }
}
