<?php

namespace Domain\Knowledge\Source\File\Observers;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cleans up after a deleted file source: detaches the agents that used it and
 * deletes the uploaded file and its parser output (OCR pages or transcript)
 * from storage. Its chunks (summary included) are removed by the Chunkable
 * trait's `deleting` hook.
 *
 * The detach is a database write, so it joins the caller's transaction. The
 * storage delete waits until that transaction commits (or runs at once when
 * there is none): a rolled-back delete, such as a failed agent delete, must
 * keep the file its row still points to.
 */
class SourceFileObserver
{
    public function deleted(SourceFile $sourceFile): void
    {
        $sourceFile->agents()->detach();

        // parser_filepath is unique per row, so no other source shares it.
        $paths = array_values(array_filter([$sourceFile->filepath, $sourceFile->parser_filepath]));

        if ($paths !== []) {
            DB::afterCommit(static fn (): bool => Storage::delete($paths));
        }
    }

    public function forceDeleted(SourceFile $sourceFile): void
    {
        $this->deleted($sourceFile);
    }
}
