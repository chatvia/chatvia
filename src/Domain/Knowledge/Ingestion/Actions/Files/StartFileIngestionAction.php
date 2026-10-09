<?php

namespace Domain\Knowledge\Ingestion\Actions\Files;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Media\MediaIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Queues the ingestion of an uploaded file on the pipeline that fits its type:
 *
 *   - audio and video → transcription ({@see MediaIngestionJob})
 *   - raw text (.txt, .md) and ebooks (.epub, .fb2) → direct split
 *     ({@see TextFileIngestionJob}); they are text already, so the document
 *     parser would only add cost
 *   - PDF, office files and images → the document parser
 *     ({@see DocumentIngestionJob})
 *
 * An already-ingested file is left alone.
 */
class StartFileIngestionAction
{
    public function execute(SourceFile $sourceFile, Agent $agent): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        if ($sourceFile->status !== SourceStatus::Processing) {
            $sourceFile->update(['status' => SourceStatus::Processing]);
        }

        $job = match (true) {
            $sourceFile->isAudioFile(), $sourceFile->isVideoFile() => new MediaIngestionJob($sourceFile, $agent),
            $sourceFile->isDirectChunkable(), $sourceFile->isEbook() => new TextFileIngestionJob($sourceFile, $agent),
            default => new DocumentIngestionJob($sourceFile, $agent),
        };

        dispatch($job);
    }
}
