<?php

namespace App\Services\Search;

use App\Jobs\UpdateRetrievalStatsJob;
use App\Models\Agent;
use App\Services\Infrastructure\Embeddings\Cohere;
use App\Services\Infrastructure\LLM\OpenAI;
use App\Services\Prompts\PromptLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KnowledgeBase
{
    private int $cacheTtl;

    private string $cachePrefix;

    private string $cacheStore;

    private int $candidateLimit;

    private array $vectorChordConfig;

    public function __construct()
    {
        $this->cacheTtl = config('vector.cache.ttl', 3600);
        $this->cachePrefix = config('vector.cache.prefix', 'rag_search:');
        $this->cacheStore = config('vector.cache.store', 'redis');
        $this->candidateLimit = config('vector.search.candidate_limit', 100);
        $this->vectorChordConfig = config('vector', []);
    }

    public function search(int|Agent $agent, string $query, int $limit = 3): array
    {
        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        // Cache the embedding for 24 hours to speed up repeated queries
        $embedding = Cache::store($this->cacheStore)->remember(
            'search_embedding:'.md5($query),
            3600 * 24,
            fn () => embed($query)
        );
        $vectorString = '['.implode(',', $embedding).']';

        // Configure search parameters from config
        $accuracyLevel = config('vector.search.mode', 'default');
        $quantize = config('vector.quantization.rabitq.enabled', true);

        // Use ChunkQueryBuilder for the search
        $chunkBuilder = \App\Models\Chunk::query();
        $chunkBuilder->configureSearchAccuracy($accuracyLevel, $this->vectorChordConfig);

        $candidateLimit = $this->getOptimalCandidateLimit($limit);
        $results = $chunkBuilder->hybridSearchForAgent($agent, $query, $vectorString, $candidateLimit, $quantize);

        if ($results) {
            // Dispatch stats update to background job for better performance
            UpdateRetrievalStatsJob::dispatch($results, $agent);

            // Only rerank if we have multiple results and reranking is enabled in config
            if (count($results) > 1 && config('vector.search.rerank', false)) {
                $results = $this->rerank($query, $results);
            }

            $results = $this->processResults($results);
        }

        return $results;
    }

    private function rerank(string $query, array $results): array
    {
        $cacheKey = $this->cachePrefix.'rerank:'.md5($query.serialize($results));

        return Cache::store($this->cacheStore)->remember($cacheKey, $this->cacheTtl, function () use ($query, $results) {
            try {
                $chunks = collect($results)->pluck('chunk')->toArray();
                $rerankedDocumentsIds = app(Cohere::class)->rerank($query, $chunks);

                return collect($results)
                    ->filter(function ($result, $index) use ($rerankedDocumentsIds) {
                        return in_array($index, $rerankedDocumentsIds, true);
                    })
                    ->sortBy(function ($result, $index) use ($rerankedDocumentsIds) {
                        return array_search($index, $rerankedDocumentsIds, true);
                    })
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                return $results;
            }
        });
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

    /**
     * Calculate optimal candidate limit for initial vector search
     * Uses configuration with sensible fallbacks
     */
    private function getOptimalCandidateLimit(int $requestedLimit): int
    {
        // Use a multiplier to get more candidates for better results
        $multiplier = $this->vectorChordConfig['performance']['candidate_multiplier'] ?? 10;
        $calculated = $requestedLimit * $multiplier;

        // Apply reasonable limits
        $maxCandidates = min(
            $this->vectorChordConfig['performance']['max_candidates'] ?? 5000,
            $this->candidateLimit,
            10000 // Absolute maximum to prevent excessive memory usage
        );

        return min($calculated, $maxCandidates);
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

    public function chunks(int|Agent $agent, string $query, int $limit = 3): Collection
    {
        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit);

        return collect($results)->pluck('chunk');
    }

    public function searchInKnowledgeBaseIfNeeded(Agent $agent, string $userMessage): string
    {
        if ($agent->isStandalone()) {
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

        return $userMessage;
    }

    public function shouldContinueSearching(array $chunks, string $userQuery): bool
    {
        $res = app(OpenAI::class)->completion([
            'model' => 'gpt-4.1-nano',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => PromptLoader::load('knowledge/follow-up/system'),
                ],
                [
                    'role' => 'user',
                    'content' => PromptLoader::load('knowledge/follow-up/user', [
                        'retrieved_chunks' => collect($chunks)->implode("\n\n"),
                        'user_query' => $userQuery,
                    ]),
                ],
            ],
        ]);

        return str_contains($res, 'search');
    }

    public function rewriteQuery(string $userQuery, $previousQuery = null): array
    {
        return OpenAI::rewriteQuery($userQuery, $previousQuery);
    }
}
