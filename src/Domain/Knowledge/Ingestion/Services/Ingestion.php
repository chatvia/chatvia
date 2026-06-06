<?php

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class Ingestion
{
    public function ingest(SourceFile $sourceFile, Agent $agent): void
    {
        if ($sourceFile->status === 'ingested') {
            return;
        }

        if ($sourceFile->status !== 'ingesting') {
            $sourceFile->update(['status' => 'ingesting']);
        }

        if ($sourceFile->isAudioFile() || $sourceFile->isVideoFile()) {
            dispatch(new MediaIngestionJob($sourceFile, $agent));
        } elseif ($sourceFile->isPdfFile() || $sourceFile->isWordFile()) {
            dispatch(new DocumentIngestionJob($sourceFile, $agent));
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
