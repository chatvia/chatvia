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
        } elseif ($sourceFile->isDirectChunkable()) {
            // Text-based formats (.txt, .md, and the Pandoc-extractable exotic
            // formats: EPUB, LaTeX, BibTeX, FictionBook, Jupyter, DocBook/JATS,
            // OPML, Troff) are chunked directly via chunk.fit instead of being
            // round-tripped through LibreOffice→PDF→Mistral OCR.
            dispatch(new TextFileIngestionJob($sourceFile, $agent));
        } else {
            // PDF, Office, OpenDocument, and images go through Mistral OCR via
            // the document pipeline — it handles their layout/visual structure.
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
