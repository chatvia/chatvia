<?php

namespace App\Support;

use App\Jobs\Ingestion\IngestionPdfJob;
use App\Jobs\Ingestion\MediaIngestionJob;
use App\Models\AgentFile;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class Ingestion
{
    public function endpoint(): string
    {
        return rtrim(config('services.ingestion.api_endpoint'), '/');
    }

    public function ingest(AgentFile $agentFile): void
    {
        $agentFile->update(['status' => 'processing']);

        if ($agentFile->isAudioFile() || $agentFile->isVideoFile()) {
            dispatch(new MediaIngestionJob($agentFile));
        } elseif ($agentFile->isPdfFile() || $agentFile->isWordFile()) {
            dispatch(new IngestionPdfJob($agentFile));
        } else {
            throw new InvalidArgumentException('Invalid agent file type');
        }
    }

    public function cancelJob(AgentFile $agentFile): void
    {
        if ($agentFile->internal_chunking_batch_id) {
            Bus::findBatch($agentFile->internal_chunking_batch_id)->cancel();
        }
    }
}
