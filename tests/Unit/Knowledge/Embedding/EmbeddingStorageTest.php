<?php

use Domain\Knowledge\Embedding\Enums\VectorType;
use Domain\Knowledge\Embedding\Exceptions\InvalidEmbeddingStorageException;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * EmbeddingStorage turns EMBEDDING_VECTOR_TYPE / EMBEDDING_DIMENSIONS into the
 * column type every embedding column and query cast uses.
 */
it('defaults to halfvec with 1024 dimensions', function (): void {
    $storage = new EmbeddingStorage;

    expect($storage->type())->toBe(VectorType::Halfvec)
        ->and($storage->dimensions())->toBe(1024)
        ->and($storage->columnType())->toBe('halfvec(1024)')
        ->and($storage->type()->operatorClass())->toBe('halfvec_l2_ops');
});

it('reads the vector type and size from the settings', function (): void {
    config(['chatvia.embeddings.type' => ' Vector ', 'chatvia.embeddings.dimensions' => '1536']);

    $storage = new EmbeddingStorage;

    expect($storage->columnType())->toBe('vector(1536)')
        ->and($storage->type()->operatorClass())->toBe('vector_l2_ops');
});

it('rejects a vector type the database cannot store', function (): void {
    config(['chatvia.embeddings.type' => 'float']);

    expect(fn (): VectorType => (new EmbeddingStorage)->type())
        ->toThrow(InvalidEmbeddingStorageException::class, 'EMBEDDING_VECTOR_TYPE must be "halfvec" or "vector", not "float".');
});

it('rejects a size the database cannot store', function (mixed $dimensions): void {
    config(['chatvia.embeddings.dimensions' => $dimensions]);

    expect(fn (): int => (new EmbeddingStorage)->dimensions())
        ->toThrow(InvalidEmbeddingStorageException::class, 'EMBEDDING_DIMENSIONS must be a whole number from 1 to 16000');
})->with([0, 16001, '1024.5', 'many']);
