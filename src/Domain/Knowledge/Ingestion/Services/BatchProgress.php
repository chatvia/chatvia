<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Illuminate\Bus\Batch;

/**
 * The progress (0-100) of an ingestion batch once the job that asks is done.
 * The job asks while it still runs, so its own run is counted. The live
 * progress of a source comes from here, which costs one read of the batch row
 * instead of counting the source's chunks after every job.
 */
final class BatchProgress
{
    public static function afterCurrentJob(?Batch $batch): ?int
    {
        if ($batch === null || $batch->totalJobs === 0) {
            return null;
        }

        return (int) floor(min($batch->processedJobs() + 1, $batch->totalJobs) / $batch->totalJobs * 100);
    }
}
