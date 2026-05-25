<?php

namespace App\Embeddings;

use Illuminate\Support\Facades\Http;

class Cohere extends AbstractEmbeddingsProvider
{
    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $embedding = Http::withToken($this->getApiKey())
            ->throw()
            ->post($this->endpoint('/embed'), [
                'model' => $this->getModel(),
                'texts' => [$text],
                'input_type' => 'search_document',
                'embedding_types' => ['float'],
                'output_dimension' => $this->getDimensions(),
            ])->json('embeddings.float.0');

        if ($returnAsText) {
            return '['.implode(',', $embedding).']';
        }

        return $embedding;
    }

    public function rerank(string $query, array $documents): ?array
    {
        $rerank = Http::withToken($this->getApiKey())
            ->throw()
            ->post($this->endpoint('/rerank'), [
                'model' => 'rerank-v4.0-pro',
                'query' => $query,
                'max_tokens_per_doc' => 1000,
                'documents' => $documents,
            ])->json('results');

        // Return array with index and relevance_score for each result
        return collect($rerank)
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
        return config('services.cohere.embedding_dimensions', 1024);
    }

    protected function getApiKey(): string
    {
        return config('services.cohere.api_key');
    }

    protected function getBaseUrl(): string
    {
        return config('services.cohere.api_endpoint');
    }

    protected function getModel(): string
    {
        return config('services.cohere.embedding_model', 'embed-v4.0');
    }
}
