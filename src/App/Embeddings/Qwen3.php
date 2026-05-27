<?php

namespace App\Embeddings;

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

        $embedding = $response->json('data.0.embedding');

        if ($returnAsText) {
            return '['.implode(',', $embedding).']';
        }

        return $embedding;
    }

    protected function doEmbedQuery(string $text): array
    {
        /** @var array<int, float> $vector */
        $vector = $this->embed('Instruct: '.self::QUERY_INSTRUCTION."\nQuery: ".$text);

        return $vector;
    }

    public function rerank(string $query, array $documents): ?array
    {
        $results = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->getApiKey(),
        ])
            ->throw()
            ->post($this->endpoint('/rerank'), [
                'model' => $this->getModel(),
                'query' => $query,
                'documents' => $documents,
            ])->json('results');

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
        return config('services.scalewayai.api_key');
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1';
    }

    protected function getModel(): string
    {
        return 'qwen3-embedding-8b';
    }
}
