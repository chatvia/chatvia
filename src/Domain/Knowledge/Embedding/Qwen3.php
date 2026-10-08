<?php

namespace Domain\Knowledge\Embedding;

use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Usage\TokenUsageContext;
use Illuminate\Support\Facades\Http;

class Qwen3 extends AbstractEmbeddingsProvider
{
    /**
     * Instruction prefix recommended by the Qwen3-Embedding paper for
     * retrieval queries. Documents are embedded without any prefix.
     */
    private const string QUERY_INSTRUCTION = 'Given a search query, retrieve relevant passages that answer the query';

    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->getApiKey(),
        ])
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'input' => $text,
                'dimensions' => $this->getDimensions(),
            ]);

        app(TokenUsageContext::class)->addFromResponse($response);

        $embedding = $response->json('data.0.embedding');

        if ($returnAsText) {
            return '['.implode(',', $embedding).']';
        }

        return $embedding;
    }

    /**
     * One request, many documents — the OpenAI-compatible /embeddings
     * endpoint accepts an input array and returns data[] in input order.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->getApiKey(),
        ])
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'input' => array_values($texts),
                'dimensions' => $this->getDimensions(),
            ]);

        app(TokenUsageContext::class)->addFromResponse($response);

        $data = $response->json('data');

        // data[] carries an `index` per item; sort defensively so the result
        // is positionally aligned with the input even if the API reorders.
        usort($data, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }

    protected function doEmbedQuery(string $text): array
    {
        /** @var array<int, float> $vector */
        $vector = $this->embed('Instruct: '.self::QUERY_INSTRUCTION."\nQuery: ".$text);

        return $vector;
    }

    public function rerank(string $query, array $documents, ?int $topN = null): ?array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->getApiKey(),
        ])
            ->throw()
            ->post($this->endpoint('/rerank'), [
                'model' => $this->getModel(),
                'query' => $query,
                'documents' => $documents,
                'top_n' => $topN ?? 3,
            ]);

        app(TokenUsageContext::class)->addFromResponse($response);

        $results = $response->json('results');

        return collect($results)
            ->map(fn ($item): array => [
                'index' => $item['index'],
                'relevance_score' => $item['relevance_score'],
            ])
            ->toArray();
    }

    public function supportsReranking(): bool
    {
        return true;
    }

    public function getDimensions(): int
    {
        return 1024;
    }

    protected function getApiKey(): string
    {
        $key = config('services.scalewayai.api_key');

        // Fail with a clear message rather than a confusing "Return value must
        // be of type string, null returned" TypeError when the key is unset
        // (e.g. a misconfigured prod env). Callers catch + degrade gracefully.
        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('Scaleway AI API key is not configured (services.scalewayai.api_key).');
        }

        return $key;
    }

    protected function getBaseUrl(): string
    {
        return Scaleway::baseUrl();
    }

    protected function getModel(): string
    {
        return 'qwen3-embedding-8b';
    }
}
