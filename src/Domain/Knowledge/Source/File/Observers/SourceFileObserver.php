<?php

namespace Domain\Knowledge\Source\File\Observers;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted file source: detaches the agents that used it and
 * deletes the uploaded file from storage. Its chunks (summary included) are
 * removed by the Chunkable trait's `deleting` hook.
 */
class SourceFileObserver
{
    public function deleted(SourceFile $sourceFile): void
    {
        $sourceFile->agents()->detach();

        if ($sourceFile->filepath) {
            Storage::delete($sourceFile->filepath);
        }
    }

    public function forceDeleted(SourceFile $sourceFile): void
    {
        $this->deleted($sourceFile);
    }
}
