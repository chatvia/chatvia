<?php

namespace App\Services\Infrastructure\Embeddings;

use Illuminate\Support\Facades\Http;

class Qwen3 extends AbstractEmbeddingsProvider
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
                'input' => $text,
                'dimensions' => $this->getDimensions(),
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
