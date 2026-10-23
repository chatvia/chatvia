<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;

/**
 * Deterministic, offline stand-in for the real reranker, bound by default in
 * TestCase. It keeps the input order with high, falling scores (1.0, 0.99, ...),
 * so the rerank paths run without needing semantic ordering and the tool
 * relevance gate passes everything through. A test that needs other scores, or
 * no reranker at all (NullReranker), binds its own.
 */
class FakeReranker implements Reranker
{
    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        $ranked = array_map(
            static fn (int $i): RerankedDocumentData => new RerankedDocumentData($i, 1.0 - ($i * 0.01)),
            array_keys(array_values($documents)),
        );

        return $topN === null ? $ranked : array_slice($ranked, 0, $topN);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function model(): string
    {
        return 'fake-reranker';
    }
}
