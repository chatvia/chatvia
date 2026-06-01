<?php

namespace App\Http\Controllers\Ingestion;

use App\Http\Requests\Ingestion\UpdateIngestionJobStatusRequest;
use Domain\Ingestion\Actions\Stats\UpdateIngestionJobStatusAction;

class WebhookIngestionJobController
{
    public function __invoke(
        UpdateIngestionJobStatusRequest $request,
        UpdateIngestionJobStatusAction $updateStatus,
    ): void {
        $updateStatus->execute(
            jobId: $request->string('job_id')->toString(),
            status: $request->string('status')->toString(),
        );
    }
}
