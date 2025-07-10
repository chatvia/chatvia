<?php

namespace App\Jobs;

use App\Models\AgentFile;
use App\Support\Ingestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FileIngestionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public AgentFile $agentFile) {}

    public function handle(Ingestion $ingestion): void
    {
        $startTime = microtime(true);

        $this->agentFile->update([
            'status' => 'processing',
        ]);

        $successful = $ingestion->ingest($this->agentFile);

        $processingTime = round(microtime(true) - $startTime);

        $this->agentFile->update([
            'status' => $successful ? 'completed' : 'failed',
            'ingested_doc_processing_time_in_sec' => $processingTime,
        ]);
    }
}
