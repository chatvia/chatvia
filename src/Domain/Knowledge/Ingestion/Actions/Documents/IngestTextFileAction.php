<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Pandoc;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentChunkIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Ingest a text-based SourceFile by chunking its text directly via chunk.fit,
 * bypassing the LibreOffice→PDF→Mistral OCR document pipeline. Handles two
 * input shapes:
 *
 *   - raw text (.txt, .md/.markdown)        → read as-is
 *   - Pandoc-extractable (EPUB, LaTeX, BibTeX, FictionBook, Jupyter,
 *     DocBook/JATS XML, OPML, Troff)        → Pandoc → plain text first
 *
 * chunk.fit's `chunk_by_title` strategy (same one the website-Markdown path
 * uses) then partitions the text. Each chunk becomes one PageData and rides the
 * SHARED DocumentChunkIngestionJob batch — identical embedding, enhancement,
 * progress, and completion handling as the OCR path.
 */
class IngestTextFileAction
{
    public function __construct(
        private readonly ChunkFitClient $chunker,
        private readonly Pandoc $pandoc,
    ) {}

    public function execute(SourceFile $sourceFile, Agent $agent, ?string $internalJobId = null): void
    {
        if ($sourceFile->isIngested()) {
            return;
        }

        $file = $sourceFile->readFile();
        $extension = $sourceFile->extension();

        $text = Pandoc::supports($extension)
            ? $this->pandoc->toText($file->getPathname(), $extension)
            : $file->getContent();

        $pages = collect($this->chunker->splitByTitle($text))
            ->values()
            ->map(fn (string $chunk, int $index): PageData => new PageData(
                pageNumber: $index + 1,
                content: $chunk,
                checksum: md5($chunk),
            ));

        $sourceFile->update([
            'internal_ingestion_job_id' => $internalJobId,
            'parser' => 'chunkfit',
        ]);

        // Empty/whitespace-only input produces no chunks — finish cleanly so the
        // UI doesn't hang on "ingesting" waiting for a batch that never runs.
        if ($pages->isEmpty()) {
            $sourceFile->update([
                'status' => SourceStatus::Completed->value,
                'expected_chunks' => 0,
            ]);

            FileIngestionProgressed::dispatch($sourceFile, $agent->external_id);

            return;
        }

        $jobs = $pages->map(fn (PageData $page, int $index): DocumentChunkIngestionJob => new DocumentChunkIngestionJob(
            $agent,
            $sourceFile,
            $page,
            $index > 0 ? $pages[$index - 1] : null,
            $index < $pages->count() - 1 ? $pages[$index + 1] : null,
        ));

        SummaryIngestionJob::dispatch($sourceFile);

        $sourceFile->update([
            'status' => SourceStatus::Processing->value,
            'expected_chunks' => $pages->count(),
        ]);

        $agentFileId = $sourceFile->id;
        $agentExternalId = $agent->external_id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent Text File ID: '.$agentFileId)
            ->allowFailures() // one failed chunk must not cancel the batch
            ->finally(function (Batch $batch) use ($agentFileId, $agentExternalId): void {
                $file = SourceFile::find($agentFileId);

                if (! $file) {
                    return;
                }

                $file->update(['status' => SourceStatus::Completed->value]);

                // Authoritative "ingestion finished" signal — see IngestDocumentAction.
                FileIngestionProgressed::dispatch($file, $agentExternalId);
            })
            ->dispatch();

        $sourceFile->update([
            'internal_chunking_batch_id' => $batch->id,
        ]);
    }
}
