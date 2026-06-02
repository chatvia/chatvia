<?php

namespace Domain\Source\Web\Actions\Source;

use Domain\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\Bus;

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

        $website->chunks()->delete();
        $website->delete();
    }
}
