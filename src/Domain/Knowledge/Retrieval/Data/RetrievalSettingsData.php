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
        /** Whether the relevance check runs (the reranker, else the similarity check). */
        public bool $relevanceCheck,
        /** How many candidates the reranker scores. */
        public int $rerankCandidates,
        /** The most tokens of knowledge one search may return. */
        public int $maxContextTokens,
        /** SQL time (ms) from which a search is logged as slow; 0 turns it off. */
        public int $slowLogMs,
        /** Agents with at most this many chunks are searched exactly; 0 never. */
        public int $exactMaxChunks,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            accuracy: (string) config('chatvia.vector.search.mode', 'default'),
            maxChunks: max(1, (int) config('chatvia.vector.search.max_chunks', 5)),
            relevanceCheck: (bool) config('chatvia.vector.search.relevance_check', true),
            rerankCandidates: (int) config('chatvia.vector.search.rerank_candidates', 25),
            maxContextTokens: (int) config('chatvia.vector.search.max_context_tokens', 3000),
            slowLogMs: (int) config('chatvia.vector.search.slow_log_ms', 2000),
            exactMaxChunks: (int) config('chatvia.vector.search.exact_max_chunks', 10_000),
        );
    }

    /**
     * How many candidates the SQL returns: enough for the reranker to choose
     * from (never fewer than the results asked for, Settings → Knowledge),
     * or twice the result count without one.
     */
    public function candidateLimit(): int
    {
        return $this->relevanceCheck ? max($this->rerankCandidates, $this->maxChunks) : $this->maxChunks * 2;
    }
}
