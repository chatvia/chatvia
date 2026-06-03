<?php

namespace Domain\Knowledge\Ingestion\Actions\Stats;

use Domain\Knowledge\Source\File\Models\SourceFile;

class UpdateIngestionJobStatusAction
{
    public function execute(string $jobId, string $status): void
    {
        SourceFile::where('ingestion_job_id', $jobId)
            ->first()
            ?->update(['status' => $status]);
    }
}
