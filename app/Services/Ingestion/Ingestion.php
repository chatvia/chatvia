<?php

namespace App\Services\Ingestion;

use App\Jobs\Ingestion\DocumentIngestionJob;
use App\Jobs\Ingestion\MediaIngestionJob;
use App\Models\AgentFile;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class Ingestion
{
    public function ingest(AgentFile $agentFile): void
    {
        $agentFile->update(['status' => 'processing']);

        if ($agentFile->isAudioFile() || $agentFile->isVideoFile()) {
            dispatch(new MediaIngestionJob($agentFile));
        } elseif ($agentFile->isPdfFile() || $agentFile->isWordFile()) {
            dispatch(new DocumentIngestionJob($agentFile));
        } else {
            throw new InvalidArgumentException('Invalid agent file type');
        }
    }

    public function cancel(AgentFile $agentFile): void
    {
        if ($agentFile->internal_chunking_batch_id) {
            Bus::findBatch($agentFile->internal_chunking_batch_id)->cancel();
        }
    }
}
