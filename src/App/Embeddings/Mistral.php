<?php

namespace App\Embeddings;

use Illuminate\Support\Facades\Http;

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
        return 'mistral-embed';
    }
}
