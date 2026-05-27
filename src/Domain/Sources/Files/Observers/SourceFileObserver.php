<?php

namespace Domain\Sources\Files\Observers;

use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Support\Facades\Storage;

class SourceFileObserver
{
    public function created(SourceFile $agentFile): void {}

    public function updated(SourceFile $agentFile): void {}

    public function deleted(SourceFile $agentFile): void
    {
        // Chunks (incl. summary) cleaned up by Chunkable::bootChunkable() via the
        // deleting event — FK cascade handles chunk_references.

        // Detach all agents (removes agent_sources pivot records)
        $agentFile->agents()->detach();

        // Delete the actual file from storage
        if ($agentFile->filepath) {
            Storage::delete($agentFile->filepath);
        }
    }

    public function restored(SourceFile $agentFile): void {}

    public function forceDeleted(SourceFile $agentFile): void
    {
        $this->deleted($agentFile);
    }
}
