<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Text\Observers;

use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted text source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source, and
 * wipes its storage folder ({@see KnowledgeStoragePath}) with the text file.
 *
 * The wipe waits until the caller's transaction commits (or runs at once when
 * there is none), so a rolled-back delete keeps the file its row points to.
 */
class SourceTextObserver
{
    public function deleted(SourceText $sourceText): void
    {
        $sourceText->agents()->detach();

        $folder = KnowledgeStoragePath::forSource($sourceText);

        DB::afterCommit(static fn (): bool => Storage::deleteDirectory($folder));
    }

    public function forceDeleted(SourceText $sourceText): void
    {
        $this->deleted($sourceText);
    }
}
