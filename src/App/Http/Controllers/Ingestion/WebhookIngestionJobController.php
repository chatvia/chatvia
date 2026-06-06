<?php

namespace App\Http\Controllers\Ingestion;

use App\Http\Requests\Ingestion\UpdateIngestionJobStatusRequest;
use Domain\Knowledge\Source\File\Models\SourceFile;

class WebhookIngestionJobController
{
    public function __invoke(UpdateIngestionJobStatusRequest $request): void
    {
        SourceFile::where('ingestion_job_id', $request->string('job_id')->toString())
            ->first()
            ?->update(['status' => $request->string('status')->toString()]);
    }
}
