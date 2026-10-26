<?php

declare(strict_types=1);

/**
 * chatvia:search:vector-indexes runs after the migrations on every start: it
 * rebuilds the chunk HNSW indexes when EMBEDDING_INDEX changed, and says so
 * when they already match.
 */
it('rebuilds the indexes when EMBEDDING_INDEX changed, and then leaves them alone', function (): void {
    config(['chatvia.embeddings.index' => 'full']);
    $this->artisan('chatvia:search:vector-indexes')->assertSuccessful();

    config(['chatvia.embeddings.index' => 'binary']);

    $this->artisan('chatvia:search:vector-indexes')
        ->expectsOutputToContain('Rebuilt as binary: chunks_embedding_hnsw, chunks_questions_embedding_hnsw.')
        ->assertSuccessful();

    $this->artisan('chatvia:search:vector-indexes')
        ->expectsOutputToContain('The vector indexes already match EMBEDDING_INDEX (binary).')
        ->assertSuccessful();
});
