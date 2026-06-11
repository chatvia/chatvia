<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

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

        // Chunks (incl. summary) cleaned up by Chunkable::bootChunkable() via
        // the deleting event; the observer detaches agents on deleted. The
        // transaction keeps row delete + cascades all-or-nothing.
        DB::transaction(fn () => $website->delete());
    }
}
