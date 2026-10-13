<?php

namespace Domain\Knowledge\Source\File\Observers;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted file source: detaches the agents that used it and
 * wipes its storage folder ({@see KnowledgeStoragePath}): the uploaded file,
 * its parser output (OCR pages or transcript) and the images saved from its
 * pages. Its chunks (summary included) are removed by the Chunkable trait's
 * `deleting` hook.
 *
 * The detach is a database write, so it joins the caller's transaction. The
 * storage wipe waits until that transaction commits (or runs at once when
 * there is none): a rolled-back delete, such as a failed agent delete, must
 * keep the files its row still points to.
 */
class SourceFileObserver
{
    public function deleted(SourceFile $sourceFile): void
    {
        $sourceFile->agents()->detach();

        $folder = KnowledgeStoragePath::forSource($sourceFile);

        DB::afterCommit(static fn (): bool => Storage::deleteDirectory($folder));
    }

    public function forceDeleted(SourceFile $sourceFile): void
    {
        $this->deleted($sourceFile);
    }
}
