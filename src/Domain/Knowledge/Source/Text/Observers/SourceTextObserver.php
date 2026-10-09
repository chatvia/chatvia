<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Text\Observers;

use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * Cleans up after a deleted text source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source.
 */
class SourceTextObserver
{
    public function deleted(SourceText $sourceText): void
    {
        $sourceText->agents()->detach();
    }

    public function forceDeleted(SourceText $sourceText): void
    {
        $this->deleted($sourceText);
    }
}
