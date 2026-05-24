<?php

namespace App\Embeddings;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;

abstract class AbstractEmbeddingsProvider implements EmbeddingsProviderInterface
{
    /**
     * Default implementation: no reranking support.
     *
     * @param  string  $query  The search query
     * @param  array  $documents  Array of documents to rerank
     * @return array|null Always returns null
     */
    public function rerank(string $query, array $documents): ?array
    {
        return null;
    }

    /**
     * Default implementation: no reranking support.
     *
     * @return bool False by default
     */
    public function supportsReranking(): bool
    {
        return false;
    }

    /**
     * Get the API endpoint.
     *
     * @param  string|null  $path  Optional path to append
     * @return string The full endpoint URL
     */
    protected function endpoint(?string $path = null): string
    {
        return rtrim($this->getBaseUrl(), '/').$path;
    }

    /**
     * Get the API key for the provider.
     *
     * @return string The API key
     */
    abstract protected function getApiKey(): string;

    /**
     * Get the base URL for the provider.
     *
     * @return string The base URL
     */
    abstract protected function getBaseUrl(): string;

    /**
     * Get the model name to use for embeddings.
     *
     * @return string The model name
     */
    abstract protected function getModel(): string;

    /**
     * Get the model name used for embeddings.
     *
     * @return string The model name
     */
    public function getModelName(): string
    {
        return $this->getModel();
    }
}
