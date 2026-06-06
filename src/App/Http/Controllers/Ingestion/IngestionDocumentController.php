<?php

namespace App\Http\Controllers\Ingestion;

use Domain\Knowledge\Ingestion\Actions\Documents\IngestDocumentChunkAction;
use Domain\Knowledge\Ingestion\Data\DocumentData;
use Illuminate\Http\Request;
use Support\Http\WebhookSignature;

class IngestionDocumentController
{
    public function __invoke(Request $request, IngestDocumentChunkAction $ingestChunk): void
    {
        // This endpoint ingests parsed document chunks into a knowledge base.
        // It is unauthenticated + CSRF-exempt, so it MUST verify the chunker
        // pipeline's HMAC over the raw body before doing any work — otherwise
        // anyone could inject chunks into any agent's KB (RAG poisoning) and
        // bill the victim's embedding usage.
        $raw = $request->getContent();

        abort_unless(
            WebhookSignature::verify($raw, $request->header('X-Auth-Hash'), $this->secret()),
            401,
        );

        $payload = json_decode($raw, true);

        abort_unless(is_array($payload), 400);

        $ingestChunk->execute(DocumentData::from($payload));
    }

    private function secret(): string
    {
        return (string) (config('services.ingestion.secret') ?: config('app.key'));
    }
}
