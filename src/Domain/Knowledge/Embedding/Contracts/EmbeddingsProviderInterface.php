<?php

namespace Domain\Knowledge\Embedding\Contracts;

interface EmbeddingsProviderInterface
{
    /**
     * Generate embeddings for a single text, treating it as a document
     * (content to be indexed and later retrieved).
     */
    public function embed(string $text, bool $returnAsText = false): string|array;

    /**
     * Generate document embeddings for many texts in as few provider calls
     * as possible. Returns one vector per input, in input order. Ingestion
     * uses this so a multi-page document doesn't pay one HTTP round-trip
     * per chunk.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Generate embeddings for a search query, applying any provider-specific
     * query-side conventions (Qwen3 instruction prefix, Cohere
     * input_type=search_query, etc.). Providers without such conventions
     * should delegate to embed().
     */
    public function embedQuery(string $text, bool $returnAsText = false): string|array;

    /**
     * Rerank documents based on query relevance.
     * Returns null if provider doesn't support reranking.
     *
     * @param  string  $query  The search query
     * @param  array  $documents  Array of documents to rerank
     * @param  int|null  $topN  Cap on results returned; null leaves the provider default in place
     * @return array|null Array of reranked documents with relevance scores or null if not supported
     */
    public function rerank(string $query, array $documents, ?int $topN = null): ?array;

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
