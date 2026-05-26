<?php

namespace Domain\Ingestion\Actions;

use Domain\Sources\Files\Models\SourceFile;

class UpdateIngestionJobStatusAction
{
    public function execute(string $jobId, string $status): void
    {
        SourceFile::where('ingestion_job_id', $jobId)
            ->first()
            ?->update(['status' => $status]);
    }
}
