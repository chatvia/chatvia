<?php

namespace App\Services\Infrastructure\Embeddings;

use App\Data\Chunk;
use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Jina extends AbstractEmbeddingsProvider
{
    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->getApiKey(),
        ])
            ->throw()
            ->post($this->endpoint('/embeddings'), [
                'model' => $this->getModel(),
                'task' => 'text-matching',
                'dimensions' => $this->getDimensions(),
                'input' => [$text],
            ]);

        $embedding = $response->json('data.0.embedding');

        if ($returnAsText) {
            return '['.implode(',', $embedding).']';
        }

        return $embedding;
    }

    public function getDimensions(): int
    {
        return config('services.jina.embedding_dimensions', 1024);
    }

    protected function getApiKey(): string
    {
        return config('services.jina.api_key');
    }

    protected function getBaseUrl(): string
    {
        return config('services.jina.api_endpoint', 'https://api.jina.ai/v1');
    }

    protected function getModel(): string
    {
        return config('services.jina.embedding_model', 'jina-embeddings-v3');
    }
}
