<?php

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds the chunks of an agent's knowledge that answer a message — the "R" of
 * the agent's RAG.
 *
 * One search runs in three phases, each timed for the logs:
 *
 *   1. embed the query (cached per query and model);
 *   2. run the four-channel hybrid SQL ({@see HybridChunkSearch}) for a pool
 *      of candidates;
 *   3. narrow the pool to `max_chunks`: with a reranker, keep the candidates
 *      that pass its relevance threshold; without one (or when it fails), keep
 *      the SQL ranking.
 *
 * Results carry a display confidence from their final rank, and the retrieval
 * counters of their sources are bumped off the request path.
 */
class KnowledgeBase
{
    public function __construct(
        private readonly HybridChunkSearch $hybridSearch,
        private readonly Reranker $reranker,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    /**
     * @return list<RetrievedChunkData> best first, at most `max_chunks`
     */
    public function search(Agent $agent, string $query): array
    {
        // An agent with no sources can never match the EXISTS filter of any
        // channel, so skip the (possibly cold) query embedding and the scan.
        // agent_id leads the agent_sources primary key: an index seek.
        if (! AgentSource::query()->where('agent_id', $agent->id)->exists()) {
            return [];
        }

        $settings = RetrievalSettingsData::fromConfig();
        $searchStart = microtime(true);

        // Query embeddings follow the provider's query conventions (for
        // example Qwen3's instruction prefix), unlike document embeddings, and
        // are cached for 24h: a sub-millisecond embed_ms means a cache hit.
        $embedStart = microtime(true);
        $queryVector = '['.implode(',', $this->embeddings->embedQuery($query)).']';
        $embedMs = $this->elapsedMs($embedStart);

        $sqlStart = microtime(true);
        $candidates = $this->candidates($agent, $query, $queryVector, $settings);
        $sqlMs = $this->elapsedMs($sqlStart);

        $reranked = false;
        $rerankMs = 0.0;
        $results = [];

        if ($candidates !== []) {
            // Only source references go on the queue — never chunk text, which
            // is customer content and would sit in Redis.
            UpdateRetrievalStatsJob::dispatch($this->sourceReferences($candidates), $agent->id);

            $rerankStart = microtime(true);
            $reranked = $this->shouldRerank($candidates, $settings);
            $results = $this->scored($reranked
                ? $this->rerank($query, $candidates, $settings)
                : array_slice($candidates, 0, $settings->maxChunks));
            $rerankMs = $this->elapsedMs($rerankStart);
        }

        $this->logTimings($settings, [
            'agent_id' => $agent->id,
            'query_chars' => strlen($query),
            'accuracy' => $settings->accuracy,
            'candidate_limit' => $settings->candidateLimit(),
            'candidates' => count($candidates),
            'returned' => count($results),
            'reranked' => $reranked,
            'embed_ms' => round($embedMs, 2),
            'sql_ms' => round($sqlMs, 2),
            'rerank_ms' => round($rerankMs, 2),
            'total_ms' => round($this->elapsedMs($searchStart), 2),
        ], $sqlMs);

        return $results;
    }

    /**
     * The hybrid SQL, in a transaction because `SET LOCAL` needs one: the
     * vchordrq settings must not leak to the next user of a pooled connection.
     *
     * @return list<RetrievedChunkData>
     */
    private function candidates(Agent $agent, string $query, string $queryVector, RetrievalSettingsData $settings): array
    {
        return DB::transaction(function () use ($agent, $query, $queryVector, $settings): array {
            $this->hybridSearch->configureAccuracy($settings->accuracy, (array) config('chatvia.vector', []));

            return $this->hybridSearch->search($agent, $query, $queryVector, $settings->candidateLimit());
        });
    }

    /**
     * Rerank only when there is something to order, it is switched on, and a
     * real reranker is configured (the no-op one's scores mean nothing).
     *
     * @param  list<RetrievedChunkData>  $candidates
     */
    private function shouldRerank(array $candidates, RetrievalSettingsData $settings): bool
    {
        return count($candidates) > 1 && $settings->rerank && $this->reranker->isEnabled();
    }

    /**
     * Keep the candidates the reranker scores at or above the relevance
     * threshold, best first. A reranker outage must not take chat down, but it
     * must not widen the context either: then the SQL ranking and the usual
     * limit apply.
     *
     * @param  list<RetrievedChunkData>  $candidates
     * @return list<RetrievedChunkData>
     */
    private function rerank(string $query, array $candidates, RetrievalSettingsData $settings): array
    {
        try {
            $ranked = $this->reranker->rerank($query, array_map(static fn (RetrievedChunkData $chunk): string => $chunk->chunk, $candidates));
        } catch (Throwable $e) {
            report($e);

            return array_slice($candidates, 0, $settings->maxChunks);
        }

        $kept = array_filter(
            $ranked,
            static fn (array $item): bool => $settings->relevanceThreshold === null || $item['relevance_score'] >= $settings->relevanceThreshold,
        );

        return array_map(
            static fn (array $item): RetrievedChunkData => $candidates[$item['index']]->withRerankScore((float) $item['relevance_score']),
            array_slice(array_values($kept), 0, $settings->maxChunks),
        );
    }

    /**
     * Strip XML/HTML from each chunk and give it a display confidence from its
     * rank: the RRF score itself is a small number (~0.01–0.07) that means
     * little to a reader.
     *
     * @param  list<RetrievedChunkData>  $results
     * @return list<RetrievedChunkData>
     */
    private function scored(array $results): array
    {
        $total = count($results);

        return array_map(
            static fn (RetrievedChunkData $result, int $rank): RetrievedChunkData => $result->scored(
                RetrievalScoring::stripXmlTags($result->chunk),
                RetrievalScoring::confidenceScore($rank, $total),
            ),
            $results,
            array_keys($results),
        );
    }

    /**
     * The distinct sources behind the results — all the stats job needs.
     *
     * @param  list<RetrievedChunkData>  $results
     * @return list<array{chunkable_type: string, chunkable_id: int}>
     */
    private function sourceReferences(array $results): array
    {
        return collect($results)
            ->map(static fn (RetrievedChunkData $result): array => [
                'chunkable_type' => $result->chunkable_type,
                'chunkable_id' => $result->chunkable_id,
            ])
            ->unique(static fn (array $reference): string => $reference['chunkable_type'].':'.$reference['chunkable_id'])
            ->values()
            ->all();
    }

    /**
     * Debug-level phase breakdown (only with LOG_LEVEL=debug), plus a warning,
     * whatever the log level, when the SQL was slow. The hybrid SQL normally
     * takes ~200ms but can spike to 20s+ (cold index, autovacuum, checkpoint),
     * which looks like a hung chat to the visitor.
     *
     * @param  array<string, mixed>  $breakdown
     */
    private function logTimings(RetrievalSettingsData $settings, array $breakdown, float $sqlMs): void
    {
        Log::debug('KnowledgeBase: search complete', $breakdown);

        if ($settings->slowLogMs > 0 && $sqlMs >= $settings->slowLogMs) {
            Log::warning('KnowledgeBase: slow vector search', $breakdown + ['slow_threshold_ms' => $settings->slowLogMs]);
        }
    }

    private function elapsedMs(float $start): float
    {
        return (microtime(true) - $start) * 1000;
    }
}
