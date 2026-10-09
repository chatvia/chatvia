<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Contracts;

use Domain\Knowledge\Embedding\Data\RerankedDocumentData;

/**
 * Scores how well each candidate text answers a query, so retrieval and tool
 * routing can keep the relevant candidates and drop the rest. The
 * implementation is chosen from config (`chatvia.ai.reranker`); an install
 * without a reranking service gets the no-op reranker.
 */
interface Reranker
{
    /**
     * Rank the documents against the query, strongest first.
     *
     * @param  list<string>  $documents
     * @param  int|null  $topN  Cap on results returned; null keeps the reranker's default
     * @return list<RerankedDocumentData> each `index` points into $documents
     */
    public function rerank(string $query, array $documents, ?int $topN = null): array;

    /**
     * False for the no-op reranker. Its scores carry no meaning, so callers
     * skip their relevance threshold instead of filtering on them.
     */
    public function isEnabled(): bool;
}
