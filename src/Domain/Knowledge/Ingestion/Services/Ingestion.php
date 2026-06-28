<?php

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Bus;

class Ingestion
{
    public function ingest(SourceFile $sourceFile, Agent $agent): void
    {
        if ($sourceFile->status === SourceStatus::Completed->value) {
            return;
        }

        if ($sourceFile->status !== SourceStatus::Processing->value) {
            $sourceFile->update(['status' => SourceStatus::Processing->value]);
        }

        if ($sourceFile->isAudioFile() || $sourceFile->isVideoFile()) {
            dispatch(new MediaIngestionJob($sourceFile, $agent));
        } elseif ($sourceFile->isDirectChunkable() || $sourceFile->isEbook()) {
            // Raw text (.txt, .md) and structured-text ebooks (.epub, .fb2) are
            // chunk-ready once their text is extracted — chunk directly via
            // chunk.fit instead of paying for Mistral OCR. Ebooks have their
            // text pulled by Pandoc inside the action.
            dispatch(new TextFileIngestionJob($sourceFile, $agent));
        } else {
            // PDF, Office, and images are parsed by Mistral OCR.
            dispatch(new DocumentIngestionJob($sourceFile, $agent));
        }
    }

    public function cancel(SourceFile $agentFile): void
    {
        if ($agentFile->internal_chunking_batch_id) {
            Bus::findBatch($agentFile->internal_chunking_batch_id)->cancel();
        }
    }
}
