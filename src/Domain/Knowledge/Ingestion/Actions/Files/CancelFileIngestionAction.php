<?php

namespace Domain\Knowledge\Ingestion\Actions\Files;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Bus;

/**
 * Cancels a file's chunk batch, if it still exists, so a file deleted while it
 * is being ingested stops creating chunks. Some older media rows hold the batch
 * id in `internal_ingestion_job_id`, so that column is tried too; on every
 * other row it is a queue job id and matches no batch.
 */
class CancelFileIngestionAction
{
    public function execute(SourceFile $sourceFile): void
    {
        $batchIds = array_filter([
            $sourceFile->internal_chunking_batch_id,
            $sourceFile->internal_ingestion_job_id,
        ]);

        foreach ($batchIds as $batchId) {
            $batch = Bus::findBatch($batchId);

            if ($batch !== null) {
                $batch->cancel();

                return;
            }
        }
    }
}
