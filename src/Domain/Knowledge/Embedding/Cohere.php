<?php

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Facades\Http;

/**
 * Embeddings from Cohere's hosted API (`services.cohere`).
 */
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

    /** embed-v4.0 returns one of these sizes (`output_dimension`). */
    protected function supportsDimensions(int $dimensions): bool
    {
        return in_array($dimensions, [256, 512, 1024, 1536], true);
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
