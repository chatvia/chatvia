<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Illuminate\Support\Str;

/**
 * Embeddings through OpenRouter (`services.openrouter`), with the same key as
 * its chat models. Its `/embeddings` works like OpenAI's and serves models
 * from many companies; it asks each for the stored size (`dimensions`).
 * Qwen3 models get their query instruction, as on Scaleway AI.
 */
class OpenRouter extends OpenAi
{
    protected function doEmbedQuery(string $text): array
    {
        if (! Str::contains($this->getModel(), 'qwen3-embedding')) {
            return parent::doEmbedQuery($text);
        }

        /** @var array<int, float> $vector */
        $vector = $this->embed('Instruct: '.Qwen3::QUERY_INSTRUCTION."\nQuery: ".$text);

        return $vector;
    }

    protected function supportsDimensions(int $dimensions): bool
    {
        return OpenRouterEmbeddingModels::fits($this->getModel(), $dimensions);
    }

    protected function getApiKey(): string
    {
        return (string) config('services.openrouter.api_key');
    }

    protected function getBaseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected function getModel(): string
    {
        return (string) config('services.openrouter.embedding_model');
    }
}
