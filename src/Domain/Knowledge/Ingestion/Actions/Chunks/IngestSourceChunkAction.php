<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * The body of every chunk job of a file or text source (document pages, media
 * transcript parts, text parts): stores the job's pages as chunks, enhanced
 * as the source's setting says (see {@see ChunkEnhancement}), and reports the
 * source's progress to its page.
 *
 * Progress comes from the job batch (jobs done / jobs in the batch), so it
 * also moves for pages that were duplicates and made no chunk. The batch's
 * completion step sends the final 100 %.
 */
class IngestSourceChunkAction
{
    public function __construct(
        private readonly CreateSourceChunksAction $createChunks,
    ) {}

    public function execute(SourceChunkData $data): void
    {
        $this->createChunks->execute(new ChunkPagesData(
            source: $data->source,
            group: $data->group,
            enhancement: $data->enhancement ?? ChunkEnhancement::for($data->source),
            imageDirectory: KnowledgeStoragePath::forAgent($data->agent),
        ));

        if ($data->progress === null) {
            return;
        }

        match (true) {
            $data->source instanceof SourceFile => FileIngestionProgressed::dispatch($data->source, $data->agent->external_id, $data->progress),
            $data->source instanceof SourceText => TextIngestionProgressed::dispatch($data->source, $data->agent->external_id, $data->progress),
        };
    }
}
