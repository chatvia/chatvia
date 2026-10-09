<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Rerank;

use Domain\Knowledge\Embedding\Contracts\Reranker;

/**
 * Used when no reranking service is set up. It keeps the candidates in the
 * order they came in, with a top score each, so nothing is ever dropped.
 */
class NullReranker implements Reranker
{
    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        $ranked = array_map(
            static fn (int $index): array => ['index' => $index, 'relevance_score' => 1.0],
            array_keys(array_values($documents)),
        );

        return $topN === null ? $ranked : array_slice($ranked, 0, max($topN, 0));
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
