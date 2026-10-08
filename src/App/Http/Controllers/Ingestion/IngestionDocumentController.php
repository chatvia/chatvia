<?php

namespace App\Http\Controllers\Ingestion;

use Domain\Knowledge\Ingestion\Actions\Documents\IngestDocumentChunkAction;
use Domain\Knowledge\Ingestion\Data\DocumentData;
use Illuminate\Http\Request;

class IngestionDocumentController
{
    public function __invoke(Request $request, IngestDocumentChunkAction $ingestChunk): void
    {
        // This endpoint ingests parsed document chunks into a knowledge base.
        // It is unauthenticated + CSRF-exempt; the VerifyIngestionSignature middleware
        // on the route rejects any body not signed by the pipeline, which stops
        // chunk injection into another agent's knowledge base.
        $payload = json_decode($request->getContent(), true);

        abort_unless(is_array($payload), 400);

        $ingestChunk->execute(DocumentData::from($payload));
    }
}
