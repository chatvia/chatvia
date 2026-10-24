<?php

use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Rerank\NullReranker;

/**
 * The no-op reranker of an install without a reranking service: candidates
 * keep their original order and nothing is dropped.
 */
it('keeps the candidates in their original order with a top score', function (): void {
    expect((new NullReranker)->rerank('q', ['a', 'b', 'c']))->toEqual([
        new RerankedDocumentData(0, 1.0),
        new RerankedDocumentData(1, 1.0),
        new RerankedDocumentData(2, 1.0),
    ]);
});

it('honours the result cap', function (): void {
    expect((new NullReranker)->rerank('q', ['a', 'b', 'c'], topN: 2))->toHaveCount(2)
        ->and((new NullReranker)->rerank('q', []))->toBe([]);
});
