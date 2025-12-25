<?php

namespace App\Observers;

use App\Models\SourceFile;
use Illuminate\Support\Facades\Storage;

class SourceFileObserver
{
    public function created(SourceFile $agentFile): void {}

    public function updated(SourceFile $agentFile): void {}

    public function deleted(SourceFile $agentFile): void
    {
        // Delete all chunks associated with this file
        $agentFile->chunks()->delete();

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
