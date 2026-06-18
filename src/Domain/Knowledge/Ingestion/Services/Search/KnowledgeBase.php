<?php

namespace Domain\Knowledge\Ingestion\Services\Search;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Jobs\UpdateRetrievalStatsJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KnowledgeBase
{
    private array $vectorChordConfig;

    public function __construct()
    {
        $this->vectorChordConfig = config('chatvia.vector', []);
    }

    public function search(int|Agent $agent, string $query): array
    {
        // The hybrid-search tenant qual needs the workspace id.
        $workspaceId = $agent instanceof Agent
            ? (int) $agent->user_id
            : (int) DB::table('agents')->where('id', $agent)->value('user_id');

        if ($agent instanceof Agent) {
            $agent = $agent->id;
        }

        if ($workspaceId === 0) {
            return [];
        }

        // Cheap guard: an agent with no attached sources can never satisfy the
        // EXISTS filter in any channel of hybridSearchForAgent, so short-circuit
        // before paying for the (possibly cold) query embedding and the
        // 4-channel vector/BM25 scan. agent_id is the leading column of the
        // agent_sources primary key, so this is an index seek.
        if (! DB::table('agent_sources')->where('agent_id', $agent)->exists()) {
            return [];
        }

        $searchStart = microtime(true);

        // --- Phase 1: query embedding ---
        // Note: query embedding uses provider-specific query conventions
        // (Qwen3 instruction prefix etc.), distinct from how documents are
        // embedded at ingestion time. AbstractEmbeddingsProvider::embedQuery
        // caches the vector for 24h under a model-versioned key, so a
        // sub-millisecond embed_ms below means a cache hit; anything larger
        // is a cold provider call.
        $embedStart = microtime(true);
        $embedding = embed_query($query);
        $embedMs = (microtime(true) - $embedStart) * 1000;

        $vectorString = '['.implode(',', $embedding).']';

        $accuracyLevel = config('chatvia.vector.search.mode', 'default');
        $maxChunks = config('chatvia.vector.search.max_chunks', 3);
        $rerankEnabled = config('chatvia.vector.search.rerank', false);
        $candidateLimit = $rerankEnabled
            ? config('chatvia.vector.search.rerank_candidates', 12)
            : ($maxChunks * 2);

        // --- Phase 2: hybrid SQL search (4-channel RRF) ---
        // SET LOCAL needs a transaction so vchordrq settings don't leak
        // across requests on pooled connections (pgbouncer / Octane).
        $sqlStart = microtime(true);
        $results = DB::transaction(function () use ($agent, $workspaceId, $query, $vectorString, $candidateLimit, $accuracyLevel): array {
            $chunkBuilder = Chunk::query();
            $chunkBuilder->configureSearchAccuracy($accuracyLevel, $this->vectorChordConfig);

            return $chunkBuilder->hybridSearchForAgent($agent, $workspaceId, $query, $vectorString, $candidateLimit);
        });
        $sqlMs = (microtime(true) - $sqlStart) * 1000;

        $candidateCount = count($results);
        $reranked = false;
        $rerankMs = 0.0;

        if ($results) {
            // Dispatch stats update to background job for better performance.
            // Dispatched before decryption so the chunk ciphertext (not the
            // plaintext) is what transits the queue payload — the stats job
            // never reads the chunk text.
            UpdateRetrievalStatsJob::dispatch($results, $agent);

            // hybridSearchForAgent returns raw DB::select rows, so the Chunk
            // model's `encrypted` cast on `chunk` never ran. Decrypt in place
            // before any downstream consumer (rerank, processResults, chunks())
            // touches the text.
            foreach ($results as $result) {
                $result->chunk = Crypt::decryptString($result->chunk);
            }

            // --- Phase 3: rerank (model call) or DB-confidence filter ---
            // Only rerank if we have multiple results, reranking is enabled in
            // config, and the configured embeddings provider supports it.
            $provider = app(EmbeddingsProviderInterface::class);
            $rerankStart = microtime(true);
            if (count($results) > 1 && $rerankEnabled && $provider->supportsReranking()) {
                $results = $this->rerank($query, $results, $provider);
                $reranked = true;
            } else {
                // When reranking is disabled or unsupported, use DB confidence
                // score to filter and limit.
                $results = $this->filterByDbConfidence($results);
            }
            $rerankMs = (microtime(true) - $rerankStart) * 1000;

            $results = $this->processResults($results);
        }

        // Phase breakdown so we can see where search latency actually goes
        // (cold embed call vs the 4-channel SQL vs the reranker round-trip)
        // before trading retrieval quality for fewer channels. Debug-level, so
        // it only writes when LOG_LEVEL=debug.
        Log::debug('KnowledgeBase: search complete', [
            'agent_id' => $agent,
            'query_chars' => strlen($query),
            'accuracy' => $accuracyLevel,
            'candidate_limit' => $candidateLimit,
            'candidates' => $candidateCount,
            'returned' => count($results),
            'reranked' => $reranked,
            'embed_ms' => round($embedMs, 2),
            'sql_ms' => round($sqlMs, 2),
            'rerank_ms' => round($rerankMs, 2),
            'total_ms' => round((microtime(true) - $searchStart) * 1000, 2),
        ]);

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

    public function chunks(int|Agent $agent, string $query): Collection
    {
        $results = $this->search($agent, $query);

        return collect($results)->pluck('chunk');
    }
}
