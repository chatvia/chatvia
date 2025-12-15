<?php

namespace App\Services\Infrastructure\Embeddings;

use App\Data\Chunk;
use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
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

    public function rerank(string $query, array $documents, int $topN = 6): ?array
    {
        $rerank = Http::withToken($this->getApiKey())
            ->throw()
            ->post($this->endpoint('/rerank'), [
                'model' => 'rerank-v3.5',
                'query' => $query,
                'max_tokens_per_doc' => 1000,
                'documents' => $documents,
                'top_n' => $topN,
            ])->json('results');

        return collect($rerank)->pluck('index')->toArray();
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
