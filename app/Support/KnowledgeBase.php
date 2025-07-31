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

        $query = $this->rewriteQuery($query);
        ray('Rewriting query: '.$query);
        $embedding = embed($query);
        $vectorString = '['.implode(',', $embedding).']';

        // Configure search parameters based on options
        $accuracyLevel = $options['accuracy'] ?? 'default';
        $quantize = $options['quantize'] ?? false;

        $this->configureSearchParameters($accuracyLevel);

        $results = $this->hybridSearch($agent, $query, $vectorString, $limit, $quantize);

        if ($results) {
            $this->updateRetrievalStats($results);

            if (count($results) > 1) {
                $results = $this->rerank($query, $results);
            }
            ray($results);

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

    private function configureSearchParameters(string $accuracyLevel): void
    {
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
    }

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

    private function rerank(string $query, array $results): array
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

    public function chunks(int|\App\Models\Agent $agent, string $query, int $limit = 3, array $options = []): Collection
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit, $options);

        return collect($results)->pluck('chunk');
    }

    public function searchInKnowledgeBaseIfNeeded(\App\Models\Agent $agent, string $userMessage): string
    {
        if ($agent->isKnowledgeable()) {
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
        ray($res);

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
You are an expert at world knowledge. Your task is to step back and paraphrase a question to a more generic step-back question, which is easier to answer. Here are a few examples

"input": "Could the members of The Police perform lawful arrests?"
"output": "what can the members of The Police do?"

"input": "Jan Sindel’s was born in what country?"
"output": "what is Jan Sindel’s personal history?"

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
