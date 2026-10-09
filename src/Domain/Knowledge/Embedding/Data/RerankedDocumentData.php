<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Data;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Spatie\LaravelData\Data;

/**
 * One candidate as scored by a {@see Reranker}: its position in the list the
 * caller sent and how well it answers the query (higher is better; the scale
 * depends on the reranking model).
 */
final class RerankedDocumentData extends Data
{
    public function __construct(
        /** Position of the document in the list given to rerank(). */
        public int $index,
        public float $relevanceScore,
    ) {}

    /**
     * One entry of a `/rerank` response (`index`, `relevance_score`).
     *
     * @param  array{index: int|string, relevance_score: float|string}  $result
     */
    public static function fromResult(array $result): self
    {
        return new self((int) $result['index'], (float) $result['relevance_score']);
    }
}
