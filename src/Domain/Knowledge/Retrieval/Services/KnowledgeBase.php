<?php

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
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
 *      of candidates: exactly over the agent's own chunks when it has few,
 *      through the vector and BM25 indexes when it has many;
 *   3. narrow the pool to `max_chunks`: with a reranker, keep the candidates
 *      that pass its relevance threshold; without one (or when it fails), keep
 *      the SQL ranking.
 *
 * Each result carries the relevance its check gave it (null when none ran),
 * and its text as the model reads it ({@see RetrievalScoring::plainText()}).
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
        $settings = RetrievalSettingsData::fromConfig();
        $searchStart = microtime(true);

        // A bounded count of the agent's chunks picks the search path. An
        // agent with none (no sources, or none ingested yet) skips the
        // (possibly cold) query embedding and the search.
        $scope = $this->hybridSearch->scope($agent, $settings->exactMaxChunks);

        if ($scope === ChunkSearchScope::None) {
            return [];
        }

        // Query embeddings follow the provider's query conventions (for
        // example Qwen3's instruction prefix), unlike document embeddings, and
        // are cached for 24h: a sub-millisecond embed_ms means a cache hit.
        $embedStart = microtime(true);
        $queryVector = '['.implode(',', $this->embeddings->embedQuery($query)).']';
        $embedMs = $this->elapsedMs($embedStart);

        $sqlStart = microtime(true);
        $candidates = $this->hybridSearch->search($agent, $query, $queryVector, $settings->candidateLimit(), $scope, $settings->accuracy);
        $sqlMs = $this->elapsedMs($sqlStart);

        $reranked = false;
        $rerankMs = 0.0;
        $results = [];

        if ($candidates !== []) {
            $rerankStart = microtime(true);
            $reranked = $this->shouldRerank($candidates, $settings);
            $results = $this->readable($reranked
                ? $this->rerank($query, $candidates, $settings)
                : array_slice($candidates, 0, $settings->maxChunks));
            $rerankMs = $this->elapsedMs($rerankStart);
        }

        $this->logTimings($settings, [
            'agent_id' => $agent->id,
            'query_chars' => strlen($query),
            'accuracy' => $settings->accuracy,
            'scope' => $scope->value,
            'candidate_limit' => $settings->candidateLimit(),
            'candidates' => count($candidates),
            'returned' => count($results),
            'reranked' => $reranked,
            'embed_ms' => round($embedMs, 2),
            'sql_ms' => round($sqlMs, 2),
            'rerank_ms' => round($rerankMs, 2),
            'total_ms' => round($this->elapsedMs($searchStart), 2),
            'results' => array_map(static fn (RetrievedChunkData $result): array => [
                'chunk_id' => $result->id,
                'search_type' => $result->search_type,
                'relevance' => $result->relevance,
            ], $results),
        ], $sqlMs);

        return $results;
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
            // Score every candidate: a reranker's own default cap (Qwen3's is 3)
            // must not cut below the results asked for, or the threshold.
            $ranked = $this->reranker->rerank($query, array_map(static fn (RetrievedChunkData $chunk): string => $chunk->chunk, $candidates), count($candidates));
        } catch (Throwable $e) {
            report($e);

            return array_slice($candidates, 0, $settings->maxChunks);
        }

        $kept = array_filter(
            $ranked,
            static fn (RerankedDocumentData $item): bool => $settings->relevanceThreshold === null || $item->relevanceScore >= $settings->relevanceThreshold,
        );

        return $kept
                |> array_values(...)
                |> (fn ($x): array => array_slice($x, 0, $settings->maxChunks))
                |> (fn ($x): array => array_map(static fn (RerankedDocumentData $item): RetrievedChunkData => $candidates[$item->index]->withRelevance($item->relevanceScore), $x));
    }

    /**
     * The results as the model reads them: tag markup removed, text kept.
     *
     * @param  list<RetrievedChunkData>  $results
     * @return list<RetrievedChunkData>
     */
    private function readable(array $results): array
    {
        return array_map(
            static fn (RetrievedChunkData $result): RetrievedChunkData => $result->withText(RetrievalScoring::plainText($result->chunk)),
            $results,
        );
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
