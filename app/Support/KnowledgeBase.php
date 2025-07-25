<?php

namespace App\Support;

use App\Models\AgentFile;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KnowledgeBase
{
    private int $cacheTtl;

    private string $cachePrefix;

    private int $candidateLimit;

    private array $vectorChordConfig;

    public function __construct()
    {
        $this->cacheTtl = config('vector.cache.ttl', 3600);
        $this->cachePrefix = config('vector.cache.prefix', 'rag_search:');
        $this->candidateLimit = config('vector.search.candidate_limit', 100);
        $this->vectorChordConfig = config('vector', []);
    }

    public function search(int|\App\Models\Agent $agent, string $query, int $limit = 3, array $options = []): array
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $embedding = embed($query);
        $vectorString = '['.implode(',', $embedding).']';

        // Configure search parameters based on options
        $accuracyLevel = $options['accuracy'] ?? 'default';
        $useHybrid = $options['hybrid'] ?? true;
        $enableReranking = $options['rerank'] ?? true;
        $quantize = $options['quantize'] ?? false;

        $this->configureSearchParameters($accuracyLevel);

        // Use VectorChord optimized hybrid search
        $results = $this->hybridSearch($agent, $query, $vectorString, $limit, $quantize);

        if ($results) {
            $this->updateRetrievalStats($results);

            if ($enableReranking && count($results) > 1) {
                $results = $this->performIntelligentReranking($query, $results);
            }

            $results = $this->processResults($results);
        }

        return $results;
    }

    /**
     * Perform VectorChord optimized hybrid search
     */
    private function hybridSearch(int $agentId, string $query, string $vectorString, int $limit, bool $quantize = true): array
    {
        $distanceOperator = $quantize
            ? 'binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536))::bit(1536) as binary_distance'
            : 'embedding <-> ?::vector(1536) as vector_distance';

        $sql = "
WITH vectorchord_candidates AS (
    SELECT id, chunk, embedding, agent_file_id,
           {$distanceOperator}
    FROM documents
    WHERE agent_id = ?
    ORDER BY ".($quantize ? 'binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536))::bit(1536)' : 'embedding <-> ?::vector(1536)').'
    LIMIT ?
),
vector_ranked AS (
    SELECT id, chunk, agent_file_id, '.($quantize ? 'binary_distance' : 'vector_distance').",
           'vector' as search_type
    FROM vectorchord_candidates
    ORDER BY ".($quantize ? 'binary_distance' : 'vector_distance')."
    LIMIT ?
),
bm25_ranked AS (
    SELECT id, chunk, agent_file_id,
           embedding_bm25 <&> bm25_catalog.to_bm25query('documents_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS bm25_rank,
           'bm25' as search_type
    FROM documents
    WHERE agent_id = ?
    ORDER BY bm25_rank
    LIMIT ?
),
combined_results AS (
    SELECT id, chunk, agent_file_id, ".($quantize ? 'binary_distance' : 'vector_distance').' as score, search_type FROM vector_ranked
    UNION ALL
    SELECT id, chunk, agent_file_id, bm25_rank as score, search_type FROM bm25_ranked
)
SELECT DISTINCT ON (id) id, chunk, agent_file_id, search_type, score
FROM combined_results
ORDER BY id, score
LIMIT ?
';

        return DB::select($sql, [
            $vectorString,
            $agentId,
            $vectorString,
            $this->getOptimalCandidateLimit($limit),
            $limit,
            $query,
            $agentId,
            $limit,
            $limit * 2, // Get more candidates for better hybrid results
        ]);
    }

    /**
     * Perform vector-only search using VectorChord
     */
    private function vectorSearch(int $agentId, string $vectorString, int $limit): array
    {
        try {
            $sql = "
SELECT id, chunk, agent_file_id,
       embedding <-> ?::vector(1536) as score,
       'vector' as search_type
FROM documents
WHERE agent_id = ?
ORDER BY embedding <-> ?::vector(1536)
LIMIT ?
";

            return DB::select($sql, [
                $vectorString,
                $agentId,
                $vectorString,
                $limit,
            ]);

        } catch (\Exception $e) {
            Log::warning('VectorChord vector search failed, falling back to basic search', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
            ]);

            return $this->performBasicVectorSearch($agentId, $vectorString, $limit);
        }
    }

    /**
     * Fallback search when VectorChord optimization fails
     */
    private function performFallbackSearch(int $agentId, string $query, string $vectorString, int $limit): array
    {
        try {
            // Try basic vector search first
            $vectorResults = $this->performBasicVectorSearch($agentId, $vectorString, $limit);

            // Try to add BM25 results if possible
            try {
                DB::statement('SET search_path = public, bm25_catalog, tokenizer_catalog');

                $bm25Results = DB::select("
                    SELECT id, chunk, agent_file_id,
                           embedding_bm25 <&> bm25_catalog.to_bm25query('documents_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS score,
                           'bm25' as search_type
                    FROM documents
                    WHERE agent_id = ?
                    ORDER BY score
                    LIMIT ?
                ", [$query, $agentId, $limit]);

                // Combine results if BM25 worked
                $combined = array_merge($vectorResults, $bm25Results);

                return array_slice($combined, 0, $limit);

            } catch (\Exception $bm25Error) {
                Log::debug('BM25 search also failed in fallback, using vector only', [
                    'error' => $bm25Error->getMessage(),
                ]);

                return $vectorResults;
            }

        } catch (\Exception $e) {
            Log::error('All search methods failed', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
            ]);

            return [];
        }
    }

    /**
     * Basic vector search without VectorChord optimizations
     * Falls back to standard pgvector operations when VectorChord fails
     */
    private function performBasicVectorSearch(int $agentId, string $vectorString, int $limit): array
    {
        // Reset to minimal probe configuration for this query
        try {
            DB::statement('SET vchordrq.probes = 0');
        } catch (\Exception $e) {
            // Ignore probe setting errors in basic search
            Log::debug('Could not set probes to 0 in basic search', ['error' => $e->getMessage()]);
        }

        $sql = "
SELECT id, chunk, agent_file_id,
       embedding <=> ?::vector(1536) as score,
       'vector' as search_type
FROM documents
WHERE agent_id = ?
ORDER BY embedding <=> ?::vector(1536)
LIMIT ?
";

        return DB::select($sql, [
            $vectorString,
            $agentId,
            $vectorString,
            $limit,
        ]);
    }

    /**
     * Configure VectorChord search parameters based on accuracy level
     */
    private function configureSearchParameters(string $accuracyLevel): void
    {
        try {
            $probes = $this->vectorChordConfig['search']['probes'][$accuracyLevel]
                ?? $this->vectorChordConfig['search']['probes']['default']
                ?? 20;

            // Set VectorChord runtime parameters
            DB::statement("SET vchordrq.probes = {$probes}");

            // Configure MaxSim if enabled
            if ($this->vectorChordConfig['search']['maxsim']['enabled'] ?? false) {
                $maxsimRefine = $this->vectorChordConfig['search']['maxsim']['refine'] ?? 2000;
                DB::statement("SET vchordrq.maxsim_refine = {$maxsimRefine}");
            }
        } catch (\Exception $e) {
            // If configuration fails, try with minimal probes
            Log::warning('Failed to configure VectorChord parameters, trying minimal configuration', [
                'error' => $e->getMessage(),
                'accuracy_level' => $accuracyLevel,
            ]);

            try {
                DB::statement('SET vchordrq.probes = 1');
            } catch (\Exception $e2) {
                // If even minimal configuration fails, log and continue
                Log::warning('VectorChord probe configuration failed completely', [
                    'error' => $e2->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update retrieval statistics for documents and agent files
     */
    private function updateRetrievalStats(array $results): void
    {
        if (empty($results)) {
            return;
        }

        $documentIds = collect($results)->pluck('id')->toArray();
        $agentFileIds = collect($results)->pluck('agent_file_id')->unique()->toArray();

        Document::query()
            ->whereIn('id', $documentIds)
            ->increment('retrieval_count');

        AgentFile::query()
            ->whereIn('id', $agentFileIds)
            ->increment('retrieval_count');
    }

    /**
     * Perform intelligent reranking with caching
     */
    private function performIntelligentReranking(string $query, array $results): array
    {
        $cacheKey = $this->cachePrefix.'rerank:'.md5($query.serialize($results));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($query, $results) {
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
                Log::warning('Reranking failed, using original results', [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);

                return $results;
            }
        });
    }

    /**
     * Process and clean search results
     */
    private function processResults(array $results): array
    {
        return collect($results)
            ->map(function ($result) {
                $result->chunk = $this->removeXmlTags($result->chunk);

                return $result;
            })
            ->values()
            ->toArray();
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

    /**
     * Get optimized chunks using VectorChord search
     */
    public function chunks(int|\App\Models\Agent $agent, string $query, int $limit = 3, array $options = []): Collection
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit, $options);

        return collect($results)->pluck('chunk');
    }

    /**
     * Get chunks with metadata and scores for advanced use cases
     */
    public function chunksWithMetadata(int|\App\Models\Agent $agent, string $query, int $limit = 3, array $options = []): Collection
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit, $options);

        return collect($results)->map(function ($result) {
            return [
                'chunk' => $result->chunk,
                'score' => $result->score ?? null,
                'search_type' => $result->search_type ?? 'unknown',
                'agent_file_id' => $result->agent_file_id,
                'document_id' => $result->id,
            ];
        });
    }

    /**
     * Batch search for multiple queries (VectorChord optimized)
     */
    public function batchSearch(int|\App\Models\Agent $agent, array $queries, int $limit = 3, array $options = []): array
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $results = [];
        $batchSize = $this->vectorChordConfig['performance']['batch']['search_batch_size'] ?? 100;

        foreach (array_chunk($queries, $batchSize) as $queryBatch) {
            foreach ($queryBatch as $query) {
                $results[$query] = $this->search($agent, $query, $limit, $options);
            }
        }

        return $results;
    }

    public function searchInKnowledgeBaseIfNeeded(\App\Models\Agent $agent, string $userMessage)
    {
        if ($agent->isKnowledgeable()) {
            $chunks = $this->chunks($agent->id, $userMessage);

            if ($chunks->isEmpty()) {
                return $userMessage;
            }

            return Str::of("### User Question:\n")
                ->append($userMessage."\n")
                ->append("### Retrieved Context:\n")
                ->append($chunks->implode("\n\n"));
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
                    'content' => config('prompts.follow-up-system-prompt'),
                ],
                [
                    'role' => 'user',
                    'content' => template(config('prompts.follow-up'), [
                        'retrieved_chunks' => collect($chunks)->implode("\n\n"),
                        'user_query' => $userQuery,
                    ]),
                ],
            ],
        ]);

        return str_contains($res, 'search');
    }

    public function rewriteQuery(string $userQuery, $previousQuery = null): string
    {
        return app(OpenAI::class)->completion([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
You are a query rewriting assistant for a Retrieval-Augmented Generation (RAG) system. Your job is to rewrite the current user query to improve document retrieval by making it clear, explicit, and semantically aligned with the way information is stored in the knowledge base.

If there are acronyms or words you are not familiar with, do not try to rephrase them.

You may also receive the user’s previous query. If there is a correlation between the current and previous queries, incorporate the necessary context to make the rewritten query self-contained and unambiguous.

Do not invent or assume contexts or meanings that are not present in the input or conversation history. Do not generate hypothetical, generalized, or open-ended questions.

If the query is already clear and unambiguous, return it as-is.

Return only the rewritten query in plain text. Do not include any prefixes, suffixes, quotes, or formatting — just the clean, rewritten query.

',
                ],
                [
                    'role' => 'user',
                    'content' => '
                        User Query is: <UserQuery>'.$userQuery.'</UserQuery>.
                        Previous Query is: <PreviousQuery>'.$previousQuery.'</PreviousQuery>.
                    ',
                ],
            ],
        ]);
    }
}
