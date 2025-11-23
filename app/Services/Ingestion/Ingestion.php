<?php

namespace App\Services\Ingestion;

use App\Jobs\Ingestion\DocumentIngestionJob;
use App\Jobs\Ingestion\MediaIngestionJob;
use App\Models\SourceFile;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class Ingestion
{
    public function ingest(SourceFile $sourceFile, int $agentId): void
    {
        $sourceFile->update(['status' => 'ingesting']);

        if ($sourceFile->isAudioFile() || $sourceFile->isVideoFile()) {
            dispatch(new MediaIngestionJob($sourceFile, $agentId));
        } elseif ($sourceFile->isPdfFile() || $sourceFile->isWordFile()) {
            dispatch(new DocumentIngestionJob($sourceFile, $agentId));
        } else {
            throw new InvalidArgumentException('Invalid agent file type');
        }
    }

    public function cancel(SourceFile $agentFile): void
    {
        if ($agentFile->internal_chunking_batch_id) {
            Bus::findBatch($agentFile->internal_chunking_batch_id)->cancel();
        }
    }
}
