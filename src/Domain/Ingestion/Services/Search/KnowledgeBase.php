<?php

namespace Domain\Ingestion\Services\Search;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use App\LLM\Operations\SearchDecision;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Jobs\UpdateRetrievalStatsJob;
use Domain\Ingestion\Models\Chunk;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KnowledgeBase
{
    private string $cacheStore;

    private array $vectorChordConfig;

    public function __construct()
    {
        $this->cacheStore = config('vector.cache.store', 'redis');
        $this->vectorChordConfig = config('vector', []);
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
            'search_embedding:q:'.md5($query).':'.$embeddingProvider->getModelName(),
            3600 * 24,
            fn (): string|array => embed_query($query)
        );
        $vectorString = '['.implode(',', $embedding).']';

        // Configure search parameters from config
        $accuracyLevel = config('vector.search.mode', 'default');
        $quantize = config('vector.quantization.rabitq.enabled', true);

        // Use ChunkQueryBuilder for the search
        $chunkBuilder = Chunk::query();
        $chunkBuilder->configureSearchAccuracy($accuracyLevel, $this->vectorChordConfig);

        // If reranking is enabled, fetch more candidates for better filtering
        // Since we filter by threshold (not limit), fetch enough candidates to ensure quality options
        $maxChunks = config('vector.search.max_chunks', 3);
        $candidateLimit = config('vector.search.rerank', false) ? 12 : ($maxChunks * 2); // Fetch extra for filtering
        $results = $chunkBuilder->hybridSearchForAgent($agent, $query, $vectorString, $candidateLimit, $quantize);

        if ($results) {
            // Dispatch stats update to background job for better performance
            UpdateRetrievalStatsJob::dispatch($results, $agent);

            // Only rerank if we have multiple results, reranking is enabled in
            // config, and the configured embeddings provider supports it.
            $provider = app(EmbeddingsProviderInterface::class);
            if (count($results) > 1 && config('vector.search.rerank', true) && $provider->supportsReranking()) {
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
        // Database already orders by score and returns best matches
        // BM25 scores are negative (higher = better), vector distances are positive (lower = better)
        // Both are already sorted correctly by the DB query
        // Simply take the top N results without additional filtering
        $maxChunks = config('vector.search.max_chunks', 3);

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
            $relevanceThreshold = config('vector.search.relevance_threshold');
            $maxChunks = config('vector.search.max_chunks');

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
        return collect($results)
            ->map(function ($result) {
                $result->chunk = $this->removeXmlTags($result->chunk);

                // Calculate confidence score from the distance/score
                // Lower distance = higher confidence
                // Normalize to 0-1 range (0.5 to 0.99)
                if (isset($result->score)) {
                    $result->confidence_score = $this->calculateConfidenceScore($result->score);
                }

                return $result;
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate confidence score from search distance/score
     * Converts distance metric to a confidence percentage (0.5 to 0.99)
     */
    private function calculateConfidenceScore(float $score): float
    {
        // For vector distance: lower is better (typically 0-2 range)
        // For BM25: higher is better (can be negative or positive)

        // Normalize to 0-1 range with sigmoid-like function
        // This ensures scores are between 0.5 (low confidence) and 0.99 (high confidence)
        $normalized = 1 / (1 + exp($score - 1));

        // Scale to 0.5-0.99 range
        $confidence = 0.5 + ($normalized * 0.49);

        return round($confidence, 4);
    }

    private function removeXmlTags(string $text): string
    {
        // Remove XML tags and their content using regex
        // This pattern matches opening tag, content, and closing tag
        $pattern = '/<[^>]+>.*?<\/[^>]+>/s';
        $text = preg_replace($pattern, '', $text);

        // Also remove self-closing tags
        $text = preg_replace('/<[^>]+\/>/s', '', $text);

        // Clean up extra whitespace that might be left behind
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    public function chunks(int|Agent $agent, string $query, int $limit = 4): Collection
    {
        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit);

        return collect($results)->pluck('chunk');
    }

    public function searchInKnowledgeBaseIfNeeded(Agent $agent, string $userMessage): string
    {
        // Check if we need to search (handles greetings, acknowledgments, etc.)
        if (! $this->shouldContinueSearching([], $userMessage)) {
            return $userMessage;
        }

        $chunks = $this->chunks($agent->id, $userMessage);

        if ($chunks->isEmpty()) {
            return $userMessage;
        }

        return Str::of("### User Question:\n")
            ->append($userMessage."\n")
            ->append("### Retrieved Context:\n")
            ->append($chunks->implode("\n\n"))
            ->toString();
    }

    public function shouldContinueSearching(array $chunks, string $userQuery): bool
    {
        return app(SearchDecision::class)->shouldSearch($chunks, $userQuery);
    }
}
