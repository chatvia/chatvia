<?php

namespace App\Http\Controllers\Ingestion;

use Domain\Ingestion\Actions\IngestDocumentChunkAction;
use Domain\Ingestion\Data\DocumentData;
use Illuminate\Http\Request;

class IngestionDocumentController
{
    public function __invoke(Request $request, IngestDocumentChunkAction $ingestChunk): void
    {
        $payload = json_decode($request->getContent(), true);
        $data = DocumentData::from($payload);

        $ingestChunk->execute($data);
    }
}
