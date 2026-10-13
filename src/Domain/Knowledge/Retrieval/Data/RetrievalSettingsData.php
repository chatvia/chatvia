<?php

namespace Domain\Knowledge\Retrieval\Data;

use Spatie\LaravelData\Data;

/**
 * The `chatvia.vector.search` settings one search runs with, read once so the
 * steps of a search agree on them.
 */
final class RetrievalSettingsData extends Data
{
    public function __construct(
        /** vchordrq accuracy mode (`default`, …); picks probes and epsilon. */
        public string $accuracy,
        /** How many chunks a search returns at most. */
        public int $maxChunks,
        public bool $rerank,
        /** How many candidates the reranker scores. */
        public int $rerankCandidates,
        /** Reranker score a chunk needs to be kept. */
        public ?float $relevanceThreshold,
        /** SQL time (ms) from which a search is logged as slow; 0 turns it off. */
        public int $slowLogMs,
        /** Agents with at most this many chunks are searched exactly; 0 never. */
        public int $exactMaxChunks,
    ) {}

    public static function fromConfig(): self
    {
        $relevanceThreshold = config('chatvia.vector.search.relevance_threshold');

        return new self(
            accuracy: (string) config('chatvia.vector.search.mode', 'default'),
            maxChunks: (int) config('chatvia.vector.search.max_chunks', 3),
            rerank: (bool) config('chatvia.vector.search.rerank', true),
            rerankCandidates: (int) config('chatvia.vector.search.rerank_candidates', 12),
            relevanceThreshold: $relevanceThreshold !== null ? (float) $relevanceThreshold : null,
            slowLogMs: (int) config('chatvia.vector.search.slow_log_ms', 2000),
            exactMaxChunks: (int) config('chatvia.vector.search.exact_max_chunks', 10_000),
        );
    }

    /**
     * How many candidates the SQL returns: enough for the reranker to choose
     * from (never fewer than the results asked for, Settings → Search), or
     * twice the result count without one.
     */
    public function candidateLimit(): int
    {
        return $this->rerank ? max($this->rerankCandidates, $this->maxChunks) : $this->maxChunks * 2;
    }
}
