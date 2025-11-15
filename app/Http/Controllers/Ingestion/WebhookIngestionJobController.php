<?php

namespace App\Http\Controllers\Ingestion;

use App\Models\AgentFile;
use Illuminate\Http\Request;

class WebhookIngestionJobController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'job_id' => ['required', 'exists:agent_files,ingestion_job_id'],
            'status' => ['required', 'in:failed,ingested'],
        ]);

        AgentFile::where('ingestion_job_id', $request->job_id)
            ->first()
            ->update(['status' => $request->status]);
    }
}
