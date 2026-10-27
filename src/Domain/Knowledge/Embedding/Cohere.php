<?php

namespace Domain\Knowledge\Embedding;

use Illuminate\Support\Arr;
use Support\Http\ProviderRetry;

/**
 * Embeddings from Cohere's hosted API (`services.cohere`).
 */
class Cohere extends AbstractEmbeddingsProvider
{
    public function embed(string $text, bool $returnAsText = false): string|array
    {
        $embedding = ProviderRetry::request()->withToken($this->getApiKey())
            ->throw()
            ->post($this->endpoint('/embed'), [
                'model' => $this->getModel(),
                'texts' => [$text],
                'input_type' => 'search_document',
                'embedding_types' => ['float'],
                'output_dimension' => $this->getDimensions(),
            ])->json('embeddings.float.0');

        if ($returnAsText) {
            return '['.Arr::join($embedding, ',').']';
        }

        return $embedding;
    }

    /** embed-v4.0 returns one of these sizes (`output_dimension`). */
    protected function supportsDimensions(int $dimensions): bool
    {
        return collect([256, 512, 1024, 1536])->containsStrict($dimensions);
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
        return (string) config('services.cohere.embedding_model');
    }
}
