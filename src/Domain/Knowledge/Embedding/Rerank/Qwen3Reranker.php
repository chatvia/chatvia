<?php

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reranker that calls the `/rerank` endpoint of the Scaleway Generative APIs
 * (an open-weight Qwen3 model by default). The endpoint and key are shared
 * with the Qwen3 embeddings; the model is `services.scalewayai.rerank_model`.
 */
class Qwen3Reranker implements Reranker
{
    /** Results returned when the caller sets no cap. */
    private const int DEFAULT_TOP_N = 3;

    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey(),
        ])
            ->throw()
            ->post(Scaleway::baseUrl().'/rerank', [
                'model' => (string) config('services.scalewayai.rerank_model'),
                'query' => $query,
                'documents' => $documents,
                'top_n' => $topN ?? self::DEFAULT_TOP_N,
            ]);

        app(TokenUsageContext::class)->addFromResponse($response);

        /** @var list<array{index: int|string, relevance_score: float|string}> $results */
        $results = $response->json('results') ?? [];

        return array_values(array_map(RerankedDocumentData::fromResult(...), $results));
    }

    public function isEnabled(): bool
    {
        return true;
    }

    private function apiKey(): string
    {
        $key = config('services.scalewayai.api_key');

        // A clear message instead of a type error when the key is missing.
        // Callers catch it and fall back to the unranked candidates.
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The reranker API key is not configured (services.scalewayai.api_key).');
        }

        return $key;
    }
}
