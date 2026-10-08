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
            // chunk-ready once their text is extracted — split them directly
            // instead of running the document parser. Ebooks have their text
            // pulled by Pandoc inside the action.
            dispatch(new TextFileIngestionJob($sourceFile, $agent));
        } else {
            // PDF, Office, and images are read by the configured DocumentParser.
            dispatch(new DocumentIngestionJob($sourceFile, $agent));
        }
    }

    /**
     * Cancel the file's chunking batch, if it still exists. Some older media
     * rows hold the batch id in `internal_ingestion_job_id`, so that column is
     * tried too; on every other row it is a queue job id and matches no batch.
     */
    public function cancel(SourceFile $agentFile): void
    {
        $batchIds = array_filter([
            $agentFile->internal_chunking_batch_id,
            $agentFile->internal_ingestion_job_id,
        ]);

        foreach ($batchIds as $batchId) {
            $batch = Bus::findBatch($batchId);

            if ($batch !== null) {
                $batch->cancel();

                return;
            }
        }
    }
}
