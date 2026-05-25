<?php

namespace Domain\Ingestion\Services;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;

class Ingestion
{
    public function ingest(SourceFile $sourceFile, int|Agent $agent): void
    {
        if ($sourceFile->status === 'ingested') {
            return;
        }

        if ($sourceFile->status !== 'ingesting') {
            $sourceFile->update(['status' => 'ingesting']);
        }

        if (is_int($agent)) {
            $agent = Agent::find($agent);
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
