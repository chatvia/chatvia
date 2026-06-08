<?php

namespace Domain\Knowledge\Ingestion\Actions\Documents;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Data\DocumentData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;

class IngestDocumentChunkAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(DocumentData $data): Chunk
    {
        return Chunk::create([
            'chunkable_id' => $data->source_file_id,
            'chunkable_type' => (new SourceFile)->getMorphClass(),
            'agent_id' => $data->agent_id,
            'original' => $data->chunk->original,
            'is_enhanced' => $data->chunk->enhanced,
            'checksum' => $data->id,
            'embedding' => embed($data->chunk->enhanced),
            'embedding_model' => $this->embeddings->getModelName(),
            'keywords' => $data->keywords,
            'metadata' => $data->metadata,
        ]);
    }
}
