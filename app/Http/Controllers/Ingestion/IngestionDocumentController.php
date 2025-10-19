<?php

namespace App\Http\Controllers\Ingestion;

use App\Data\Ingestion\DocumentData;
use App\Models\Chunk;
use App\Support\Cohere;
use Illuminate\Http\Request;

class IngestionDocumentController
{
    public function __invoke(Request $request, Cohere $cohere)
    {
        $rawJson = $request->getContent();

        $data = json_decode($rawJson, true);
        $data = DocumentData::from($data);

        Chunk::create([
            'agent_file_id' => $data->agent_file_id,
            'agent_id' => $data->agent_id,
            'type' => $data->chunk->type->value,
            'original' => $data->chunk->original,
            'enhanced' => $data->chunk->enhanced,
            'checksum' => $data->id,
            'embedding' => $cohere->embed($data->chunk->enhanced),
            'keywords' => $data->keywords,
            'metadata' => $data->metadata,
        ]);
    }
}
