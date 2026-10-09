<?php

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Facades\Http;

/**
 * Embeddings from Mistral's hosted API (`services.mistral`).
 */
class Mistral extends AbstractEmbeddingsProvider
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
        return 1024;
    }

    protected function getApiKey(): string
    {
        return config('services.mistral.api_key');
    }

    protected function getBaseUrl(): string
    {
        return config('services.mistral.base_url');
    }

    protected function getModel(): string
    {
        return (string) config('services.mistral.embedding_model');
    }
}
