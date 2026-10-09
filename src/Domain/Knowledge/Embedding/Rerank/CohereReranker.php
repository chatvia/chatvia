<?php

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Illuminate\Support\Facades\Http;

/**
 * Reranker that calls Cohere's `/rerank` endpoint. The endpoint and key are
 * shared with the Cohere embeddings; the model is `services.cohere.rerank_model`.
 */
class CohereReranker implements Reranker
{
    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        $payload = [
            'model' => (string) config('services.cohere.rerank_model'),
            'query' => $query,
            'max_tokens_per_doc' => 1000,
            'documents' => $documents,
        ];

        if ($topN !== null) {
            $payload['top_n'] = $topN;
        }

        /** @var list<array{index: int|string, relevance_score: float|string}> $results */
        $results = Http::withToken((string) config('services.cohere.api_key'))
            ->throw()
            ->post(rtrim((string) config('services.cohere.api_endpoint'), '/').'/rerank', $payload)
            ->json('results') ?? [];

        return array_values(array_map(RerankedDocumentData::fromResult(...), $results));
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
