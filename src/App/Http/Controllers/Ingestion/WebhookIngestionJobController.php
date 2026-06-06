<?php

namespace App\Http\Controllers\Ingestion;

use App\Http\Requests\Ingestion\UpdateIngestionJobStatusRequest;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Support\Http\WebhookSignature;

class WebhookIngestionJobController
{
    public function __invoke(UpdateIngestionJobStatusRequest $request): void
    {
        // Unauthenticated + CSRF-exempt: verify the pipeline HMAC over the raw
        // body before mutating any ingestion-job status (prevents anyone from
        // flipping arbitrary source files' status).
        abort_unless(
            WebhookSignature::verify(
                $request->getContent(),
                $request->header('X-Auth-Hash'),
                (string) (config('services.ingestion.secret') ?: config('app.key')),
            ),
            401,
        );

        SourceFile::where('ingestion_job_id', $request->string('job_id')->toString())
            ->first()
            ?->update(['status' => $request->string('status')->toString()]);
    }
}
