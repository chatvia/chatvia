<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a website source: cancels its training batch if one is running,
 * deletes its chunks in batches, then deletes the row. Its agent links go
 * with the observer.
 */
class DeleteSourceWebsiteAction
{
    public function execute(SourceWebsite $website): void
    {
        if ($website->internal_chunking_batch_id) {
            $batch = Bus::findBatch($website->internal_chunking_batch_id);

            if ($batch && ! $batch->cancelled()) {
                $batch->cancel();
            }
        }

        // The chunks (summary included) go first, in batches and outside the
        // transaction, so a big website never deletes them all in one long
        // statement. The `deleting` hook (Chunkable) then removes any chunk a
        // job wrote in between; the observer detaches agents on deleted. The
        // transaction keeps the row delete and its cascades all-or-nothing.
        $website->deleteChunks();

        DB::transaction(fn () => $website->delete());
    }
}
