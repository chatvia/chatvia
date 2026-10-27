<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Contracts;

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * Turns text into vectors for semantic search. The provider is chosen from
 * config (`chatvia.ai.models.embedding`). Reranking is a separate contract
 * ({@see Reranker}).
 */
interface EmbeddingsProvider
{
    /**
     * Generate embeddings for a single text, treating it as a document
     * (content to be indexed and later retrieved).
     *
     * @return ($returnAsText is true ? string : array<int, float>) the vector, or its pgvector text literal (`[0.1,0.2,...]`) when $returnAsText is true
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
     *
     * @return ($returnAsText is true ? string : array<int, float>) the vector, or its pgvector text literal (`[0.1,0.2,...]`) when $returnAsText is true
     */
    public function embedQuery(string $text, bool $returnAsText = false): string|array;

    /**
     * The size of the vectors this provider returns. It must equal the size
     * the database stores ({@see EmbeddingStorage}).
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
