<?php

namespace Domain\Knowledge\Embedding;

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
