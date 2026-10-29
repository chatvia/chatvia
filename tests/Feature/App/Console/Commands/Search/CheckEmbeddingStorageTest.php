<?php

use Domain\Knowledge\Embedding\Enums\VectorType;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * chatvia:search:check-embedding-storage runs after the migrations on every start and
 * deploy: it stops the start when the embedding size or vector type was
 * changed after install. The columns are as
 * the testing database was built (EMBEDDING_VECTOR_TYPE/EMBEDDING_DIMENSIONS).
 */
beforeEach(function (): void {
    $this->stored = app(EmbeddingStorage::class)->columnType();
});

it('fails and names the columns when the size changed after install', function (): void {
    config(['chatvia.embeddings.dimensions' => app(EmbeddingStorage::class)->dimensions() === 768 ? 1024 : 768]);

    $this->artisan('chatvia:search:check-embedding-storage')
        ->expectsOutputToContain('The settings say '.app(EmbeddingStorage::class)->columnType().', but the database stores embeddings differently:')
        ->expectsOutputToContain("chunks.embedding: {$this->stored}")
        ->assertFailed();
});

it('fails when the vector type changed after install', function (): void {
    config(['chatvia.embeddings.type' => app(EmbeddingStorage::class)->type() === VectorType::Vector ? VectorType::Halfvec->value : VectorType::Vector->value]);

    $this->artisan('chatvia:search:check-embedding-storage')
        ->expectsOutputToContain("mcp_tools.embedding: {$this->stored}")
        ->assertFailed();
});
