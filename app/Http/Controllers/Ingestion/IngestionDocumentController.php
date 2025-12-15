<?php

namespace App\Http\Controllers\Ingestion;

use App\Contracts\EmbeddingsProviderInterface;
use App\Data\Ingestion\DocumentData;
use App\Models\Chunk;
use App\Models\SourceFile;
use Illuminate\Http\Request;

class IngestionDocumentController
{
    public function __invoke(Request $request, EmbeddingsProviderInterface $embeddings)
    {
        $rawJson = $request->getContent();

        $data = json_decode($rawJson, true);
        $data = DocumentData::from($data);

        Chunk::create([
            'chunkable_id' => $data->source_file_id,
            'chunkable_type' => SourceFile::class,
            'agent_id' => $data->agent_id,
            'type' => $data->chunk->type->value,
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
