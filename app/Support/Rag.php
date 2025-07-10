<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pgvector\Laravel\Distance;

class Rag
{
    private int $cacheTtl;
    private string $cachePrefix;
    private int $candidateLimit;

    public function __construct()
    {
        $this->cacheTtl = config('vector_search.cache.ttl', 3600);
        $this->cachePrefix = config('vector_search.cache.prefix', 'rag_search:');
        $this->candidateLimit = config('vector_search.search.candidate_limit', 500);
    }

public function binarySearch(int|\App\Models\Agent $agent, string $query, int $limit = 3): array
{
    if ($agent instanceof \App\Models\Agent) {
        $agent = $agent->id;
    }

    // Generate cache key
    $cacheKey = $this->cachePrefix . 'binary:' . md5($agent . ':' . $query . ':' . $limit);

    // Try cache first
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $embedding = embed($query);
    $vectorString = '[' . implode(',', $embedding) . ']';

    $sql = "
        SELECT id, chunk, embedding
        FROM documents
        WHERE agent_id = ?
        ORDER BY binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536))
        LIMIT ?
    ";

    $results = DB::select($sql, [
        $agent,
        $vectorString,
        $limit
    ]);

    // Cache results
    Cache::put($cacheKey, $results, $this->cacheTtl);

    return $results;
}

    public function search(int|\App\Models\Agent $agent, string $query, int $limit = 3): array
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

//        // Generate cache key
//        $cacheKey = $this->cachePrefix . md5($agent . ':' . $query . ':' . $limit);
//
//        // Try cache first
//        $cached = Cache::get($cacheKey);
//        if ($cached !== null) {
//            return $cached;
//        }
//
        $embedding = embed($query);
        $vectorString = '[' . implode(',', $embedding) . ']';

        $sql = "
        WITH ranked_candidates AS (
            SELECT id, chunk, embedding,
                   binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536)) as binary_distance
            FROM documents
            WHERE agent_id = ?
            ORDER BY binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536))
            LIMIT ?
        )
        SELECT id, chunk
        FROM ranked_candidates
        ORDER BY embedding <=> ?::vector(1536)
        LIMIT ?
        ";

        $results = DB::select($sql, [
            $vectorString,
            $agent,
            $vectorString,
            $this->candidateLimit,
            $vectorString,
            $limit
        ]);

        // Cache results
//        Cache::put($cacheKey, $results, $this->cacheTtl);

        return $results;
    }

   public function bm25(int|\App\Models\Agent $agent, $query)
{
    if ($agent instanceof \App\Models\Agent) {
        $agent = $agent->id;
    }

    // Set search path to include required schemas for BM25
    DB::statement('SET search_path = public, bm25_catalog, tokenizer_catalog');

    $sql = "
        SELECT
            id,
            chunk,
            embedding_bm25 <&> bm25_catalog.to_bm25query('documents_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS rank
        FROM documents
        WHERE agent_id = ?
        ORDER BY rank
        LIMIT ?
    ";

    return DB::select($sql, [
        $query,
        $agent,
        3,
    ]);
}

    public function chunks(int $agent, string $query, int $limit = 3): string
    {
        $results = $this->search($agent, $query, $limit);

        return collect($results)->pluck('chunk')->implode("\n\n");
    }

    public function makeLlmMessageFromUserMessage(\App\Models\Agent $agent, string $userMessage)
    {
        if ($agent->isKnowledgeable()) {
            $chunks = $this->chunks($agent->id, $userMessage);

            return Str::of("### User Question:\n")
                ->append($userMessage."\n")
                ->append("### Retrieved Context:\n")
                ->append($chunks);
        }

        return $userMessage;
    }

    public function clearCache(?int $agent = null): void
    {
        if ($agent) {
            // Clear cache for specific agent - more complex as we'd need to track keys
            Cache::flush(); // Simplified approach
        } else {
            Cache::flush();
        }
    }
}
