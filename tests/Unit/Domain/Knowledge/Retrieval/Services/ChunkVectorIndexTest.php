<?php

declare(strict_types=1);

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Services\ChunkVectorIndex;

/**
 * The index expression and the search's ORDER BY come from one place, so the
 * planner can match them: a binary index quantizes both sides to the column's
 * size, a full one compares the vectors as stored.
 */
it('quantizes the column and the query vector to the column size for a binary index', function (): void {
    config(['chatvia.embeddings.index' => 'binary', 'chatvia.embeddings.dimensions' => 768]);
    $index = new ChunkVectorIndex(new EmbeddingStorage);

    expect($index->expression('embedding'))->toBe('(binary_quantize(embedding)::bit(768))')
        ->and($index->operatorClass())->toBe('bit_hamming_ops')
        ->and($index->orderBy('c.embedding', '?::halfvec(768)'))
        ->toBe('binary_quantize(c.embedding)::bit(768) <~> binary_quantize(?::halfvec(768))::bit(768)');
});

it('compares the stored vectors for a full index', function (): void {
    config(['chatvia.embeddings.index' => 'full', 'chatvia.embeddings.type' => 'vector', 'chatvia.embeddings.dimensions' => 768]);
    $index = new ChunkVectorIndex(new EmbeddingStorage);

    expect($index->expression('embedding'))->toBe('embedding')
        ->and($index->operatorClass())->toBe('vector_l2_ops')
        ->and($index->orderBy('c.embedding', '?::vector(768)'))->toBe('c.embedding <-> ?::vector(768)');
});
