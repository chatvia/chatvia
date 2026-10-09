<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentEnhanceChunkJob;
use Domain\Knowledge\Ingestion\Jobs\Text\TextEnhanceChunkJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * The body of every per-chunk ingestion job (document pages, media transcript
 * parts, text parts): creates the chunk, reports progress, and queues the
 * enhancement step when it is on.
 *
 * Duplicate pages create no chunk, so they report no progress and are not
 * enhanced. Document pages are enhanced with their images and tables; other
 * chunks with their text only. Text sources are always enhanced; file sources
 * only when `chatvia.ai.chunker.enhance` is on.
 */
class IngestSourceChunkAction
{
    public function __construct(
        private readonly CreateSourceChunkAction $createChunk,
    ) {}

    public function execute(SourceChunkData $data): void
    {
        $chunk = $this->createChunk->execute($data->agent, $data->source, $data->page);

        if (! $chunk instanceof Chunk) {
            return;
        }

        $this->reportProgress($data);

        if ($data->source instanceof SourceText || config('chatvia.ai.chunker.enhance') === true) {
            $this->queueEnhancement($data, $chunk);
        }
    }

    private function reportProgress(SourceChunkData $data): void
    {
        $source = $data->source->refresh();

        match (true) {
            $source instanceof SourceFile => FileIngestionProgressed::dispatch($source, $data->agent->external_id),
            $source instanceof SourceText => TextIngestionProgressed::dispatch($source, $data->agent->external_id),
        };
    }

    private function queueEnhancement(SourceChunkData $data, Chunk $chunk): void
    {
        $isDocument = $data->source instanceof SourceFile
            && ! $data->source->isAudioFile()
            && ! $data->source->isVideoFile();

        $job = $isDocument
            ? new DocumentEnhanceChunkJob($data->agent, $data->source, $chunk, $data->page, $data->previousPage, $data->nextPage)
            : new TextEnhanceChunkJob($data->agent, $data->source, $chunk, $data->page, $data->previousPage, $data->nextPage);

        dispatch($job);
    }
}
