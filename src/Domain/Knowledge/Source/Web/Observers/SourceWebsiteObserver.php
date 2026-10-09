<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Observers;

use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Cleans up after a deleted website source. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook; this detaches the agents
 * that used it, so no `agent_sources` row points at a missing source.
 */
class SourceWebsiteObserver
{
    public function deleted(SourceWebsite $website): void
    {
        $website->agents()->detach();
    }

    public function forceDeleted(SourceWebsite $website): void
    {
        $this->deleted($website);
    }
}
