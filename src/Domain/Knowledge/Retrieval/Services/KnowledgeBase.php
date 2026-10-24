<?php

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 *      through the vector and lexical indexes when it has many;
 *   3. the relevance check narrows the pool to `max_chunks`: the reranker's
 *      threshold, or without one (or when it fails) a basic similarity
 *      check; then the context budget (`max_context_tokens`).
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
        private readonly AiCallTags $tags,
    ) {}

    /**
     * Its query embedding and rerank are tagged `knowledge-search` in
     * Spectra, apart from the same kinds of call that tool routing makes.
     *
     * @return list<RetrievedChunkData> best first, at most `max_chunks`
     */
    public function search(Agent $agent, string $query): array
    {
        return $this->tags->during(AiCallTag::KnowledgeSearch, fn (): array => $this->find($agent, $query));
    }

    /**
     * @return list<RetrievedChunkData>
     */
    private function find(Agent $agent, string $query): array
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

        $check = 'none';
        $checkMs = 0.0;
        $results = [];

        if ($candidates !== []) {
            $checkStart = microtime(true);
            [$check, $selected] = $this->select($query, $candidates, $settings);
            $results = $this->readable($this->withinBudget($selected, $settings));
            $checkMs = $this->elapsedMs($checkStart);
        }

        $this->logTimings($settings, [
            'agent_id' => $agent->id,
            'query_chars' => Str::length($query),
            'accuracy' => $settings->accuracy,
            'scope' => $scope->value,
            'candidate_limit' => $settings->candidateLimit(),
            'candidates' => count($candidates),
            'returned' => count($results),
            'check' => $check,
            'embed_ms' => round($embedMs, 2),
            'sql_ms' => round($sqlMs, 2),
            'check_ms' => round($checkMs, 2),
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
     * The relevance check, best first, at most `max_chunks`:
     *  - with a reranker: the candidates it scores at or above the model's
     *    threshold, in its order;
     *  - without one, or when it fails: the candidates whose meaning is close
     *    enough to the question (the embedding model's threshold), in the
     *    search's order; an outage must not take chat down, nor widen it;
     *  - with the check off, or no threshold known: the search's top results.
     *
     * @param  list<RetrievedChunkData>  $candidates
     * @return array{0: string, 1: list<RetrievedChunkData>} which check ran, and what it kept
     */
    private function select(string $query, array $candidates, RetrievalSettingsData $settings): array
    {
        if ($settings->relevanceCheck && $this->reranker->isEnabled()) {
            try {
                return ['rerank', $this->rerank($query, $candidates, $settings)];
            } catch (Throwable $e) {
                report($e);
            }
        }

        $threshold = $settings->relevanceCheck ? RelevanceThresholds::knowledgeSimilarity() : null;

        if ($threshold !== null) {
            return ['similarity', $this->bySimilarity($candidates, $threshold, $settings)];
        }

        return ['none', array_slice($candidates, 0, $settings->maxChunks)];
    }

    /**
     * Keep the candidates the reranker scores at or above the relevance
     * threshold, best first.
     *
     * @param  list<RetrievedChunkData>  $candidates
     * @return list<RetrievedChunkData>
     */
    private function rerank(string $query, array $candidates, RetrievalSettingsData $settings): array
    {
        $threshold = RelevanceThresholds::knowledgeRerank($this->reranker);

        // Score every candidate, so the threshold sees them all.
        $ranked = $this->reranker->rerank($query, array_map(static fn (RetrievedChunkData $chunk): string => $chunk->chunk, $candidates), count($candidates));

        return $ranked
                |> (fn ($x): array => array_filter($x, static fn (RerankedDocumentData $item): bool => $item->relevanceScore >= $threshold && isset($candidates[$item->index])))
                |> array_values(...)
                |> (fn ($x): array => array_slice($x, 0, $settings->maxChunks))
                |> (fn ($x): array => array_map(static fn (RerankedDocumentData $item): RetrievedChunkData => $candidates[$item->index]->withRelevance($item->relevanceScore), $x));
    }

    /**
     * The basic similarity check: the candidates whose cosine similarity to
     * the question reaches the threshold, in the search's order.
     *
     * @param  list<RetrievedChunkData>  $candidates
     * @return list<RetrievedChunkData>
     */
    private function bySimilarity(array $candidates, float $threshold, RetrievalSettingsData $settings): array
    {
        return $candidates
                |> (fn ($x): array => array_filter($x, static fn (RetrievedChunkData $chunk): bool => $chunk->similarity !== null && $chunk->similarity >= $threshold))
                |> array_values(...)
                |> (fn ($x): array => array_slice($x, 0, $settings->maxChunks))
                |> (fn ($x): array => array_map(static fn (RetrievedChunkData $chunk): RetrievedChunkData => $chunk->withRelevance((float) $chunk->similarity), $x));
    }

    /**
     * The results that fit the context budget (`max_context_tokens`), in
     * order: the best always goes in, a lower one only while there is room.
     *
     * @param  list<RetrievedChunkData>  $results
     * @return list<RetrievedChunkData>
     */
    private function withinBudget(array $results, RetrievalSettingsData $settings): array
    {
        $kept = [];
        $total = 0;

        foreach ($results as $result) {
            if ($kept !== [] && $settings->maxContextTokens > 0 && $total + $result->tokens > $settings->maxContextTokens) {
                continue;
            }

            $kept[] = $result;
            $total += $result->tokens;
        }

        return $kept;
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
