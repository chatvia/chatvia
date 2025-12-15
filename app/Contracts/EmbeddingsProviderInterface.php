<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface EmbeddingsProviderInterface
{
    /**
     * Generate embeddings for a single text.
     *
     * @param  string  $text  The text to embed
     * @param  bool  $returnAsText  Whether to return as text string or array
     * @return string|array The embedding vector
     */
    public function embed(string $text, bool $returnAsText = false): string|array;

    /**
     * Rerank documents based on query relevance.
     * Returns null if provider doesn't support reranking.
     *
     * @param  string  $query  The search query
     * @param  array  $documents  Array of documents to rerank
     * @param  int  $topN  Number of top results to return
     * @return array|null Array of reranked document indices or null if not supported
     */
    public function rerank(string $query, array $documents, int $topN = 6): ?array;

    /**
     * Check if the provider supports reranking.
     *
     * @return bool True if reranking is supported
     */
    public function supportsReranking(): bool;

    /**
     * Get the embedding dimensions for this provider.
     *
     * @return int The number of dimensions
     */
    public function getDimensions(): int;

    /**
     * Get the model name used for embeddings.
     *
     * @return string The model name
     */
    public function getModelName(): string;
}
