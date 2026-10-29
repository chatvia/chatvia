<?php

use Domain\Knowledge\Embedding\Enums\VectorIndex;
use Domain\Knowledge\Embedding\Exceptions\InvalidEmbeddingStorageException;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * EmbeddingStorage turns EMBEDDING_VECTOR_TYPE / EMBEDDING_DIMENSIONS /
 * EMBEDDING_INDEX into the column type every embedding column and query cast
 * uses, and refuses any value the database or the HNSW index cannot hold.
 */
it('reads the vector type, size and index kind from the settings', function (): void {
    config(['chatvia.embeddings.type' => ' Vector ', 'chatvia.embeddings.dimensions' => '1536', 'chatvia.embeddings.index' => ' Full ']);

    $storage = new EmbeddingStorage;

    expect($storage->columnType())->toBe('vector(1536)')
        ->and($storage->type()->operatorClass())->toBe('vector_l2_ops')
        ->and($storage->index())->toBe(VectorIndex::Full);
});

it('rejects a vector type, size or index kind it cannot store', function (array $settings, string $method): void {
    config($settings);

    expect(fn (): mixed => (new EmbeddingStorage)->{$method}())->toThrow(InvalidEmbeddingStorageException::class);
})->with([
    'a float type' => [['chatvia.embeddings.type' => 'float'], 'type'],
    'a size of 0' => [['chatvia.embeddings.dimensions' => 0], 'dimensions'],
    'a size over 4000' => [['chatvia.embeddings.dimensions' => 4001], 'dimensions'],
    'a size that is not a whole number' => [['chatvia.embeddings.dimensions' => '1024.5'], 'dimensions'],
    'an ivfflat index' => [['chatvia.embeddings.index' => 'ivfflat'], 'index'],
]);

it('caps the size at what the HNSW index accepts for the type', function (): void {
    config(['chatvia.embeddings.type' => 'vector', 'chatvia.embeddings.dimensions' => 2048]);

    expect(fn (): int => (new EmbeddingStorage)->dimensions())
        ->toThrow(InvalidEmbeddingStorageException::class);

    config(['chatvia.embeddings.type' => 'halfvec']);

    expect((new EmbeddingStorage)->dimensions())->toBe(2048);
});
