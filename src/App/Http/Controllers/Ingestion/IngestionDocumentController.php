<?php

namespace App\Http\Controllers\Ingestion;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use Domain\Ingestion\Data\DocumentData;
use Domain\Ingestion\Models\Chunk;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\Request;

class IngestionDocumentController
{
    public function __invoke(Request $request, EmbeddingsProviderInterface $embeddings): void
    {
        $rawJson = $request->getContent();

        $data = json_decode($rawJson, true);
        $data = DocumentData::from($data);

        Chunk::create([
            'chunkable_id' => $data->source_file_id,
            'chunkable_type' => SourceFile::class,
            'agent_id' => $data->agent_id,
            'type' => $data->chunk->type,
            'original' => $data->chunk->original,
            'enhanced' => $data->chunk->enhanced,
            'checksum' => $data->id,
            'embedding' => embed($data->chunk->enhanced),
            'embedding_model' => $embeddings->getModelName(),
            'keywords' => $data->keywords,
            'metadata' => $data->metadata,
        ]);
    }
}
