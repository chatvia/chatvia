<?php

namespace Domain\Knowledge\Ingestion\Services\Search;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KnowledgeBase
{
    private string $cacheStore;

    private array $vectorChordConfig;

    public function __construct()
    {
        $this->cacheStore = config('chatvia.vector.cache.store', 'redis');
        $this->vectorChordConfig = config('chatvia.vector', []);
    }

    public function search(int|Agent $agent, string $query, int $limit = 4): array
    {
        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        // Cache the embedding for 24 hours to speed up repeated queries.
        // Note: query embedding uses provider-specific query conventions
        // (Qwen3 instruction prefix etc.), distinct from how documents are
        // embedded at ingestion time. Cache key versioned with ':q' so old
        // document-style cache entries can't collide.
        $embeddingProvider = app(EmbeddingsProviderInterface::class);
        $embedding = Cache::store($this->cacheStore)->remember(
            'embed:query:'.$embeddingProvider->getModelName().':'.sha1($query),
            3600 * 24,
            fn (): string|array => embed_query($query)
        );
        $vectorString = '['.implode(',', $embedding).']';

        $accuracyLevel = config('chatvia.vector.search.mode', 'default');
        $maxChunks = config('chatvia.vector.search.max_chunks', 3);
        $candidateLimit = config('chatvia.vector.search.rerank', false) ? 12 : ($maxChunks * 2);

        // SET LOCAL needs a transaction so vchordrq settings don't leak
        // across requests on pooled connections (pgbouncer / Octane).
        $results = DB::transaction(function () use ($agent, $query, $vectorString, $candidateLimit, $accuracyLevel): array {
            $chunkBuilder = Chunk::query();
            $chunkBuilder->configureSearchAccuracy($accuracyLevel, $this->vectorChordConfig);

            return $chunkBuilder->hybridSearchForAgent($agent, $query, $vectorString, $candidateLimit);
        });

        if ($results) {
            // Dispatch stats update to background job for better performance
            UpdateRetrievalStatsJob::dispatch($results, $agent);

            // Only rerank if we have multiple results, reranking is enabled in
            // config, and the configured embeddings provider supports it.
            $provider = app(EmbeddingsProviderInterface::class);
            if (count($results) > 1 && config('chatvia.vector.search.rerank', false) && $provider->supportsReranking()) {
                $results = $this->rerank($query, $results, $provider);
            } else {
                // When reranking is disabled or unsupported, use DB confidence
                // score to filter and limit.
                $results = $this->filterByDbConfidence($results);
            }

            $results = $this->processResults($results);
        }

        return $results;
    }

    private function filterByDbConfidence(array $results): array
    {
        $maxChunks = config('chatvia.vector.search.max_chunks', 3);

        return collect($results)
            ->take($maxChunks)
            ->values()
            ->toArray();
    }

    private function rerank(string $query, array $results, EmbeddingsProviderInterface $provider): array
    {
        try {
            $chunks = collect($results)->pluck('chunk')->toArray();
            $rerankedResults = $provider->rerank($query, $chunks);

            // Get relevance threshold from config (default: 0.5)
            $relevanceThreshold = config('chatvia.vector.search.relevance_threshold');
            $maxChunks = config('chatvia.vector.search.max_chunks');

            // Map reranked results with relevance scores and filter by threshold
            // Then limit to max chunks
            return collect($rerankedResults)
                ->filter(fn ($item): bool => $item['relevance_score'] >= $relevanceThreshold)
                ->take($maxChunks)
                ->map(function ($item) use ($results) {
                    $result = $results[$item['index']];
                    $result->rerank_score = $item['relevance_score'];

                    return $result;
                })
                ->values()
                ->toArray();
        } catch (Exception $e) {
            // On error, return original results
            return $results;
        }
    }

    private function processResults(array $results): array
    {
        $total = count($results);

        return collect($results)
            ->values()
            ->map(function ($result, int $rank) use ($total) {
                $result->chunk = RetrievalScoring::stripXmlTags($result->chunk);

                // RRF returns a small positive score (~0.01–0.07); convert to a
                // rank-based confidence: top result → near 0.99, last → near 0.5.
                $result->confidence_score = RetrievalScoring::confidenceScore($rank, $total);

                return $result;
            })
            ->values()
            ->toArray();
    }

    public function chunks(int|Agent $agent, string $query, int $limit = 4): Collection
    {
        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit);

        return collect($results)->pluck('chunk');
    }
}
