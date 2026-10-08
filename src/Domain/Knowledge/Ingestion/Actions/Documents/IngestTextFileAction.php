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
 * Ingest a text SourceFile by chunking its content directly via chunk.fit,
 * bypassing Mistral OCR — the text is already chunk-ready, so OCR would only
 * add cost and latency. Handles raw text (.txt, .md/.markdown) read as-is and
 * structured-text ebooks (.epub, .fb2) whose markdown is extracted by Pandoc.
 *
 * chunk.fit's `chunk_by_title` strategy (same one the website-Markdown path
 * uses) partitions the text. Each chunk becomes one PageData and rides the
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

        // Ebooks are structured text — extract their markdown with Pandoc so
        // chunk.fit's title-based splitting has headings to work with. Raw
        // text files are already chunk-ready and read as-is.
        $text = $sourceFile->isEbook()
            ? $sourceFile->withLocalFile(fn ($file): string => $this->pandoc->toMarkdown($file->getPathname(), $sourceFile->extension()))
            : $sourceFile->readContents();

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
