<?php

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Facades\Http;

/**
 * Embeddings from Jina AI's hosted API (`services.jina`).
 */
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

    /** jina-embeddings-v3 returns any size up to 1024 (Matryoshka). */
    protected function supportsDimensions(int $dimensions): bool
    {
        return $dimensions >= 32 && $dimensions <= 1024;
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
