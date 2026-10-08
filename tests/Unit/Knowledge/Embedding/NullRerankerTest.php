<?php

use Domain\Knowledge\Embedding\Rerank\NullReranker;

/**
 * The no-op reranker of an install without a reranking service: candidates
 * keep their original order and nothing is dropped.
 */
it('keeps the candidates in their original order with a top score', function (): void {
    expect((new NullReranker)->rerank('q', ['a', 'b', 'c']))->toBe([
        ['index' => 0, 'relevance_score' => 1.0],
        ['index' => 1, 'relevance_score' => 1.0],
        ['index' => 2, 'relevance_score' => 1.0],
    ]);
});

it('honours the result cap', function (): void {
    expect((new NullReranker)->rerank('q', ['a', 'b', 'c'], topN: 2))->toHaveCount(2)
        ->and((new NullReranker)->rerank('q', []))->toBe([]);
});

it('tells callers to skip their relevance threshold', function (): void {
    expect((new NullReranker)->isEnabled())->toBeFalse();
});
