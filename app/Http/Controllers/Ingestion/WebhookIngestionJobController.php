<?php

namespace App\Http\Controllers\Ingestion;

use App\Models\SourceFile;
use Illuminate\Http\Request;

class WebhookIngestionJobController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'job_id' => ['required', 'exists:source_files,ingestion_job_id'],
            'status' => ['required', 'in:failed,ingested'],
        ]);

        SourceFile::where('ingestion_job_id', $request->job_id)
            ->first()
            ->update(['status' => $request->status]);
    }
}
