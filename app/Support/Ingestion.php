<?php

namespace App\Support;

use App\Jobs\Ingestion\IngestionJob;
use App\Models\AgentFile;
use Illuminate\Support\Facades\Bus;

class Ingestion
{
    public function endpoint(): string
    {
        return rtrim(config('services.ingestion.api_endpoint'), '/');
    }

    public function ingest(AgentFile $agentFile): void
    {
        IngestionJob::dispatch($agentFile);
    }

    public function cancelJob(AgentFile $agentFile): void
    {
        Bus::findBatch($agentFile->internal_chunking_batch_id)->cancel();
    }
}
