<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Embedding;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use RuntimeException;

/**
 * Deterministic, offline stand-in for the real reranker, bound by default in
 * TestCase. It keeps the input order with high, falling scores (1.0, 0.99, ...),
 * so the rerank paths run without needing semantic ordering and the tool
 * relevance gate passes everything through. {@see failing()} and
 * {@see cappedAt()} copy two ways a real one misbehaves. A test that needs
 * other scores, or no reranker at all (NullReranker), binds its own.
 */
class FakeReranker implements Reranker
{
    public function __construct(
        private readonly ?int $defaultCap = null,
        private readonly bool $fails = false,
    ) {}

    /** A reranker whose service is down: every call throws. */
    public static function failing(): self
    {
        return new self(fails: true);
    }

    /** A reranker that returns at most $documents when no top_n is asked for, as Qwen3's endpoint does. */
    public static function cappedAt(int $documents): self
    {
        return new self(defaultCap: $documents);
    }

    public function rerank(string $query, array $documents, ?int $topN = null): array
    {
        if ($this->fails) {
            throw new RuntimeException('reranker down');
        }

        $ranked = array_map(
            static fn (int $i): RerankedDocumentData => new RerankedDocumentData($i, 1.0 - ($i * 0.01)),
            array_keys(array_values($documents)),
        );

        $limit = $topN ?? $this->defaultCap;

        return $limit === null ? $ranked : array_slice($ranked, 0, $limit);
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
